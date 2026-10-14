<?php

namespace Tests\Feature\Migration;

use App\Http\Controllers\Admin\MigrationPackageDownloadController;
use App\Models\Admin;
use App\Security\PackageAudit;
use App\Services\Migration\Export\PackageOutbox;
use App\Services\Migration\Transport\RangeFileStreamer;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/** An administrator downloading their own package: signed address, permission, range, and what is written down. */
class AdminPackageDownloadTest extends TestCase
{
    use RefreshDatabase;

    private string $dir = '';

    private string $body = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
        $this->dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oeparts-admindl-'.bin2hex(random_bytes(4));
        mkdir($this->dir.'/exports', 0700, true);
        $this->app->instance(PackageOutbox::class, new PackageOutbox($this->dir.'/exports'));
        $this->app->instance(PackageAudit::class, new PackageAudit($this->dir.'/history.ndjson'));
        $this->app->when(RangeFileStreamer::class)->needs('$closeOutputBuffers')->give(false);

        $this->body = 'OEPARTS1'.random_bytes(120_000);
        file_put_contents($this->dir.'/exports/site.oeparts', $this->body);
    }

    protected function tearDown(): void
    {
        $this->travelBack();

        foreach (glob($this->dir.'/{,*/}{,.}*', GLOB_BRACE) ?: [] as $file) {
            is_file($file) ? @unlink($file) : null;
        }

        @rmdir($this->dir.'/exports');
        @rmdir($this->dir);

        parent::tearDown();
    }

    private function admin(string $role): Admin
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole($role);

        return $admin;
    }

    #[Test]
    public function a_permitted_admin_downloads_the_package_through_a_signed_address(): void
    {
        $this->actingAs($this->admin('super_admin'), 'admin');

        $response = $this->get(MigrationPackageDownloadController::url('site.oeparts'));

        $response->assertOk();
        $this->assertSame($this->body, $response->streamedContent());
        $this->assertSame('attachment; filename="site.oeparts"', $response->headers->get('Content-Disposition'));
        $this->assertSame('bytes', $response->headers->get('Accept-Ranges'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        $history = (string) file_get_contents($this->dir.'/history.ndjson');
        $this->assertStringContainsString('"event":"package.downloaded"', $history);
        $this->assertStringContainsString('site.oeparts', $history);
    }

    #[Test]
    public function a_download_can_be_resumed_with_a_range(): void
    {
        $this->actingAs($this->admin('super_admin'), 'admin');
        $url = MigrationPackageDownloadController::url('site.oeparts');

        $part = $this->withHeader('Range', 'bytes=50000-')->get($url);

        $part->assertStatus(206);
        $this->assertSame('bytes 50000-'.(strlen($this->body) - 1).'/'.strlen($this->body), $part->headers->get('Content-Range'));
        $this->assertSame(substr($this->body, 50000), $part->streamedContent());
    }

    #[Test]
    public function an_admin_without_the_permission_is_refused_even_with_a_valid_signature(): void
    {
        $this->actingAs($this->admin('support'), 'admin');

        $this->get(MigrationPackageDownloadController::url('site.oeparts'))->assertForbidden();
    }

    #[Test]
    public function an_unsigned_a_tampered_and_an_expired_address_are_refused(): void
    {
        $this->actingAs($this->admin('super_admin'), 'admin');

        $this->get('/admin/migration/packages/site.oeparts')->assertForbidden();

        $signed = MigrationPackageDownloadController::url('site.oeparts');
        $this->get(str_replace('signature=', 'signature=0', $signed))->assertForbidden();

        $this->travel(6)->minutes();
        $this->get($signed)->assertForbidden();
    }

    #[Test]
    public function nobody_who_is_not_signed_in_gets_anything(): void
    {
        $signed = MigrationPackageDownloadController::url('site.oeparts');

        $response = $this->get($signed);

        $this->assertContains($response->getStatusCode(), [302, 401, 403]);
        $this->assertStringNotContainsString('OEPARTS1', (string) $response->getContent());
    }

    #[Test]
    public function only_a_package_name_inside_the_exports_folder_is_served(): void
    {
        $this->actingAs($this->admin('super_admin'), 'admin');

        $this->get(MigrationPackageDownloadController::url('missing.oeparts'))->assertNotFound();

        // a name that is not a package name never reaches the controller: the route refuses the shape
        foreach (['..%2F..%2F.env', 'site.oeparts.json', 'notes.txt'] as $name) {
            $this->get(URL::temporarySignedRoute(MigrationPackageDownloadController::ROUTE, now()->addMinute(), ['name' => rawurldecode($name)], absolute: true))->assertNotFound();
        }
    }

    #[Test]
    public function the_route_has_the_download_limiter_and_is_not_a_closure(): void
    {
        $route = app('router')->getRoutes()->getByName(MigrationPackageDownloadController::ROUTE);

        $this->assertNotNull($route);
        $this->assertStringNotContainsString('Closure', $route->getActionName());
        $this->assertContains('throttle:export-download', $route->gatherMiddleware());
        $this->assertContains('signed', $route->gatherMiddleware());
    }
}

<?php

namespace Tests\Feature;

use App\Http\Middleware\AnnouncePageSize;
use App\Models\Admin;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * An admin page announces its real size (Server-Timing: oelen) and the page guard reports a page that arrived with fewer
 * bytes — the evidence for WHERE a "blank page until reload" is made on a live server.
 */
class PageIntegrityReportTest extends TestCase
{
    use RefreshDatabase;

    private function through(string $path, Response $response): Response
    {
        return (new AnnouncePageSize)->handle(Request::create($path), fn () => $response);
    }

    #[Test]
    public function an_admin_html_page_announces_how_many_bytes_it_has(): void
    {
        $html = '<html>'.str_repeat('é', 1000).'</html>';

        $out = $this->through('/admin/orders', new Response($html, 200, ['Content-Type' => 'text/html; charset=utf-8']));

        $this->assertSame('oelen;desc="'.strlen($html).'"', $out->headers->get('Server-Timing'));
    }

    #[Test]
    public function an_existing_server_timing_header_is_kept(): void
    {
        $out = $this->through('/admin', new Response('<html></html>', 200, ['Content-Type' => 'text/html', 'Server-Timing' => 'db;dur=5']));

        $this->assertStringStartsWith('db;dur=5, oelen;desc="', (string) $out->headers->get('Server-Timing'));
    }

    #[Test]
    public function other_responses_are_left_alone(): void
    {
        $this->assertNull($this->through('/en', new Response('<html></html>', 200, ['Content-Type' => 'text/html']))->headers->get('Server-Timing'), 'storefront');
        $this->assertNull($this->through('/admin/orders', new Response('{}', 200, ['Content-Type' => 'application/json']))->headers->get('Server-Timing'), 'json');
        $this->assertNull($this->through('/admin/orders', new Response('<html></html>', 500, ['Content-Type' => 'text/html']))->headers->get('Server-Timing'), 'an error page');
    }

    #[Test]
    public function a_short_page_report_is_written_to_the_slow_admin_log_without_page_content(): void
    {
        $this->seed(RolesSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');

        Log::shouldReceive('channel')->once()->with('slow_admin')->andReturnSelf();
        Log::shouldReceive('warning')->once()->withArgs(function (string $message, array $context): bool {
            return $message === 'Admin page reached the browser incomplete'
                && $context['server_sent_bytes'] === 350585
                && $context['browser_received_bytes'] === 286206
                && $context['missing_bytes'] === 64379
                && $context['protocol'] === 'h3'
                && ! array_key_exists('ip', $context);
        });

        $this->actingAs($admin, 'admin')
            ->postJson('/admin/diagnostics/page-integrity', ['path' => '/admin/orders', 'expected' => 350585, 'received' => 286206, 'transfer' => 286500, 'protocol' => 'h3', 'via' => 'navigate', 'millis' => 420])
            ->assertOk();
    }

    #[Test]
    public function a_report_needs_a_signed_in_admin_and_sane_numbers(): void
    {
        $this->postJson('/admin/diagnostics/page-integrity', ['path' => '/admin', 'expected' => 10, 'received' => 5])->assertRedirect();

        $this->seed(RolesSeeder::class);
        $admin = Admin::factory()->create();
        $admin->assignRole('super_admin');
        $this->actingAs($admin, 'admin')
            ->postJson('/admin/diagnostics/page-integrity', ['path' => '/admin', 'expected' => 'lots', 'received' => -1])
            ->assertStatus(422);
    }
}

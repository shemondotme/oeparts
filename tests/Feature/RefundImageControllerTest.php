<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Refund evidence photos live on the private ('local') disk and are only
 * reachable through a signed URL (see AppServiceProvider::boot()'s
 * buildTemporaryUrlsUsing for the 'local' disk) plus an admin permission
 * check inside the controller itself.
 */
class RefundImageControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([\Database\Seeders\SettingsSeeder::class, \Database\Seeders\RolesSeeder::class]);
        Storage::fake('local');
        Storage::disk('local')->put('refund-images/evidence.jpg', 'fake-jpeg-bytes');
    }

    private function adminWithRole(string $role): Admin
    {
        $admin = Admin::factory()->create(['is_active' => true]);
        $admin->assignRole($role);

        return $admin;
    }

    private function signedRefundImageUrl(string $path = 'refund-images/evidence.jpg'): string
    {
        return URL::temporarySignedRoute('admin.refund-images.show', now()->addMinutes(30), ['path' => $path]);
    }

    #[Test]
    public function guests_are_redirected_to_login(): void
    {
        $this->get($this->signedRefundImageUrl())->assertRedirect(route('filament.admin.auth.login'));
    }

    #[Test]
    public function an_admin_without_view_refunds_permission_is_forbidden(): void
    {
        $this->actingAs($this->adminWithRole('catalog_admin'), 'admin');

        $this->get($this->signedRefundImageUrl())->assertForbidden();
    }

    #[Test]
    public function an_admin_with_view_refunds_permission_can_view_the_image(): void
    {
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        $response = $this->get($this->signedRefundImageUrl());

        $response->assertSuccessful();
        $this->assertSame('fake-jpeg-bytes', $response->streamedContent());
    }

    #[Test]
    public function a_tampered_signature_is_rejected(): void
    {
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        $url = $this->signedRefundImageUrl() . '-tampered';

        $this->get($url)->assertForbidden();
    }

    #[Test]
    public function a_path_outside_refund_images_is_rejected_even_with_a_valid_signature(): void
    {
        Storage::disk('local')->put('invoices/some-order.pdf', '%PDF-fake');
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        $this->get($this->signedRefundImageUrl('invoices/some-order.pdf'))->assertNotFound();
    }

    #[Test]
    public function a_missing_image_returns_not_found(): void
    {
        $this->actingAs($this->adminWithRole('super_admin'), 'admin');

        $this->get($this->signedRefundImageUrl('refund-images/does-not-exist.jpg'))->assertNotFound();
    }
}

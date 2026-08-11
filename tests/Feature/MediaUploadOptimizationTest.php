<?php

namespace Tests\Feature;

use App\Filament\Resources\MediaFileResource\Pages\ListMediaFiles;
use App\Models\Admin;
use App\Models\MediaFile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Confirms ImageOptimizationService is actually wired into the two upload
 * endpoints that feed the Media Library (the Media Picker used by the CMS
 * section builder, and the WYSIWYG editor's inline image tool) — both used
 * to store the file exactly as UploadedImageSanitizer left it, with no
 * resize/compress/WebP step at all.
 */
class MediaUploadOptimizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function admin(): Admin
    {
        // Both upload endpoints gate on `auth.admin` middleware only (no
        // per-permission check inside MediaPickerController::upload() /
        // EditorController::uploadImage() — see destroy(), which DOES check
        // 'delete media files', for contrast) — any authenticated admin.
        return Admin::factory()->create(['is_active' => true]);
    }

    private function fakeJpeg(int $width = 60, int $height = 60): UploadedFile
    {
        // GD-backed fake — real, GD-decodable JPEG bytes, not a dummy stub.
        return UploadedFile::fake()->image('logo.jpg', $width, $height);
    }

    #[Test]
    public function the_media_picker_upload_endpoint_stores_an_optimized_webp_file(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $response = $this->post('/admin/cms/media-picker/upload', [
            'file' => $this->fakeJpeg(),
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);

        $storedPath = $response->json('file.file_path');
        $this->assertStringEndsWith('.webp', $storedPath, 'the JPEG upload was converted to WebP');
        $this->assertSame('image/webp', $response->json('file.mime_type'));
        Storage::disk('public')->assertExists($storedPath);
    }

    #[Test]
    public function the_editor_upload_endpoint_serves_an_optimized_webp_file(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $response = $this->post('/admin/editor/upload-image', [
            'file' => $this->fakeJpeg(),
        ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertStringEndsWith('.webp', $response->json('location'));
    }

    #[Test]
    public function an_oversized_upload_is_downscaled_before_it_ever_reaches_the_media_library(): void
    {
        \App\Models\Setting::updateOrCreate(['group' => 'performance', 'key' => 'image_max_width'], ['value' => '200', 'type' => 'integer', 'is_encrypted' => false]);
        \App\Models\Setting::updateOrCreate(['group' => 'performance', 'key' => 'image_max_height'], ['value' => '200', 'type' => 'integer', 'is_encrypted' => false]);
        app(\App\Services\SettingsService::class)->forget('performance');

        $this->actingAs($this->admin(), 'admin');

        $response = $this->post('/admin/cms/media-picker/upload', [
            'file' => $this->fakeJpeg(2000, 1000),
        ]);

        $response->assertOk();
        $storedPath = $response->json('file.file_path');
        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($storedPath));
        $this->assertSame(200, $w);
        $this->assertSame(100, $h);
    }

    #[Test]
    public function the_media_library_admin_upload_action_stores_an_optimized_webp_file(): void
    {
        $this->seed([
            \Database\Seeders\SettingsSeeder::class,
            \Database\Seeders\RolesSeeder::class,
            \Database\Seeders\AdminSeeder::class,
        ]);
        $this->actingAs(Admin::where('email', 'superadmin@oeparts.test')->firstOrFail(), 'admin');

        // A TABLE header action (registered via Table::headerActions(), not
        // the page's own getHeaderActions()) — callTableAction(), not
        // callAction(), which only searches page-level actions.
        Livewire::test(ListMediaFiles::class)
            ->callTableAction('upload', data: ['file' => $this->fakeJpeg()]);

        $media = MediaFile::latest('id')->first();
        $this->assertNotNull($media);
        $this->assertStringEndsWith('.webp', $media->file_path, 'the JPEG upload was converted to WebP');
        $this->assertSame('image/webp', $media->mime_type);
        Storage::disk('public')->assertExists($media->file_path);
    }
}

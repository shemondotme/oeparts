<?php

namespace Tests\Unit;

use App\Models\Setting;
use App\Services\ImageOptimizationService;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Every media-library-family upload (Media Library, the inline WYSIWYG
 * editor image tool, the Media Picker) was stored exactly as received — no
 * size cap, no re-compression, no WebP conversion — despite the app already
 * re-encoding every image through GD anyway (UploadedImageSanitizer).
 * Deliberately NOT wired into refund evidence photos, which must stay
 * byte-for-byte as the customer submitted them (see AccountController::
 * storeRefundImage — untouched by this service).
 */
class ImageOptimizationServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    private function putJpeg(string $path, int $width, int $height): void
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 120, 60, 200));
        ob_start();
        imagejpeg($image, null, 90);
        $bytes = ob_get_clean();
        imagedestroy($image);

        Storage::disk('public')->put($path, $bytes);
    }

    private function putGif(string $path): void
    {
        $image = imagecreatetruecolor(50, 50);
        ob_start();
        imagegif($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        Storage::disk('public')->put($path, $bytes);
    }

    #[Test]
    public function it_downscales_an_oversized_image_preserving_aspect_ratio(): void
    {
        Setting::updateOrCreate(['group' => 'performance', 'key' => 'image_max_width'], ['value' => '100', 'type' => 'integer', 'is_encrypted' => false]);
        Setting::updateOrCreate(['group' => 'performance', 'key' => 'image_max_height'], ['value' => '100', 'type' => 'integer', 'is_encrypted' => false]);
        app(SettingsService::class)->forget('performance');

        $this->putJpeg('media/wide.jpg', 400, 100); // 4:1 ratio, wider than tall

        $result = app(ImageOptimizationService::class)->optimize('public', 'media/wide.jpg', 'image/jpeg');

        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($result['path']));
        $this->assertSame(100, $w, 'capped to max width');
        $this->assertSame(25, $h, 'height scaled down keeping the 4:1 ratio');
    }

    #[Test]
    public function a_smaller_than_cap_image_is_never_upscaled(): void
    {
        $this->putJpeg('media/small.jpg', 50, 50);

        $result = app(ImageOptimizationService::class)->optimize('public', 'media/small.jpg', 'image/jpeg');

        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($result['path']));
        $this->assertSame(50, $w);
        $this->assertSame(50, $h);
    }

    #[Test]
    public function it_converts_jpeg_to_webp_by_default(): void
    {
        $this->putJpeg('media/photo.jpg', 60, 60);

        $result = app(ImageOptimizationService::class)->optimize('public', 'media/photo.jpg', 'image/jpeg');

        $this->assertSame('media/photo.webp', $result['path']);
        $this->assertSame('image/webp', $result['mime']);
        Storage::disk('public')->assertExists('media/photo.webp');
        Storage::disk('public')->assertMissing('media/photo.jpg');
    }

    #[Test]
    public function disabling_webp_conversion_keeps_the_original_format(): void
    {
        Setting::updateOrCreate(['group' => 'performance', 'key' => 'image_convert_webp'], ['value' => 'false', 'type' => 'boolean', 'is_encrypted' => false]);
        app(SettingsService::class)->forget('performance');

        $this->putJpeg('media/photo.jpg', 60, 60);

        $result = app(ImageOptimizationService::class)->optimize('public', 'media/photo.jpg', 'image/jpeg');

        $this->assertSame('media/photo.jpg', $result['path']);
        $this->assertSame('image/jpeg', $result['mime']);
        Storage::disk('public')->assertExists('media/photo.jpg');
    }

    #[Test]
    public function disabling_optimization_entirely_leaves_the_file_untouched(): void
    {
        Setting::updateOrCreate(['group' => 'performance', 'key' => 'optimize_images'], ['value' => 'false', 'type' => 'boolean', 'is_encrypted' => false]);
        app(SettingsService::class)->forget('performance');

        $this->putJpeg('media/photo.jpg', 5000, 5000);

        $result = app(ImageOptimizationService::class)->optimize('public', 'media/photo.jpg', 'image/jpeg');

        $this->assertSame('media/photo.jpg', $result['path']);
        $this->assertSame('image/jpeg', $result['mime']);
        [$w] = getimagesizefromstring(Storage::disk('public')->get('media/photo.jpg'));
        $this->assertSame(5000, $w, 'no resize happened while the feature is off');
    }

    #[Test]
    public function a_gif_is_left_completely_untouched_to_avoid_flattening_an_animation(): void
    {
        $this->putGif('media/anim.gif');

        $result = app(ImageOptimizationService::class)->optimize('public', 'media/anim.gif', 'image/gif');

        $this->assertSame('media/anim.gif', $result['path']);
        $this->assertSame('image/gif', $result['mime']);
    }

    #[Test]
    public function an_unreadable_file_fails_soft_and_returns_the_original(): void
    {
        Storage::disk('public')->put('media/not-really-an-image.jpg', 'this is just text, not JPEG bytes');

        $result = app(ImageOptimizationService::class)->optimize('public', 'media/not-really-an-image.jpg', 'image/jpeg');

        $this->assertSame('media/not-really-an-image.jpg', $result['path']);
        Storage::disk('public')->assertExists('media/not-really-an-image.jpg');
    }

    /**
     * Phase 14 (Monitoring/Logging Audit). The catch(\Throwable) around the
     * actual resize/WebP-convert/save pass was completely silent — if GD is
     * systemically broken (bad build, missing WebP support), every single
     * upload would fail soft forever with nothing anywhere to explain why.
     * Forces a genuine write failure (a directory already sitting at the
     * WebP destination path, so imagewebp() can't create a file there)
     * rather than mocking — this exercises the real catch path, not an
     * assumption about it.
     */
    #[Test]
    public function a_failure_during_the_actual_optimization_pass_logs_a_warning_and_fails_soft(): void
    {
        Log::spy();

        $this->putJpeg('media/photo.jpg', 60, 60);
        mkdir(Storage::disk('public')->path('media/photo.webp'), 0775, true);

        $result = app(ImageOptimizationService::class)->optimize('public', 'media/photo.jpg', 'image/jpeg');

        $this->assertSame('media/photo.jpg', $result['path'], 'falls back to the original, untouched');
        Storage::disk('public')->assertExists('media/photo.jpg');
        Log::shouldHaveReceived('warning')
            ->once()
            ->withArgs(fn ($message) => str_contains($message, 'ImageOptimizationService') && str_contains($message, 'media/photo.jpg'));
    }
}

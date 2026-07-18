<?php

namespace Tests\Unit\Services;

use App\Services\UploadedImageSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UploadedImageSanitizerTest extends TestCase
{
    private UploadedImageSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new UploadedImageSanitizer();
        Storage::fake('local');
    }

    private function realJpeg(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'sanitizertest') . '.jpg';
        $im = imagecreatetruecolor(3, 3);
        imagejpeg($im, $path);
        imagedestroy($im);

        return new UploadedFile($path, 'test.jpg', 'image/jpeg', null, true);
    }

    #[Test]
    public function it_accepts_a_clean_image(): void
    {
        $this->sanitizer->assertSafe($this->realJpeg());
        $this->addToAssertionCount(1); // no exception thrown
    }

    #[Test]
    public function it_rejects_a_file_carrying_a_php_tag(): void
    {
        $file = $this->realJpeg();
        file_put_contents($file->getRealPath(), file_get_contents($file->getRealPath()) . '<?php system($_GET["c"]); ?>');

        $this->expectException(\InvalidArgumentException::class);
        $this->sanitizer->assertSafe($file);
    }

    #[Test]
    public function it_rejects_a_file_carrying_a_script_tag(): void
    {
        $file = $this->realJpeg();
        file_put_contents($file->getRealPath(), file_get_contents($file->getRealPath()) . '<script>alert(1)</script>');

        $this->expectException(\InvalidArgumentException::class);
        $this->sanitizer->assertSafe($file);
    }

    #[Test]
    public function sanitize_strips_a_trailing_payload_from_a_jpeg(): void
    {
        Storage::disk('local')->put('test/dirty.jpg', file_get_contents($this->realJpeg()->getRealPath()) . 'TRAILING-PAYLOAD-MARKER');
        $this->assertStringContainsString('TRAILING-PAYLOAD-MARKER', Storage::disk('local')->get('test/dirty.jpg'));

        $this->sanitizer->sanitize('local', 'test/dirty.jpg', 'image/jpeg');

        $this->assertStringNotContainsString('TRAILING-PAYLOAD-MARKER', Storage::disk('local')->get('test/dirty.jpg'));
    }

    #[Test]
    public function sanitize_is_a_no_op_for_unsupported_mime_types(): void
    {
        Storage::disk('local')->put('test/file.pdf', '%PDF-not-really-an-image');

        $this->sanitizer->sanitize('local', 'test/file.pdf', 'application/pdf');

        $this->assertSame('%PDF-not-really-an-image', Storage::disk('local')->get('test/file.pdf'));
    }
}

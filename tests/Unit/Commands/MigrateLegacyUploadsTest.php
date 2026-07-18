<?php

namespace Tests\Unit\Commands;

use App\Enums\RefundStatus;
use App\Models\Admin;
use App\Models\MediaFile;
use App\Models\RefundRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MigrateLegacyUploadsTest extends TestCase
{
    use RefreshDatabase;

    private int $adminId;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        $this->adminId = Admin::factory()->create()->id;
    }

    private function createMediaFile(array $attrs): MediaFile
    {
        return MediaFile::create(array_merge([
            'uploaded_by' => $this->adminId,
            'file_name' => 'test.png',
            'file_url' => 'http://example.test/storage/' . ($attrs['file_path'] ?? 'test.png'),
            'mime_type' => 'image/png',
            'size' => 5,
        ], $attrs));
    }

    #[Test]
    public function it_migrates_a_flat_media_file_and_updates_the_media_file_row(): void
    {
        Storage::disk('public')->put('media/legacy-logo.png', 'bytes');
        $media = $this->createMediaFile([
            'file_path' => 'media/legacy-logo.png',
            'file_url' => 'http://example.test/storage/media/legacy-logo.png',
        ]);
        $media->created_at = '2026-03-15 10:00:00';
        $media->save();

        $this->artisan('oeparts:storage:migrate-legacy-uploads')->assertSuccessful();

        $media->refresh();
        $this->assertSame('media/2026/03/legacy-logo.png', $media->file_path);
        Storage::disk('public')->assertExists('media/2026/03/legacy-logo.png');
        Storage::disk('public')->assertMissing('media/legacy-logo.png');
        $this->assertStringContainsString('media/2026/03/legacy-logo.png', $media->file_url);
    }

    #[Test]
    public function it_leaves_already_partitioned_media_alone(): void
    {
        Storage::disk('public')->put('media/2026/07/current.png', 'bytes');
        $media = $this->createMediaFile([
            'file_path' => 'media/2026/07/current.png',
            'file_url' => 'http://example.test/storage/media/2026/07/current.png',
        ]);

        $this->artisan('oeparts:storage:migrate-legacy-uploads')->assertSuccessful();

        $this->assertSame('media/2026/07/current.png', $media->fresh()->file_path);
        Storage::disk('public')->assertExists('media/2026/07/current.png');
    }

    #[Test]
    public function it_migrates_flat_editor_files_by_mtime(): void
    {
        Storage::disk('public')->put('editor/orphaned.jpg', 'bytes');
        touch(Storage::disk('public')->path('editor/orphaned.jpg'), mktime(0, 0, 0, 4, 1, 2026));

        $this->artisan('oeparts:storage:migrate-legacy-uploads')->assertSuccessful();

        Storage::disk('public')->assertExists('editor/2026/04/orphaned.jpg');
        Storage::disk('public')->assertMissing('editor/orphaned.jpg');
    }

    #[Test]
    public function it_migrates_refund_images_and_updates_the_object_format_path(): void
    {
        Storage::disk('local')->put('refund-images/flat.jpg', 'bytes');
        $refund = RefundRequest::factory()->create([
            'status' => RefundStatus::Pending,
            'return_images' => [[
                'path' => 'refund-images/flat.jpg',
                'original_name' => 'flat.jpg',
                'size' => 5,
                'uploaded_at' => '2026-02-10T00:00:00+00:00',
            ]],
        ]);

        $this->artisan('oeparts:storage:migrate-legacy-uploads')->assertSuccessful();

        $refund->refresh();
        $this->assertSame('refund-images/2026/02/flat.jpg', $refund->return_images[0]['path']);
        Storage::disk('local')->assertExists('refund-images/2026/02/flat.jpg');
    }

    #[Test]
    public function it_migrates_legacy_flat_string_refund_images(): void
    {
        Storage::disk('local')->put('refund-images/legacy.jpg', 'bytes');
        touch(Storage::disk('local')->path('refund-images/legacy.jpg'), mktime(0, 0, 0, 1, 1, 2026));

        $refund = RefundRequest::factory()->create([
            'status' => RefundStatus::Pending,
            'return_images' => ['refund-images/legacy.jpg'],
        ]);

        $this->artisan('oeparts:storage:migrate-legacy-uploads')->assertSuccessful();

        $refund->refresh();
        $this->assertSame('refund-images/2026/01/legacy.jpg', $refund->return_images[0]);
    }

    #[Test]
    public function it_migrates_flat_invoice_files_by_mtime(): void
    {
        Storage::disk('local')->put('invoices/OLD-001.pdf', '%PDF-old');
        touch(Storage::disk('local')->path('invoices/OLD-001.pdf'), mktime(0, 0, 0, 5, 1, 2026));

        $this->artisan('oeparts:storage:migrate-legacy-uploads')->assertSuccessful();

        Storage::disk('local')->assertExists('invoices/2026/05/OLD-001.pdf');
    }

    #[Test]
    public function dry_run_changes_nothing(): void
    {
        Storage::disk('public')->put('media/dry-run-test.png', 'bytes');
        $media = $this->createMediaFile(['file_path' => 'media/dry-run-test.png']);

        $this->artisan('oeparts:storage:migrate-legacy-uploads', ['--dry-run' => true])->assertSuccessful();

        Storage::disk('public')->assertExists('media/dry-run-test.png');
        $this->assertSame('media/dry-run-test.png', $media->fresh()->file_path);
    }

    #[Test]
    public function it_is_idempotent(): void
    {
        Storage::disk('public')->put('media/once.png', 'bytes');
        $media = $this->createMediaFile(['file_path' => 'media/once.png']);

        $this->artisan('oeparts:storage:migrate-legacy-uploads')->assertSuccessful();
        $firstPath = $media->fresh()->file_path;

        $this->artisan('oeparts:storage:migrate-legacy-uploads')->assertSuccessful();
        $secondPath = $media->fresh()->file_path;

        $this->assertSame($firstPath, $secondPath);
    }
}

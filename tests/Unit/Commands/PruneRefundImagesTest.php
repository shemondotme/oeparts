<?php

namespace Tests\Unit\Commands;

use App\Enums\RefundStatus;
use App\Models\RefundRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PruneRefundImagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([\Database\Seeders\SettingsSeeder::class]);
        Storage::fake('local');
    }

    #[Test]
    public function it_purges_images_for_refunds_processed_before_the_retention_window(): void
    {
        Storage::disk('local')->put('refund-images/2026/01/old.jpg', 'bytes');

        $refund = RefundRequest::factory()->create([
            'status' => RefundStatus::Approved,
            'processed_at' => now()->subDays(200),
            'return_images' => [[
                'path' => 'refund-images/2026/01/old.jpg',
                'original_name' => 'old.jpg',
                'size' => 5,
                'uploaded_at' => now()->subDays(200)->toIso8601String(),
            ]],
        ]);

        $this->artisan('oeparts:refunds:clean-images', ['--days' => 180])
            ->assertSuccessful();

        Storage::disk('local')->assertMissing('refund-images/2026/01/old.jpg');
        $this->assertNull($refund->fresh()->return_images);
    }

    #[Test]
    public function it_leaves_recently_processed_refunds_alone(): void
    {
        Storage::disk('local')->put('refund-images/2026/07/recent.jpg', 'bytes');

        $refund = RefundRequest::factory()->create([
            'status' => RefundStatus::Approved,
            'processed_at' => now()->subDays(5),
            'return_images' => [[
                'path' => 'refund-images/2026/07/recent.jpg',
                'original_name' => 'recent.jpg',
                'size' => 5,
                'uploaded_at' => now()->subDays(5)->toIso8601String(),
            ]],
        ]);

        $this->artisan('oeparts:refunds:clean-images', ['--days' => 180])
            ->assertSuccessful();

        Storage::disk('local')->assertExists('refund-images/2026/07/recent.jpg');
        $this->assertNotNull($refund->fresh()->return_images);
    }

    #[Test]
    public function it_leaves_pending_refunds_alone_regardless_of_age(): void
    {
        Storage::disk('local')->put('refund-images/2026/01/pending.jpg', 'bytes');

        $refund = RefundRequest::factory()->create([
            'status' => RefundStatus::Pending,
            'processed_at' => null,
            'return_images' => [[
                'path' => 'refund-images/2026/01/pending.jpg',
                'original_name' => 'pending.jpg',
                'size' => 5,
                'uploaded_at' => now()->subDays(200)->toIso8601String(),
            ]],
        ]);

        $this->artisan('oeparts:refunds:clean-images', ['--days' => 180])
            ->assertSuccessful();

        Storage::disk('local')->assertExists('refund-images/2026/01/pending.jpg');
        $this->assertNotNull($refund->fresh()->return_images);
    }

    #[Test]
    public function it_handles_legacy_flat_string_paths(): void
    {
        Storage::disk('local')->put('refund-images/legacy-old.jpg', 'bytes');

        RefundRequest::factory()->create([
            'status' => RefundStatus::Rejected,
            'processed_at' => now()->subDays(200),
            'return_images' => ['refund-images/legacy-old.jpg'],
        ]);

        $this->artisan('oeparts:refunds:clean-images', ['--days' => 180])
            ->assertSuccessful();

        Storage::disk('local')->assertMissing('refund-images/legacy-old.jpg');
    }

    #[Test]
    public function disabled_when_days_is_zero(): void
    {
        Storage::disk('local')->put('refund-images/2026/01/old.jpg', 'bytes');

        RefundRequest::factory()->create([
            'status' => RefundStatus::Approved,
            'processed_at' => now()->subDays(200),
            'return_images' => ['refund-images/2026/01/old.jpg'],
        ]);

        $this->artisan('oeparts:refunds:clean-images', ['--days' => 0])
            ->assertSuccessful();

        Storage::disk('local')->assertExists('refund-images/2026/01/old.jpg');
    }
}

<?php

namespace Tests\Unit\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PruneInvoiceCacheTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([\Database\Seeders\SettingsSeeder::class]);
        Storage::fake('local');
    }

    #[Test]
    public function it_deletes_invoices_older_than_the_retention_window(): void
    {
        Storage::disk('local')->put('invoices/2026/01/OLD-001.pdf', '%PDF-old');
        touch(Storage::disk('local')->path('invoices/2026/01/OLD-001.pdf'), now()->subDays(40)->timestamp);

        $this->artisan('oeparts:invoices:clean-cache', ['--days' => 30])
            ->assertSuccessful();

        Storage::disk('local')->assertMissing('invoices/2026/01/OLD-001.pdf');
    }

    #[Test]
    public function it_keeps_recent_invoices(): void
    {
        Storage::disk('local')->put('invoices/2026/07/RECENT-001.pdf', '%PDF-recent');

        $this->artisan('oeparts:invoices:clean-cache', ['--days' => 30])
            ->assertSuccessful();

        Storage::disk('local')->assertExists('invoices/2026/07/RECENT-001.pdf');
    }

    #[Test]
    public function disabled_when_days_is_zero(): void
    {
        Storage::disk('local')->put('invoices/2026/01/OLD-002.pdf', '%PDF-old');
        touch(Storage::disk('local')->path('invoices/2026/01/OLD-002.pdf'), now()->subDays(400)->timestamp);

        $this->artisan('oeparts:invoices:clean-cache', ['--days' => 0])
            ->assertSuccessful();

        Storage::disk('local')->assertExists('invoices/2026/01/OLD-002.pdf');
    }
}

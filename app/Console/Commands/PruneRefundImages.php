<?php

namespace App\Console\Commands;

use App\Enums\RefundStatus;
use App\Models\RefundRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneRefundImages extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oeparts:refunds:clean-images {--days= : Days after processing before images are purged (defaults to orders.refund_image_retention_days setting)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete customer-submitted refund evidence photos for long-resolved refund requests (GDPR data minimization)';

    public function handle(): int
    {
        $days = $this->option('days') !== null
            ? (int) $this->option('days')
            : (int) settings('orders.refund_image_retention_days', 180);

        if ($days <= 0) {
            $this->info('Refund image pruning disabled (orders.refund_image_retention_days is 0).');

            return self::SUCCESS;
        }

        $cutoff = now()->subDays($days);

        $refunds = RefundRequest::query()
            ->whereIn('status', [RefundStatus::Approved, RefundStatus::Rejected, RefundStatus::Processed])
            ->whereNotNull('processed_at')
            ->where('processed_at', '<', $cutoff)
            ->whereNotNull('return_images')
            ->get();

        $filesDeleted = 0;

        foreach ($refunds as $refund) {
            foreach ($refund->return_images ?? [] as $item) {
                $path = is_array($item) ? ($item['path'] ?? null) : $item;
                if ($path && Storage::disk('local')->exists($path)) {
                    Storage::disk('local')->delete($path);
                    $filesDeleted++;
                }
            }

            $refund->update(['return_images' => null]);
        }

        $this->info("Purged images for {$refunds->count()} refund request(s), deleting {$filesDeleted} file(s) (processed before {$cutoff->toDateString()}).");

        return self::SUCCESS;
    }
}

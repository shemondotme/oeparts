<?php

namespace App\Jobs;

use App\Models\Product;
use App\Support\AdminNotifier;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * ProductResource's "Mark In Stock"/"Mark Out of Stock" row-selection bulk
 * actions ran synchronously for any selection size — safe for the typical
 * case (a handful of checked rows), but Filament's default "select all X
 * records" crosses pagination, and on the real catalog (1M+ products; the
 * dev seed is only ~90 — see [[project_production_catalog_scale]]) a
 * genuinely large selection risked a memory/timeout crash mid-write, with
 * no natural recovery (unlike a read-only export, a half-completed bulk
 * write leaves some rows changed and others not, with no record of where
 * it stopped).
 *
 * Only dispatched above ProductResource::LARGE_BATCH_ROW_THRESHOLD — the
 * common small-selection case stays instant (matches the sibling
 * BulkUpdateProducts page's own LARGE_BATCH_THRESHOLD=500 pattern, and
 * keeps the existing product-bulk-actions.spec.js e2e test's "completes
 * immediately" assumption intact for the case it actually covers).
 *
 * Chunked per-record ->save() (not a single mass UPDATE) so
 * Product::booted()'s cache-invalidation observer hook still fires for
 * every changed row — same reasoning BulkUpdateProducts::apply() already
 * documents for its own identical stock_in/stock_out actions.
 */
class BulkUpdateProductStockStatus implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    /**
     * @param  array<int, int>  $productIds
     */
    public function __construct(
        public array $productIds,
        public bool $inStock,
        public string $triggeredBy,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $updated = 0;

        Product::query()
            ->whereIn('id', $this->productIds)
            ->where('is_in_stock', ! $this->inStock)
            ->chunkById(200, function ($products) use (&$updated) {
                foreach ($products as $product) {
                    $product->is_in_stock = $this->inStock;
                    $product->save();
                    $updated++;
                }
            });

        $this->notifyResult($updated);
    }

    private function notifyResult(int $updated): void
    {
        try {
            $label = $this->inStock ? 'marked in stock' : 'marked out of stock';

            AdminNotifier::toRoles(
                ['super_admin', 'admin'],
                Notification::make()
                    ->title("Bulk stock update finished: {$updated} product(s) {$label}")
                    ->body("Requested by {$this->triggeredBy}.")
                    ->icon('heroicon-o-archive-box')
                    ->iconColor('success')
            );
        } catch (Throwable $e) {
            // A bell notification must never fail a job that otherwise succeeded.
            Log::warning('Failed to send bulk stock update completion notification', ['error' => $e->getMessage()]);
        }
    }

    public function failed(Throwable $e): void
    {
        try {
            AdminNotifier::toRoles(
                ['super_admin', 'admin'],
                Notification::make()
                    ->title('Bulk stock update failed')
                    ->body($e->getMessage())
                    ->icon('heroicon-o-archive-box')
                    ->iconColor('danger')
            );
        } catch (Throwable $inner) {
            // A bell notification must never mask the original failure.
        }
    }
}

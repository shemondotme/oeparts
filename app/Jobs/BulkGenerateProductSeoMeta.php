<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\SeoMeta;
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
 * SeoMetaResource was edit-one-row-at-a-time with no bulk path at all — on
 * a catalog with 1M+ products, fixing a templated title pattern across even
 * a filtered few thousand SKUs meant opening each one individually. Queued
 * the same way RegenerateSitemap/PushIndexNow/ImportRedirectsFromCsv are:
 * a per-row DB write (SeoMeta::updateOrCreate) across potentially thousands
 * of selected products is enough to risk a web request's timeout, so the
 * bulk action just dispatches this and the admin gets a bell notification
 * when it finishes.
 *
 * Takes raw product IDs (not a Collection of models) so the job payload
 * stays light regardless of how many were selected — chunkById() re-fetches
 * them in bounded batches rather than holding the whole selection in memory
 * at once.
 */
class BulkGenerateProductSeoMeta implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    /**
     * @param  array<int, int>  $productIds
     */
    public function __construct(
        public array $productIds,
        public ?string $titleTemplate,
        public ?string $descriptionTemplate,
        public bool $overwriteExisting,
        public string $triggeredBy,
    ) {
        $this->onQueue('default');
    }

    public function handle(): void
    {
        $updated = 0;
        $skipped = 0;

        Product::query()
            ->whereIn('id', $this->productIds)
            ->with('manufacturer')
            ->chunkById(200, function ($products) use (&$updated, &$skipped) {
                $existingMeta = SeoMeta::where('metable_type', Product::class)
                    ->whereIn('metable_id', $products->pluck('id'))
                    ->get()
                    ->keyBy('metable_id');

                foreach ($products as $product) {
                    $existing = $existingMeta->get($product->id);
                    $hasCustomMeta = $existing && (filled($existing->meta_title) || filled($existing->meta_description));

                    if ($hasCustomMeta && ! $this->overwriteExisting) {
                        $skipped++;

                        continue;
                    }

                    $attributes = [];
                    if ($this->titleTemplate !== null) {
                        $attributes['meta_title'] = $this->render($this->titleTemplate, $product);
                    }
                    if ($this->descriptionTemplate !== null) {
                        $attributes['meta_description'] = $this->render($this->descriptionTemplate, $product);
                    }

                    if ($attributes === []) {
                        $skipped++;

                        continue;
                    }

                    SeoMeta::updateOrCreate(
                        ['metable_type' => Product::class, 'metable_id' => $product->id],
                        $attributes
                    );
                    $updated++;
                }
            });

        $this->notifyResult($updated, $skipped);
    }

    /**
     * Placeholders: {oem}, {brand}/{manufacturer}, {name}, {min}/{max}
     * (this product's own price — there's exactly one product per row here,
     * unlike the OEM-group hub-page templates this deliberately doesn't
     * share an implementation with), {site}. Same vocabulary as the
     * Control Center's live template preview, just applied for real
     * against every selected product instead of one static sample.
     */
    private function render(string $template, Product $product): string
    {
        $manufacturerName = $product->manufacturer ? trans_field($product->manufacturer->name) : '';
        $productName = trans_field($product->name) ?: $product->oem_number;

        return str_replace(
            ['{oem}', '{brand}', '{manufacturer}', '{name}', '{min}', '{max}', '{site}'],
            [
                $product->oem_number,
                $manufacturerName,
                $manufacturerName,
                $productName,
                (string) $product->price,
                (string) $product->price,
                settings('general.site_name', 'OeParts'),
            ],
            $template
        );
    }

    private function notifyResult(int $updated, int $skipped): void
    {
        try {
            AdminNotifier::toRoles(
                ['super_admin', 'admin'],
                Notification::make()
                    ->title('Bulk SEO meta generation finished')
                    ->body("Updated {$updated} product(s), skipped {$skipped}, requested by {$this->triggeredBy}.")
                    ->icon('heroicon-o-document-text')
                    ->iconColor('success')
            );
        } catch (Throwable $e) {
            // A bell notification must never fail a job that otherwise succeeded.
            Log::warning('Failed to send bulk SEO meta completion notification', ['error' => $e->getMessage()]);
        }
    }

    public function failed(Throwable $e): void
    {
        try {
            AdminNotifier::toRoles(
                ['super_admin', 'admin'],
                Notification::make()
                    ->title('Bulk SEO meta generation failed')
                    ->body($e->getMessage())
                    ->icon('heroicon-o-document-text')
                    ->iconColor('danger')
            );
        } catch (Throwable $inner) {
            // A bell notification must never mask the original failure.
        }
    }
}

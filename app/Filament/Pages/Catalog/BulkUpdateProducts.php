<?php

namespace App\Filament\Pages\Catalog;

use App\Filament\Support\AdminUi;
use App\Jobs\NotifyAdminsOfBulkProductUpdate;
use App\Models\BulkUpdateLog;
use App\Models\CarModel;
use App\Models\Condition;
use App\Models\Manufacturer;
use App\Models\Product;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Bulk Update Products — filter-driven price/stock/detail changes across an
 * arbitrary set of products (not limited to a manually checkbox-selected
 * page of rows, unlike ProductResource's row-selection bulk actions).
 *
 * Rebuild of a pre-Filament tool (App\Http\Controllers\Admin\BulkUpdateController,
 * deleted in bfdb83a during the Filament rewrite) — the `bulk update products`
 * permission and the BulkUpdateLog model/table survived that rewrite unused
 * until now. No Filament Schema/Form here on purpose, same precedent as
 * ProductImport.php: raw Livewire properties + wire:click/wire:model.live.
 *
 * Mutating methods carry their own explicit ->can() check (CLAUDE rule #31:
 * custom actions get no automatic policy enforcement). Changes are applied
 * via a per-record ->save() loop (not a single mass UPDATE) so
 * Product::booted()'s cache-invalidation hook fires for every changed row.
 *
 * Every applied change is logged to BulkUpdateLog with a per-row snapshot of
 * the PRIOR value (capped at self::MAX_SNAPSHOT_ROWS rows) so a single click
 * on BulkUpdateLogPage can revert it — see that page's `revert` action.
 */
class BulkUpdateProducts extends Page
{
    protected static ?string $title = 'Bulk Update Products';

    protected string $view = 'filament.pages.catalog.bulk-update-products';

    protected ?string $subheading = 'Preview before you commit — this changes live pricing, stock, and details across every matching product.';

    private const LARGE_BATCH_THRESHOLD = 500;

    private const MAX_SNAPSHOT_ROWS = 5000;

    private const PRICE_ACTIONS = ['price_increase', 'price_decrease', 'price_set'];

    private const STOCK_ACTIONS = ['stock_in', 'stock_out', 'mark_active', 'mark_inactive'];

    private const DETAIL_ACTIONS = ['condition_set', 'delivery_time_set', 'moq_set'];

    private const ACTION_LABELS = [
        'price_increase' => 'Increase Price by %',
        'price_decrease' => 'Decrease Price by %',
        'price_set' => 'Set Fixed Price',
        'condition_set' => 'Change Condition',
        'mark_active' => 'Mark Active',
        'mark_inactive' => 'Mark Inactive',
        'stock_in' => 'Mark In Stock',
        'stock_out' => 'Mark Out of Stock',
        'delivery_time_set' => 'Set Delivery Time',
        'moq_set' => 'Set Minimum Order Qty',
    ];

    // ---- Filters ----
    public ?int $manufacturerId = null;

    public ?int $conditionId = null;

    /** 'in' | 'out' | null (all) */
    public ?string $stockFilter = null;

    /** 'active' | 'inactive' | null (all) */
    public ?string $activeFilter = null;

    public ?int $carModelId = null;

    public ?string $priceMin = null;

    public ?string $priceMax = null;

    public ?string $oemSearch = null;

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    // ---- Action ----
    public ?string $actionType = null;

    public ?string $percentage = null;

    public ?string $fixedPrice = null;

    public ?int $newConditionId = null;

    public ?string $newDeliveryTime = null;

    public ?string $newMoq = null;

    public bool $confirmed = false;

    public bool $largeBatchAck = false;

    /** @var array{count:int,samples:array<int,array<string,mixed>>}|null */
    public ?array $preview = null;

    /** @var array{count:int,action:string}|null */
    public ?array $result = null;

    public function updatedManufacturerId(): void
    {
        $this->resetPreview();
    }

    public function updatedConditionId(): void
    {
        $this->resetPreview();
    }

    public function updatedStockFilter(): void
    {
        $this->resetPreview();
    }

    public function updatedActiveFilter(): void
    {
        $this->resetPreview();
    }

    public function updatedCarModelId(): void
    {
        $this->resetPreview();
    }

    public function updatedPriceMin(): void
    {
        $this->resetPreview();
    }

    public function updatedPriceMax(): void
    {
        $this->resetPreview();
    }

    public function updatedOemSearch(): void
    {
        $this->resetPreview();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPreview();
    }

    public function updatedDateTo(): void
    {
        $this->resetPreview();
    }

    public function updatedActionType(): void
    {
        $this->resetPreview();
    }

    public function updatedPercentage(): void
    {
        $this->resetPreview();
    }

    public function updatedFixedPrice(): void
    {
        $this->resetPreview();
    }

    public function updatedNewConditionId(): void
    {
        $this->resetPreview();
    }

    public function updatedNewDeliveryTime(): void
    {
        $this->resetPreview();
    }

    public function updatedNewMoq(): void
    {
        $this->resetPreview();
    }

    private function resetPreview(): void
    {
        $this->preview = null;
        $this->result = null;
        $this->confirmed = false;
        $this->largeBatchAck = false;
    }

    private function actionPermission(?string $action): string
    {
        return match (true) {
            in_array($action, self::PRICE_ACTIONS, true) => 'bulk update product prices',
            in_array($action, self::STOCK_ACTIONS, true) => 'bulk update product stock',
            in_array($action, self::DETAIL_ACTIONS, true) => 'bulk update product details',
            default => 'bulk update products',
        };
    }

    /** @return array<string,string> Only actions the current admin is allowed to run. */
    public function availableActions(): array
    {
        $user = auth('admin')->user();

        return collect(self::ACTION_LABELS)
            ->filter(fn (string $label, string $action) => $user?->can($this->actionPermission($action)) || $user?->can('bulk update products'))
            ->all();
    }

    /** @return array<int,string> */
    public function manufacturerOptions(): array
    {
        return Manufacturer::query()
            ->orderBy('slug')
            ->get()
            ->mapWithKeys(fn (Manufacturer $m) => [$m->id => AdminUi::localizedName($m->name)])
            ->all();
    }

    /** @return array<int,string> */
    public function conditionOptions(): array
    {
        return Condition::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /** @return array<int,string> */
    public function carModelOptions(): array
    {
        return CarModel::query()->orderBy('slug')->pluck('name', 'id')->all();
    }

    private function matchingQuery(): Builder
    {
        return Product::query()
            ->when($this->manufacturerId, fn (Builder $q) => $q->where('manufacturer_id', $this->manufacturerId))
            ->when($this->conditionId, fn (Builder $q) => $q->where('condition_id', $this->conditionId))
            ->when($this->stockFilter === 'in', fn (Builder $q) => $q->where('is_in_stock', true))
            ->when($this->stockFilter === 'out', fn (Builder $q) => $q->where('is_in_stock', false))
            ->when($this->activeFilter === 'active', fn (Builder $q) => $q->where('is_active', true))
            ->when($this->activeFilter === 'inactive', fn (Builder $q) => $q->where('is_active', false))
            ->when($this->carModelId, fn (Builder $q) => $q->whereHas(
                'carModels',
                fn (Builder $cm) => $cm->where('car_models.id', $this->carModelId)
            ))
            ->when(filled($this->priceMin), fn (Builder $q) => $q->where('price', '>=', $this->priceMin))
            ->when(filled($this->priceMax), fn (Builder $q) => $q->where('price', '<=', $this->priceMax))
            ->when(filled($this->oemSearch), fn (Builder $q) => $q->where('oem_number', 'like', '%'.$this->oemSearch.'%'))
            ->when(filled($this->dateFrom), fn (Builder $q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when(filled($this->dateTo), fn (Builder $q) => $q->whereDate('created_at', '<=', $this->dateTo));
    }

    private function validateAction(): void
    {
        $this->validate([
            'actionType' => 'required|in:'.implode(',', array_keys(self::ACTION_LABELS)),
            'percentage' => 'required_if:actionType,price_increase,price_decrease|nullable|numeric|min:0.01|max:100',
            'fixedPrice' => 'required_if:actionType,price_set|nullable|numeric|min:0|max:999999.99',
            'newConditionId' => 'required_if:actionType,condition_set|nullable|exists:conditions,id',
            'newDeliveryTime' => 'required_if:actionType,delivery_time_set|nullable|string|max:50',
            'newMoq' => 'required_if:actionType,moq_set|nullable|integer|min:1',
        ]);
    }

    public function runPreview(): void
    {
        abort_unless(auth('admin')->user()?->can($this->actionPermission($this->actionType)), 403);

        $this->validateAction();

        $query = $this->matchingQuery();
        $count = (clone $query)->count();

        if ($count === 0) {
            $this->preview = ['count' => 0, 'samples' => []];
            $this->confirmed = false;

            return;
        }

        $samples = (clone $query)->with(['manufacturer', 'condition'])->limit(10)->get()
            ->map(function (Product $p): array {
                $change = $this->previewChange($p);

                return [
                    'oem_number' => $p->oem_number,
                    'manufacturer' => AdminUi::localizedName($p->manufacturer?->name),
                    'old' => $this->formatFieldValue($change['field'], $change['old']),
                    'new' => $this->formatFieldValue($change['field'], $change['new']),
                ];
            })
            ->all();

        $this->preview = ['count' => $count, 'samples' => $samples];
        $this->confirmed = false;
        $this->largeBatchAck = false;
    }

    /** @return array{field:?string,old:mixed,new:mixed} */
    private function previewChange(Product $p): array
    {
        return match ($this->actionType) {
            'price_increase' => ['field' => 'price', 'old' => (string) $p->price, 'new' => $this->computePrice((string) $p->price, true)],
            'price_decrease' => ['field' => 'price', 'old' => (string) $p->price, 'new' => $this->computePrice((string) $p->price, false)],
            'price_set' => ['field' => 'price', 'old' => (string) $p->price, 'new' => number_format((float) $this->fixedPrice, 2, '.', '')],
            'condition_set' => ['field' => 'condition', 'old' => $p->condition?->name, 'new' => $this->conditionOptions()[$this->newConditionId] ?? null],
            'mark_active' => ['field' => 'active', 'old' => $p->is_active, 'new' => true],
            'mark_inactive' => ['field' => 'active', 'old' => $p->is_active, 'new' => false],
            'stock_in' => ['field' => 'stock', 'old' => $p->is_in_stock, 'new' => true],
            'stock_out' => ['field' => 'stock', 'old' => $p->is_in_stock, 'new' => false],
            'delivery_time_set' => ['field' => 'delivery_time', 'old' => $p->delivery_time, 'new' => $this->newDeliveryTime],
            'moq_set' => ['field' => 'moq', 'old' => $p->moq, 'new' => (int) $this->newMoq],
            default => ['field' => null, 'old' => null, 'new' => null],
        };
    }

    private function formatFieldValue(?string $field, mixed $value): string
    {
        return match ($field) {
            'price' => number_format((float) $value, 2),
            'active' => $value ? 'Active' : 'Inactive',
            'stock' => $value ? 'In Stock' : 'Out of Stock',
            'condition' => (string) ($value ?? '—'),
            'delivery_time' => filled($value) ? (string) $value : '—',
            'moq' => (string) $value,
            default => '—',
        };
    }

    private function computePrice(string $price, bool $increase): string
    {
        $delta = bcmul($price, bcdiv((string) $this->percentage, '100', 4), 2);

        return $increase ? bcadd($price, $delta, 2) : bcsub($price, $delta, 2);
    }

    public function downloadCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        abort_unless(auth('admin')->user()?->can($this->actionPermission($this->actionType)), 403);

        $this->validateAction();

        $query = $this->matchingQuery()->with(['manufacturer', 'condition']);

        return response()->streamDownload(function () use ($query): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['oem_number', 'manufacturer', 'field', 'old_value', 'new_value']);

            $query->chunk(500, function ($products) use ($out): void {
                foreach ($products as $product) {
                    $change = $this->previewChange($product);
                    fputcsv($out, [
                        $product->oem_number,
                        AdminUi::localizedName($product->manufacturer?->name),
                        (string) $change['field'],
                        $this->formatFieldValue($change['field'], $change['old']),
                        $this->formatFieldValue($change['field'], $change['new']),
                    ]);
                }
            });

            fclose($out);
        }, 'bulk-update-preview-'.now()->format('Y-m-d-His').'.csv');
    }

    public function apply(): void
    {
        abort_unless(auth('admin')->user()?->can($this->actionPermission($this->actionType)), 403);

        $this->validateAction();

        if (! $this->preview) {
            Notification::make()->title('Run a preview first')->warning()->send();

            return;
        }

        if (! $this->confirmed) {
            Notification::make()->title('Please confirm the preview before applying')->warning()->send();

            return;
        }

        if ($this->preview['count'] > self::LARGE_BATCH_THRESHOLD && ! $this->largeBatchAck) {
            Notification::make()->title('Please acknowledge the large-batch warning before applying')->warning()->send();

            return;
        }

        $records = $this->matchingQuery()->get();

        if ($records->isEmpty()) {
            Notification::make()->title('No matching products')->warning()->send();
            $this->resetPreview();

            return;
        }

        $count = 0;
        $snapshot = [];
        $snapshotTruncated = false;

        DB::transaction(function () use ($records, &$count, &$snapshot, &$snapshotTruncated): void {
            foreach ($records as $record) {
                $changed = $this->applyToRecord($record);

                if ($changed === null) {
                    continue;
                }

                $count++;

                if (count($snapshot) < self::MAX_SNAPSHOT_ROWS) {
                    $snapshot[] = ['id' => $record->id, ...$changed];
                } else {
                    $snapshotTruncated = true;
                }
            }

            BulkUpdateLog::create([
                'admin_id' => auth('admin')->id(),
                'action_type' => $this->actionType,
                'entity_type' => Product::class,
                'target_manufacturer_id' => $this->manufacturerId,
                'affected_rows_count' => $count,
                'payload' => [
                    'snapshot' => $snapshot,
                    'snapshot_truncated' => $snapshotTruncated,
                ],
                'filters' => [
                    'manufacturer_id' => $this->manufacturerId,
                    'condition_id' => $this->conditionId,
                    'stock_filter' => $this->stockFilter,
                    'active_filter' => $this->activeFilter,
                    'car_model_id' => $this->carModelId,
                    'price_min' => $this->priceMin,
                    'price_max' => $this->priceMax,
                    'oem_search' => $this->oemSearch,
                    'date_from' => $this->dateFrom,
                    'date_to' => $this->dateTo,
                ],
                'updates' => [
                    'action' => $this->actionType,
                    'percentage' => $this->percentage,
                    'fixed_price' => $this->fixedPrice,
                    'new_condition_id' => $this->newConditionId,
                    'new_delivery_time' => $this->newDeliveryTime,
                    'new_moq' => $this->newMoq,
                ],
                'ip_address' => request()->ip(),
                'user_agent' => (string) request()->userAgent(),
                'created_at' => now(),
            ]);
        });

        if ($count > self::LARGE_BATCH_THRESHOLD) {
            NotifyAdminsOfBulkProductUpdate::dispatch(
                adminName: auth('admin')->user()?->name ?? 'An admin',
                actionLabel: self::ACTION_LABELS[$this->actionType] ?? (string) $this->actionType,
                affectedCount: $count,
            );
        }

        $this->result = ['count' => $count, 'action' => (string) $this->actionType];
        $this->preview = null;
        $this->confirmed = false;
        $this->largeBatchAck = false;

        Notification::make()
            ->title("{$count} products updated")
            ->success()
            ->send();
    }

    /** @return array{field:string,old:mixed}|null Null when the record already matches the target value (no-op). */
    private function applyToRecord(Product $record): ?array
    {
        return match ($this->actionType) {
            'price_increase' => $this->mutatePrice($record, true),
            'price_decrease' => $this->mutatePrice($record, false),
            'price_set' => $this->mutatePriceSet($record),
            'condition_set' => $this->mutateField($record, 'condition_id', $this->newConditionId),
            'mark_active' => $this->mutateField($record, 'is_active', true),
            'mark_inactive' => $this->mutateField($record, 'is_active', false),
            'stock_in' => $this->mutateField($record, 'is_in_stock', true),
            'stock_out' => $this->mutateField($record, 'is_in_stock', false),
            'delivery_time_set' => $this->mutateField($record, 'delivery_time', $this->newDeliveryTime),
            'moq_set' => $this->mutateField($record, 'moq', (int) $this->newMoq),
            default => null,
        };
    }

    private function mutateField(Product $record, string $field, mixed $value): ?array
    {
        if ($record->{$field} === $value) {
            return null;
        }

        $old = $record->{$field};
        $record->{$field} = $value;
        $record->save();

        return ['field' => $field, 'old' => $old];
    }

    private function mutatePrice(Product $record, bool $increase): ?array
    {
        $old = (string) $record->price;
        $new = $this->computePrice($old, $increase);

        if (bccomp($old, $new, 2) === 0) {
            return null;
        }

        $record->price = $new;
        $record->save();

        return ['field' => 'price', 'old' => $old];
    }

    private function mutatePriceSet(Product $record): ?array
    {
        $old = (string) $record->price;
        $new = number_format((float) $this->fixedPrice, 2, '.', '');

        if (bccomp($old, $new, 2) === 0) {
            return null;
        }

        $record->price = $new;
        $record->save();

        return ['field' => 'price', 'old' => $old];
    }

    public function resetFilters(): void
    {
        $this->manufacturerId = null;
        $this->conditionId = null;
        $this->stockFilter = null;
        $this->activeFilter = null;
        $this->carModelId = null;
        $this->priceMin = null;
        $this->priceMax = null;
        $this->oemSearch = null;
        $this->dateFrom = null;
        $this->dateTo = null;
        $this->actionType = null;
        $this->percentage = null;
        $this->fixedPrice = null;
        $this->newConditionId = null;
        $this->newDeliveryTime = null;
        $this->newMoq = null;
        $this->resetPreview();
        $this->result = null;
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Catalog';
    }

    public static function getNavigationSort(): ?int
    {
        return 49; // just above Import Products (50) and Bulk Update Log (51)
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-adjustments-horizontal';
    }

    public static function getNavigationLabel(): string
    {
        return 'Bulk Update Products';
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        if (! $user) {
            return false;
        }

        return $user->can('bulk update products')
            || $user->can('bulk update product prices')
            || $user->can('bulk update product stock')
            || $user->can('bulk update product details');
    }
}

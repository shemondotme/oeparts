<?php

namespace App\Filament\Pages\Catalog;

use App\Enums\BulkUpdateAction;
use App\Models\BulkUpdateLog;
use App\Models\Product;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Support\Facades\DB;

class BulkUpdateLogPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $title = 'Bulk Update Log';

    protected string $view = 'filament.pages.catalog.bulk-update-log';

    public static function getNavigationGroup(): ?string
    {
        return 'Catalog';
    }

    public static function getNavigationSort(): ?int
    {
        return 51;
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-document-check';
    }

    public static function getNavigationLabel(): string
    {
        return 'Bulk Update Log';
    }

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    private const ACTION_LABELS = [
        'price_increase' => 'Price ↑',
        'price_decrease' => 'Price ↓',
        'price_set' => 'Price Set',
        'condition_set' => 'Condition Change',
        'mark_active' => 'Marked Active',
        'mark_inactive' => 'Marked Inactive',
        'stock_in' => 'Stock In',
        'stock_out' => 'Stock Out',
        'delivery_time_set' => 'Delivery Time',
        'moq_set' => 'MOQ Set',
        'import' => 'Import',
        'revert' => 'Reverted',
    ];

    private const ACTION_COLORS = [
        'price_increase' => 'success',
        'price_decrease' => 'danger',
        'price_set' => 'warning',
        'condition_set' => 'info',
        'mark_active' => 'success',
        'mark_inactive' => 'gray',
        'stock_in' => 'info',
        'stock_out' => 'warning',
        'delivery_time_set' => 'info',
        'moq_set' => 'info',
        'import' => 'primary',
        'revert' => 'gray',
    ];

    /**
     * action_type is cast to the BulkUpdateAction enum on the model, so a raw
     * column value read through Filament's column state resolution is a
     * BulkUpdateAction instance, not a string — match()'ing it against string
     * literals directly never matches (strict comparison) and previously fell
     * through to ucfirst($enumInstance), which throws a TypeError the moment
     * any row exists. Unwrap ->value first.
     */
    private static function actionTypeValue(mixed $state): ?string
    {
        return $state instanceof BulkUpdateAction ? $state->value : $state;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                BulkUpdateLog::query()
                    ->with(['admin', 'targetManufacturer'])
                    ->orderByDesc('created_at')
            )
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('When')
                    ->since()
                    ->sortable()
                    ->fontMono()
                    ->size('sm'),

                Tables\Columns\TextColumn::make('admin.name')
                    ->label('By')
                    ->searchable()
                    ->sortable()
                    ->size('sm'),

                Tables\Columns\TextColumn::make('action_type')
                    ->label('Action')
                    ->badge()
                    ->formatStateUsing(function ($state): string {
                        $value = static::actionTypeValue($state);

                        return self::ACTION_LABELS[$value] ?? ($value ? ucfirst($value) : '—');
                    })
                    ->color(function ($state): string {
                        $value = static::actionTypeValue($state);

                        return self::ACTION_COLORS[$value] ?? 'gray';
                    })
                    ->size('sm'),

                Tables\Columns\TextColumn::make('entity_type')
                    ->label('Entity')
                    ->formatStateUsing(fn ($state): string => class_basename($state ?? ''))
                    ->searchable()
                    ->size('sm'),

                Tables\Columns\TextColumn::make('targetManufacturer.name')
                    ->label('Manufacturer')
                    ->searchable()
                    ->default('—')
                    ->size('sm'),

                Tables\Columns\TextColumn::make('affected_rows_count')
                    ->label('Affected')
                    ->sortable()
                    ->fontMono()
                    ->size('sm'),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->fontMono()
                    ->size('sm'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->poll('60s')
            ->filters([
                Tables\Filters\SelectFilter::make('action_type')
                    ->label('Action Type')
                    ->options([
                        'price_increase' => 'Price Increase',
                        'price_decrease' => 'Price Decrease',
                        'price_set' => 'Price Set',
                        'condition_set' => 'Condition Change',
                        'mark_active' => 'Marked Active',
                        'mark_inactive' => 'Marked Inactive',
                        'stock_in' => 'Stock In',
                        'stock_out' => 'Stock Out',
                        'delivery_time_set' => 'Delivery Time Change',
                        'moq_set' => 'MOQ Change',
                        'import' => 'Import',
                        'revert' => 'Reverted',
                    ])
                    ->multiple(),
                Tables\Filters\SelectFilter::make('admin_id')
                    ->label('Admin')
                    ->options(fn () => \App\Models\Admin::pluck('name', 'id'))
                    ->searchable(),
                Tables\Filters\Filter::make('created_at')
                    ->label('Date Range')
                    ->form([
                        Select::make('created_at')
                            ->options([
                                'today' => 'Today',
                                'yesterday' => 'Yesterday',
                                'week' => 'This Week',
                                'month' => 'This Month',
                                'quarter' => 'This Quarter',
                            ])
                            ->placeholder('All Time'),
                    ])
                    ->query(function ($query, array $data): void {
                        if (empty($data['created_at'])) {
                            return;
                        }

                        $query->whereDate('created_at', match ($data['created_at']) {
                            'today' => now()->toDateString(),
                            'yesterday' => now()->subDay()->toDateString(),
                            'week' => now()->startOfWeek()->toDateString(),
                            'month' => now()->startOfMonth()->toDateString(),
                            'quarter' => now()->startOfQuarter()->toDateString(),
                            default => now()->toDateString(),
                        });
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('viewDetails')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading('Bulk Update Details')
                    ->modalContent(function ($record) {
                        return view('filament.pages.catalog.bulk-update-detail', ['record' => $record]);
                    })
                    ->modalSubmitAction(false),
                Tables\Actions\Action::make('revert')
                    ->label('Revert')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Revert this bulk update?')
                    ->modalDescription(fn (BulkUpdateLog $record): string => 'This restores the previous value for '.number_format((int) $record->affected_rows_count).' product(s). Reverting itself creates a new, separate log entry — it cannot be undone automatically.')
                    ->modalSubmitActionLabel('Yes, revert')
                    ->visible(fn (BulkUpdateLog $record): bool => static::canRevert($record))
                    ->action(function (BulkUpdateLog $record): void {
                        abort_unless(static::canManageBulkUpdates(), 403);
                        abort_unless(static::canRevert($record), 403);

                        $snapshot = $record->payload['snapshot'] ?? [];
                        $count = 0;

                        DB::transaction(function () use ($snapshot, &$count, $record): void {
                            foreach ($snapshot as $entry) {
                                $product = Product::find($entry['id'] ?? null);

                                if (! $product || ! isset($entry['field'])) {
                                    continue;
                                }

                                $product->{$entry['field']} = $entry['old'];
                                $product->save();
                                $count++;
                            }

                            $payload = $record->payload ?? [];
                            $payload['reverted_at'] = now()->toIso8601String();
                            $payload['reverted_by_admin_id'] = auth('admin')->id();
                            $record->payload = $payload;
                            $record->save();

                            BulkUpdateLog::create([
                                'admin_id' => auth('admin')->id(),
                                'action_type' => 'revert',
                                'entity_type' => Product::class,
                                'affected_rows_count' => $count,
                                'payload' => ['reverted_log_id' => $record->id],
                                'filters' => null,
                                'updates' => ['note' => 'Revert of bulk update log #'.$record->id],
                                'ip_address' => request()->ip(),
                                'user_agent' => (string) request()->userAgent(),
                                'created_at' => now(),
                            ]);
                        });

                        Notification::make()->title("{$count} product(s) reverted")->success()->send();
                    }),
            ]);
    }

    private static function canManageBulkUpdates(): bool
    {
        $user = auth('admin')->user();

        return (bool) ($user?->can('bulk update products')
            || $user?->can('bulk update product prices')
            || $user?->can('bulk update product stock')
            || $user?->can('bulk update product details'));
    }

    private static function canRevert(BulkUpdateLog $record): bool
    {
        if (! static::canManageBulkUpdates()) {
            return false;
        }

        if ($record->entity_type !== Product::class) {
            return false;
        }

        if (in_array(static::actionTypeValue($record->getRawOriginal('action_type')), ['import', 'revert'], true)) {
            return false;
        }

        $payload = $record->payload ?? [];

        if (empty($payload['snapshot'])) {
            return false;
        }

        if (! empty($payload['snapshot_truncated'])) {
            return false;
        }

        if (! empty($payload['reverted_at'])) {
            return false;
        }

        return true;
    }
}

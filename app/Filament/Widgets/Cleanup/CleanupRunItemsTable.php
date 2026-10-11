<?php

namespace App\Filament\Widgets\Cleanup;

use App\Filament\Pages\System\CleanupDashboard;
use App\Models\CleanupRun;
use App\Models\CleanupRunItem;
use App\Services\Cleanup\CleanupText;
use App\Services\Cleanup\TargetRegistry;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** The per-target result of one cleanup or preview, as a stock table. Embedded by the Cleanup page. */
class CleanupRunItemsTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public int $runId = 0;

    public static function canView(): bool
    {
        return CleanupDashboard::canAccess();
    }

    public function table(Table $table): Table
    {
        $dry = (bool) CleanupRun::query()->find($this->runId)?->isDryRun();
        $labels = collect(app(TargetRegistry::class)->all())->map->label();

        return $table
            ->query(CleanupRunItem::query()->where('run_id', $this->runId)->orderBy('id'))
            ->heading('Result per item')
            ->paginated(false)
            ->columns([
                TextColumn::make('target')
                    ->label('What')
                    ->formatStateUsing(fn (string $state): string => $labels[$state] ?? $state)
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (CleanupRunItem $record): ?string => ($reasons = array_map([CleanupText::class, 'reason'], (array) ($record->reasons ?? []))) === [] ? null : implode(' ', $reasons))
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Result')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => $dry && $state === 'success' ? 'Counted' : CleanupText::status($state))
                    ->color(fn (string $state): string => CleanupText::color($state, $dry)),
                TextColumn::make('targeted')
                    ->label('Found')
                    ->numeric(),
                TextColumn::make('deleted')
                    ->label($dry ? 'Would remove' : 'Removed')
                    ->state(fn (CleanupRunItem $record): int => $dry ? (int) $record->targeted : (int) $record->deleted)
                    ->numeric(),
                TextColumn::make('external_gone')
                    ->label('Gone elsewhere')
                    ->tooltip('Removed by something else while this ran')
                    ->numeric()
                    ->visible(! $dry),
                TextColumn::make('remaining')
                    ->label('Left')
                    ->numeric()
                    ->visible(! $dry),
                TextColumn::make('freed')
                    ->label('Freed')
                    ->state(fn (CleanupRunItem $record): string => CleanupText::bytes($record->bytes_freed_allocated ?? $record->bytes_freed_apparent))
                    ->tooltip(fn (CleanupRunItem $record): ?string => $record->fs_free_before !== null && $record->fs_free_after !== null
                        ? 'Disk free before/after: '.CleanupText::bytes($record->fs_free_before).' → '.CleanupText::bytes($record->fs_free_after)
                        : null)
                    ->visible(! $dry),
            ]);
    }
}

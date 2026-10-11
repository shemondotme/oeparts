<?php

namespace App\Filament\Widgets\Cleanup;

use App\Filament\Pages\System\CleanupDashboard;
use App\Models\CleanupRun;
use App\Services\Cleanup\CleanupText;
use App\Services\Cleanup\TargetRegistry;
use Filament\Actions\Action;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/** Every preview and cleanup, whether an admin started it or the schedule did. "Details" opens the run on the page. */
class CleanupHistoryTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return CleanupDashboard::canAccess();
    }

    public function table(Table $table): Table
    {
        $labels = collect(app(TargetRegistry::class)->all())->map->label();
        $days = (int) settings('cleanup.history_retention_days', config('cleanup.history_retention_days', 180));

        return $table
            ->query(CleanupRun::query()->with('admin:id,name')->withCount('items')->orderByDesc('id'))
            ->heading('Cleanup history')
            ->description("Every preview and cleanup, whether you started it or the schedule did. Kept for {$days} days (set in Settings).")
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('id')
                    ->label('Run')
                    ->prefix('#')
                    ->fontFamily(FontFamily::Mono),
                TextColumn::make('started_at')
                    ->label('When')
                    ->state(fn (CleanupRun $record) => ($record->started_at ?? $record->created_at)->diffForHumans())
                    ->tooltip(fn (CleanupRun $record): ?string => $record->started_at?->toDateTimeString()),
                TextColumn::make('trigger')
                    ->label('Started by')
                    ->state(fn (CleanupRun $record): string => $record->trigger === 'manual' ? ($record->admin->name ?? 'An admin') : ($record->trigger === 'scheduled' ? 'Schedule' : 'Resume')),
                TextColumn::make('target')
                    ->label('What')
                    ->state(fn (CleanupRun $record): string => $record->target ? ($labels[$record->target] ?? $record->target) : $record->items_count.' targets')
                    ->badge(false)
                    ->description(fn (CleanupRun $record): ?string => $record->isDryRun() ? 'preview' : null)
                    ->wrap(),
                TextColumn::make('status')
                    ->label('Result')
                    ->badge()
                    ->formatStateUsing(fn (string $state, CleanupRun $record): string => CleanupText::status($state, $record->isDryRun()))
                    ->color(fn (string $state, CleanupRun $record): string => CleanupText::color($state, $record->isDryRun())),
                TextColumn::make('deleted')
                    ->label('Removed')
                    ->state(fn (CleanupRun $record): string => $record->isDryRun() ? '—' : CleanupText::number($record->deleted))
                    ->alignEnd(),
                TextColumn::make('bytes_freed')
                    ->label('Freed')
                    ->state(fn (CleanupRun $record): string => $record->isDryRun() ? '—' : CleanupText::bytes($record->bytes_freed))
                    ->alignEnd(),
            ])
            ->recordActions([
                Action::make('details')
                    ->label('Details')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->action(function (CleanupRun $record): void {
                        abort_unless(CleanupDashboard::canAccess(), 403);
                        $this->dispatch('cleanup-show-run', id: $record->getKey());
                    }),
            ])
            ->emptyStateHeading('Nothing has been run yet')
            ->emptyStateIcon('heroicon-o-clock');
    }
}

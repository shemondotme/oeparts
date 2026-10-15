<?php

namespace App\Filament\Widgets\Migration;

use App\Filament\Pages\System\MigrationDashboard;
use App\Security\PackageAudit;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Support\Carbon;

/**
 * Everything the migration feature did on this site, newest first, read from the audit file (history.ndjson). The file lives
 * outside the database on purpose: it survives the swap an import makes and it is never part of a package. Nothing in it is a
 * secret (the writer drops key-like fields and cuts addresses down to their host).
 */
class MigrationHistoryTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    private const SHOWN = 300;

    public static function canView(): bool
    {
        return MigrationDashboard::canAccess();
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->records())
            ->heading(__('migration.ui.history.heading'))
            ->description(__('migration.ui.history.description', ['shown' => self::SHOWN]))
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('ts')->label(__('migration.ui.history.col_when'))
                    ->state(fn (array $record): string => Carbon::parse($record['ts'])->diffForHumans())
                    ->tooltip(fn (array $record): string => Carbon::parse($record['ts'])->toDateTimeString()),
                TextColumn::make('event')->label(__('migration.ui.history.col_what'))->badge()
                    ->state(fn (array $record): string => $this->eventLabel($record['event']))
                    ->color(fn (array $record): string => str_contains($record['event'], 'failed') || str_contains($record['event'], 'denied') ? 'danger' : (str_contains($record['event'], 'completed') ? 'success' : 'gray')),
                TextColumn::make('run_id')->label(__('migration.ui.history.col_run'))->fontFamily(FontFamily::Mono)->placeholder('—'),
                TextColumn::make('detail')->label(__('migration.ui.history.col_details'))->wrap()->state(fn (array $record): string => $record['detail']),
            ])
            ->emptyStateHeading(__('migration.ui.history.empty_heading'))
            ->emptyStateIcon('heroicon-o-clock');
    }

    private function eventLabel(string $event): string
    {
        // "export.started" becomes the key "export_started": a dot inside a language key can never be found by dot notation
        $key = 'migration.ui.history.event.'.str_replace('.', '_', $event);
        $label = __($key);

        return $label === $key ? $event : $label;
    }

    /** @return array<int|string, array<string, mixed>> */
    private function records(): array
    {
        $rows = [];

        foreach (array_slice(array_reverse(app(PackageAudit::class)->history()), 0, self::SHOWN) as $i => $entry) {
            $rest = array_diff_key($entry, ['ts' => 1, 'event' => 1, 'run_id' => 1]);
            $rows[(string) $i] = [
                'ts' => (string) ($entry['ts'] ?? now()->toIso8601String()),
                'event' => (string) ($entry['event'] ?? 'unknown'),
                'run_id' => $entry['run_id'] ?? null,
                'detail' => implode(', ', array_map(static fn ($k, $v): string => $k.': '.(is_scalar($v) || $v === null ? (string) $v : json_encode($v)), array_keys($rest), $rest)),
            ];
        }

        return $rows;
    }
}

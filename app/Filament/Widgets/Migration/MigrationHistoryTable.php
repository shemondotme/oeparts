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

    private const LABELS = [
        'export.started' => 'Export started', 'export.completed' => 'Export finished', 'export.failed' => 'Export failed',
        'export.unfrozen' => 'Shop reopened', 'import.started' => 'Import started', 'import.completed' => 'Import finished',
        'import.failed' => 'Import failed', 'import.rolled_back' => 'Import taken back', 'package.downloaded' => 'Package downloaded',
        'package.deleted' => 'Package deleted', 'share.created' => 'Link made', 'share.claimed' => 'Link used',
        'share.consumed' => 'Link used up', 'share.revoked' => 'Link revoked', 'share.denied' => 'Link refused',
    ];

    public static function canView(): bool
    {
        return MigrationDashboard::canAccess();
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->records())
            ->heading('What happened')
            ->description('The last '.self::SHOWN.' events, newest first. This record is kept in a file, so it is still here after an import has replaced the database.')
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('ts')->label('When')
                    ->state(fn (array $record): string => Carbon::parse($record['ts'])->diffForHumans())
                    ->tooltip(fn (array $record): string => Carbon::parse($record['ts'])->toDateTimeString()),
                TextColumn::make('event')->label('What')->badge()
                    ->state(fn (array $record): string => self::LABELS[$record['event']] ?? $record['event'])
                    ->color(fn (array $record): string => str_contains($record['event'], 'failed') || str_contains($record['event'], 'denied') ? 'danger' : (str_contains($record['event'], 'completed') ? 'success' : 'gray')),
                TextColumn::make('run_id')->label('Run')->fontFamily(FontFamily::Mono)->placeholder('—'),
                TextColumn::make('detail')->label('Details')->wrap()->state(fn (array $record): string => $record['detail']),
            ])
            ->emptyStateHeading('Nothing has happened yet')
            ->emptyStateIcon('heroicon-o-clock');
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

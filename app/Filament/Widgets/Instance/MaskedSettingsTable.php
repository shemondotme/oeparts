<?php

namespace App\Filament\Widgets\Instance;

use App\Filament\Pages\System\InstanceReadiness;
use App\Services\Instance\SafeMode;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * The settings a testing or quarantined copy reads as switched off (tracking ids, search-engine pings, scheduled backups and
 * automatic cleaning, the card gateway's live mode, and so on), so an admin who sees a setting that "does not stick" knows why.
 * The stored values are never changed: on the real shop this list is empty, and going live brings the real values back at once.
 * It names settings and the value the copy sees instead; it never shows a stored value.
 */
class MaskedSettingsTable extends TableWidget
{
    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return InstanceReadiness::canAccess();
    }

    public function table(Table $table): Table
    {
        return $table
            ->records(fn (): array => $this->records())
            ->heading(__('instance.safe.masked_heading'))
            ->description(__('instance.safe.masked_description'))
            ->paginated(false)
            ->columns([
                TextColumn::make('group')->label(__('instance.safe.col_group'))->badge()->color('gray')
                    ->state(fn (array $record): string => $record['group']),
                TextColumn::make('setting')->label(__('instance.safe.col_setting'))->fontFamily(FontFamily::Mono)->wrap()
                    ->state(fn (array $record): string => $record['key']),
                TextColumn::make('shows')->label(__('instance.safe.col_shows'))
                    ->state(fn (array $record): string => $this->shown($record['shows'])),
            ])
            ->emptyStateHeading(__('instance.safe.masked_empty'))
            ->emptyStateIcon('heroicon-o-eye');
    }

    private function shown(mixed $value): string
    {
        return match (true) {
            $value === false => __('instance.safe.shows_off'),
            $value === true => __('instance.safe.shows_on'),
            $value === '' || $value === null => __('instance.safe.shows_empty'),
            default => (string) $value,
        };
    }

    /** @return array<string, array{group: string, key: string, shows: mixed}> */
    private function records(): array
    {
        $rows = [];

        foreach (app(SafeMode::class)->activeOverrides() as $override) {
            $rows[$override['group'].'.'.$override['key']] = $override;
        }

        return $rows;
    }
}

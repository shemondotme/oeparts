<?php

namespace App\Filament\Pages\Settings;

use App\Models\ActivityLog;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Illuminate\Database\Eloquent\Builder;

class SettingsActivityLog extends Page
{
    use InteractsWithTable;

    protected static ?string $cluster = \App\Filament\Clusters\Settings::class;

    protected static ?string $title = 'Settings Activity Log';

    protected string $view = 'filament.pages.settings.activity-log';

    public static function getNavigationLabel(): string
    {
        return 'Activity Log';
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-clock';
    }

    public static function getNavigationSort(): ?int
    {
        return 99;
    }

    public static function canAccess(): bool
    {
        $user = auth('admin')->user();

        return $user->hasRole(['super_admin', 'admin']);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ActivityLog::query()
                    ->where('action', 'settings_updated')
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

                Tables\Columns\TextColumn::make('old_values')
                    ->label('Changed')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', array_keys($state)) : '—')
                    ->wrap()
                    ->size('sm'),

                Tables\Columns\TextColumn::make('new_values')
                    ->label('New Values')
                    ->formatStateUsing(function ($state) {
                        if (! is_array($state)) {
                            return '—';
                        }

                        return collect($state)
                            ->take(3)
                            ->map(fn ($v, $k) => $k . ': ' . (is_array($v) ? json_encode($v) : $v))
                            ->implode(', ') . (count($state) > 3 ? ' +' . (count($state) - 3) . ' more' : '');
                    })
                    ->wrap()
                    ->limit(80)
                    ->size('sm'),

                Tables\Columns\TextColumn::make('ip_address')
                    ->label('IP')
                    ->fontMono()
                    ->size('sm'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([25, 50, 100])
            ->poll('60s');
    }
}

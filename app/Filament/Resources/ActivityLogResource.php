<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\ActivityLog;
use Filament\Actions;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
// TextEntry lives in Infolists, not Schemas — the wrong import 500'd every
// activity-detail view (the audit trail itself was unopenable).
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    public static function form(Schema $schema): Schema
    {
        $actionLabels = static::getActionLabels();

        return $schema
            ->columns(2)
            ->components([
                Section::make('Activity Details')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('action')
                            ->label(__('admin.action'))
                            ->badge()
                            ->formatStateUsing(fn ($state) => $actionLabels[$state] ?? $state)
                            ->weight(FontWeight::Bold),
                        TextEntry::make('admin.name')
                            ->label(__('admin.performed_by')),
                        TextEntry::make('created_at')
                            ->label(__('admin.date_time'))
                            ->dateTime('M j, Y H:i:s'),
                        TextEntry::make('ip_address')
                            ->label(__('admin.ip_address'))
                            ->fontMono()
                            ->copyable()
                            ->copyMessage('IP copied'),
                    ]),
                Section::make('Subject')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('model_type')
                            ->label(__('admin.source'))
                            ->formatStateUsing(function ($state) {
                                return match ($state) {
                                    'App\Filament\Pages\System\SetupAssistant' => 'Setup Assistant',
                                    'App\Filament\Pages\System\HealthCheckDashboard' => 'Health Check Dashboard',
                                    default => $state ? class_basename($state) : 'N/A',
                                };
                            })
                            ->html()
                            ->weight(FontWeight::Medium),
                        TextEntry::make('model_id')
                            ->label(__('admin.record_id'))
                            ->fontMono(),
                    ]),
                Section::make('Changes')
                    ->schema([
                        // state() (not formatStateUsing) — Filament treats array
                        // states as item lists and formats PER ELEMENT, so the
                        // foreach received ints/strings and fataled.
                        TextEntry::make('new_values')
                            ->label(__('admin.details'))
                            ->state(fn ($record): string => self::renderValueList($record->new_values))
                            ->html(),
                        TextEntry::make('old_values')
                            ->label(__('admin.previous_values'))
                            ->state(fn ($record): string => self::renderValueList($record->old_values))
                            ->html()
                            ->visible(fn ($record) => ! empty($record->old_values)),
                    ]),
            ]);
    }

    public static function getSourceUrl(?ActivityLog $record): ?string
    {
        if (! $record) {
            return null;
        }

        return match ($record->model_type) {
            \App\Filament\Pages\System\SetupAssistant::class => \App\Filament\Pages\System\SetupAssistant::getUrl(),
            \App\Filament\Pages\System\HealthCheckDashboard::class => \App\Filament\Pages\System\HealthCheckDashboard::getUrl(),
            default => null,
        };
    }

    /**
     * Render an old/new-values payload as escaped key: value lines.
     * Tolerates any shape (arrays, scalars, null).
     */
    public static function renderValueList(mixed $values): string
    {
        if (blank($values) || ! is_iterable($values)) {
            return '—';
        }

        $lines = [];
        foreach ($values as $key => $value) {
            $lines[] = '<span class="font-mono text-xs">' . e((string) $key) . ':</span> '
                . e(is_array($value) ? json_encode($value) : (string) $value);
        }

        return $lines === [] ? '—' : implode('<br>', $lines);
    }

    public static function getActionLabels(): array
    {
        return [
            'created' => 'Created',
            'updated' => 'Updated',
            'deleted' => 'Deleted',
            'login' => 'Login',
            'logout' => 'Logout',
            'order_status_changed' => 'Order Status Changed',
            'payment_received' => 'Payment Received',
            'settings_updated' => 'Settings Updated',
            'clear_cache' => 'Cache Cleared',
            'clear_views' => 'Views Cleared',
            'run_migrations' => 'Migrations Run',
            'seed_demo_data' => 'Demo Data Seeded',
            'maintenance_enabled' => 'Maintenance Enabled',
            'maintenance_disabled' => 'Maintenance Disabled',
            'health_check_run' => 'Health Check Run',
            'remediation_action' => 'Remediation Action',
        ];
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-clipboard-document-list';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Administration';
    }

    protected static ?int $navigationSort = 30;

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->modifyQueryUsing(fn ($query) => $query->with('admin'))
            ->columns([
                Tables\Columns\TextColumn::make('admin.name')
                    ->label(__('admin.admin'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('action')
                    ->label(__('admin.action'))
                    ->searchable()
                    ->sortable()
                    ->badge(),
                Tables\Columns\TextColumn::make('model_type')
                    ->label(__('admin.model'))
                    ->searchable()
                    ->sortable()
                    ->limit(30),
                Tables\Columns\TextColumn::make('model_id')
                    ->label(__('admin.record_id'))
                    ->numeric()
                    ->fontMono()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label(__('admin.ip_address'))
                    ->fontMono()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('admin.date'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('action')
                    ->label(__('admin.activity_type'))
                    ->options(static::getActionLabels())
                    ->searchable()
                    ->helperText('Filter by the type of admin activity.'),
                Tables\Filters\SelectFilter::make('admin_id')
                    ->label(__('admin.admin_user'))
                    ->relationship('admin', 'name')
                    ->helperText('Filter activities by the admin who performed them.'),
            ])
            ->actions([
                Tables\Actions\Action::make('viewSource')
                    ->label(__('admin.source'))
                    ->icon('heroicon-o-arrow-top-right-on-square')
                    ->color('gray')
                    ->url(fn (ActivityLog $record): ?string => static::getSourceUrl($record), shouldOpenInNewTab: true)
                    ->visible(fn (ActivityLog $record): bool => static::getSourceUrl($record) !== null),
                ...AdminUi::recordActionsReadOnly(),
            ])
            ->emptyStateIcon('heroicon-o-clipboard-document-list')
            ->emptyStateHeading('No activity logged yet')
            ->emptyStateDescription('Admin activities will appear here as users interact with the system.')
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    AdminUi::exportCsvBulkAction('Export Activity Log', [
                        'admin.name' => 'Admin',
                        'action' => 'Action',
                        'model_type' => 'Model',
                        'model_id' => 'Record ID',
                        'ip_address' => 'IP Address',
                        'created_at' => 'Date',
                    ]),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
            'view' => Pages\ViewActivityLog::route('/{record}'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['action', 'model_type'];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}

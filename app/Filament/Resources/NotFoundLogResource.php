<?php

namespace App\Filament\Resources;

use App\Enums\RedirectType;
use App\Filament\Resources\NotFoundLogResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\NotFoundLog;
use App\Models\Redirect;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * SEO 404 monitor — deduplicated log of frontend 404s (recorded from
 * bootstrap/app.php's NotFoundHttpException renderable hook), so dead inbound
 * links and stale sitemap entries surface in the admin instead of only in raw
 * web-server logs. Distinct from FailedSearchLogResource (internal site-search
 * misses, not HTTP 404s).
 */
class NotFoundLogResource extends Resource
{
    protected static ?string $model = NotFoundLog::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-link-slash';
    }

    public static function getNavigationBadge(): ?string
    {
        return \App\Support\NavBadge::count(
            'not_found_logs_unresolved',
            fn () => static::getModel()::where('resolved', false)->count()
        );
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Unresolved 404s';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Administration';
    }

    public static function getNavigationSort(): ?int
    {
        return 91;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'path';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('404 Details')
                    ->description('Read-only details of a dead link hit on the storefront.')
                    ->schema([
                        Forms\Components\TextInput::make('path')->disabled()->dehydrated(false)->columnSpanFull(),
                        Forms\Components\TextInput::make('referer')->label(__('admin.referer'))->disabled()->dehydrated(false)->columnSpanFull(),
                        Forms\Components\TextInput::make('lang')->label(__('admin.language'))->disabled()->dehydrated(false),
                        Forms\Components\TextInput::make('ip_address')->label(__('admin.ip_address'))->disabled()->dehydrated(false),
                        Forms\Components\TextInput::make('hit_count')->label(__('admin.hit_count'))->disabled()->dehydrated(false),
                        Forms\Components\Toggle::make('resolved')->label(__('admin.resolved'))->disabled()->dehydrated(false),
                        Forms\Components\DateTimePicker::make('first_seen_at')->disabled()->dehydrated(false),
                        Forms\Components\DateTimePicker::make('last_seen_at')->disabled()->dehydrated(false),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->columns([
                Tables\Columns\TextColumn::make('path')
                    ->label(__('admin.path'))
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Path copied')
                    ->limit(50)
                    ->fontMono(),
                Tables\Columns\TextColumn::make('referer')
                    ->label(__('admin.referer'))
                    ->limit(40)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('lang')
                    ->label(__('admin.language'))
                    ->badge()
                    ->alignCenter()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('hit_count')
                    ->label(__('admin.hits'))
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color(fn (int $state): string => $state >= 10 ? 'danger' : ($state >= 3 ? 'warning' : 'gray')),
                Tables\Columns\IconColumn::make('resolved')
                    ->boolean()
                    ->alignCenter(),
                Tables\Columns\TextColumn::make('ip_address')
                    ->label(__('admin.ip_address'))
                    ->fontMono()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('first_seen_at')
                    ->label(__('admin.first_seen'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('last_seen_at')
                    ->label(__('admin.last_seen'))
                    ->dateTime()
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('resolved')
                    ->label(__('admin.resolved'))
                    ->helperText('Show resolved or unresolved 404s.'),
                Tables\Filters\SelectFilter::make('lang')
                    ->label(__('admin.language'))
                    ->options([
                        'en' => 'English', 'de' => 'German', 'lt' => 'Lithuanian',
                        'fr' => 'French', 'es' => 'Spanish',
                    ])
                    ->native(false),
            ])
            ->actions([
                Actions\ActionGroup::make([
                    Actions\ViewAction::make(),
                    static::createRedirectAction(),
                    Actions\Action::make('toggleResolved')
                        ->label(fn (NotFoundLog $record): string => $record->resolved ? 'Mark unresolved' : 'Mark resolved')
                        ->icon(fn (NotFoundLog $record): string => $record->resolved ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                        ->color('gray')
                        ->authorize('update')
                        ->action(fn (NotFoundLog $record) => $record->update(['resolved' => ! $record->resolved])),
                ]),
            ])
            ->emptyStateIcon('heroicon-o-link-slash')
            ->emptyStateHeading('No 404s logged')
            ->emptyStateDescription('Dead links hit on the storefront will show up here.')
            ->bulkActions([
                Actions\BulkActionGroup::make([
                    AdminUi::exportCsvBulkAction('Export 404s', [
                        'path' => 'Path',
                        'referer' => 'Referer',
                        'lang' => 'Language',
                        'hit_count' => 'Hits',
                        'resolved' => 'Resolved',
                        'first_seen_at' => 'First Seen',
                        'last_seen_at' => 'Last Seen',
                    ]),
                    Actions\BulkAction::make('markResolved')
                        ->label(__('admin.mark_resolved'))
                        ->icon('heroicon-o-check-circle')
                        ->authorize('update')
                        ->action(fn ($records) => $records->each->update(['resolved' => true])),
                    // No bulk delete: history is retention/logs:clean's job, not a
                    // manual admin action, consistent with FailedSearchLogResource.
                ]),
            ])
            ->defaultSort('last_seen_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100]);
    }

    /** Quick "Create redirect" action — pre-fills from_url with this 404's path and resolves the log on success. */
    private static function createRedirectAction(): Actions\Action
    {
        return Actions\Action::make('createRedirect')
            ->label(__('admin.create_redirect'))
            ->icon('heroicon-o-arrow-turn-right-up')
            ->color('primary')
            ->authorize('update')
            ->visible(fn (NotFoundLog $record): bool => ! $record->resolved)
            ->form([
                Forms\Components\TextInput::make('from_url')
                    ->label(__('admin.from_url'))
                    ->disabled()
                    ->dehydrated(true),
                Forms\Components\TextInput::make('to_url')
                    ->label(__('admin.to_url'))
                    ->required()
                    ->helperText('Relative path or full URL the visitor should land on instead.'),
                Forms\Components\Select::make('type')
                    ->label(__('admin.redirect_type'))
                    ->options([
                        RedirectType::Permanent->value => '301 — Permanent',
                        RedirectType::Temporary->value => '302 — Temporary',
                    ])
                    ->default(RedirectType::Permanent->value)
                    ->required(),
            ])
            ->fillForm(fn (NotFoundLog $record): array => ['from_url' => $record->path])
            ->action(function (NotFoundLog $record, array $data): void {
                Redirect::create([
                    'from_url' => $record->path,
                    'to_url' => $data['to_url'],
                    'type' => $data['type'],
                    'is_active' => true,
                ]);
                $record->update(['resolved' => true]);

                Notification::make()->title('Redirect created')->success()->send();
            });
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNotFoundLogs::route('/'),
            'view' => Pages\ViewNotFoundLog::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['path'];
    }
}

<?php

namespace App\Filament\Resources;

use App\Enums\RedirectType;
use App\Filament\Resources\RedirectResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\Redirect;
use Filament\Forms;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Notifications\NotificationAction;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Support\Enums\FontWeight;

class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-arrow-right-end-on-rectangle';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Administration';
    }

    public static function getNavigationSort(): ?int
    {
        return 50;
    }

    public static function getRecordTitleAttribute(): ?string
    {
        return 'from_url';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'xl' => 3])
                    ->columnSpanFull()
                    ->schema([
                        // ─── Main column ──────────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 2])
                            ->schema([
                                Section::make('Routing Details')
                                    ->icon('heroicon-o-arrow-right-end-on-rectangle')
                                    ->description('Define the source and destination URLs for this redirect.')
                                    ->schema([
                                        Forms\Components\TextInput::make('from_url')
                                            ->label(__('admin.source_url_from'))
                                            // No leading slash: HandleRedirects matches against
                                            // Request::path(), which never has one — the old
                                            // placeholder's own example ("/old-page") produced a
                                            // stored value that could never match. Saved value is
                                            // also lowercased (Redirect::booted()), so the example
                                            // here matches exactly what gets stored.
                                            ->placeholder('e.g. old-page or en/old-path')
                                            ->required()
                                            ->maxLength(500)
                                            ->unique(ignoreRecord: true)
                                            ->helperText('The old URL path that should redirect — without a leading slash (e.g. "old-page" or "en/old-path"). Use relative paths for internal redirects.'),
                                        Forms\Components\TextInput::make('to_url')
                                            ->label(__('admin.destination_url_to'))
                                            ->placeholder('e.g. /new-page or https://example.com')
                                            ->required()
                                            ->maxLength(500)
                                            ->helperText('The new URL where visitors should be redirected to.')
                                            ->rules([
                                                fn (Get $get, ?Redirect $record): \Closure => function (string $attribute, $value, \Closure $fail) use ($get, $record) {
                                                    $from = strtolower(trim((string) $get('from_url'), '/'));
                                                    $to = strtolower(trim((string) $value, '/'));

                                                    if ($from !== '' && $from === $to) {
                                                        $fail('The destination cannot be the same as the source — this would redirect a visitor to themselves in an infinite loop.');

                                                        return;
                                                    }

                                                    $reverseExists = Redirect::query()
                                                        ->where('is_active', true)
                                                        ->where('from_url', $to)
                                                        ->where('to_url', $from)
                                                        ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                                                        ->exists();

                                                    if ($reverseExists) {
                                                        $fail('An active redirect already sends this destination back to the source — saving this would create an infinite redirect loop.');
                                                    }
                                                },
                                            ]),
                                        Forms\Components\Select::make('type')
                                            ->label(__('admin.redirect_type'))
                                            ->options(RedirectType::class)
                                            ->native(false)
                                            ->required()
                                            ->helperText('301 = Permanent (cached by browsers). 302 = Temporary (not cached).'),
                                    ])->columns(2),
                            ]),

                        // ─── Sidebar column ───────────────────────────────
                        Group::make()
                            ->columnSpan(['default' => 1, 'xl' => 1])
                            ->schema([
                                Section::make('Settings & Status')
                                    ->icon('heroicon-o-adjustments-horizontal')
                                    ->description('Redirect status and usage tracking.')
                                    ->schema([
                                        Forms\Components\Toggle::make('is_active')
                                            ->label(__('admin.redirect_active'))
                                            ->helperText('Inactive redirects are not enforced.')
                                            ->default(true),
                                        // No form-level default: this readOnly field
                                        // dehydrates as null on Create (nothing sets
                                        // it), which overrides the DB column's own
                                        // default(0) — an explicit NULL in the INSERT
                                        // still fails redirects.hit_count's NOT NULL
                                        // constraint even though the column has a
                                        // default. Crashed every "Create Redirect"
                                        // submission via the admin panel.
                                        AdminUi::readOnlyField('hit_count', 'Hit Count', 'Number of times this redirect has been triggered.')
                                            ->default(0),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return AdminUi::configureTable($table)
            ->columns([
            Tables\Columns\TextColumn::make('from_url')
                ->label(__('admin.from_url'))
                ->searchable()
                ->sortable()
                ->copyable()
                ->copyMessage('URL copied')
                ->weight(FontWeight::Medium)
                ->limit(40)
                ->fontMono(),
            Tables\Columns\TextColumn::make('to_url')
                ->label(__('admin.to_url'))
                ->searchable()
                ->copyable()
                ->copyMessage('URL copied')
                ->limit(40)
                ->fontMono(),
            Tables\Columns\TextColumn::make('type')
                ->label(__('admin.type'))
                ->badge()
                ->color(fn (RedirectType $state): string => match ($state) {
                    RedirectType::Permanent => 'success',
                    RedirectType::Temporary => 'warning',
                })
                ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('hit_count')
                    ->label(__('admin.hits'))
                    ->fontMono()
                    ->alignCenter()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('admin.active'))
                    ->boolean()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label(__('admin.redirect_type'))
                    ->options(RedirectType::class)
                    ->helperText('Filter by permanent (301) or temporary (302) redirects.'),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('admin.redirect_status'))
                    ->placeholder('All')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only'),
            ])
            ->actions(AdminUi::recordActionsWithoutView())
            ->bulkActions([
            Actions\BulkActionGroup::make([
                AdminUi::exportCsvBulkAction('Export Redirects', [
                    'from_url' => 'From URL',
                    'to_url' => 'To URL',
                    'type' => 'Type',
                    'hit_count' => 'Hits',
                    'is_active' => 'Active',
                ]),
                Actions\DeleteBulkAction::make(),
            ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->emptyStateIcon('heroicon-o-arrow-right-end-on-rectangle')
            ->emptyStateHeading('No redirect rules configured yet')
            ->emptyStateDescription('Create URL redirects for broken links, page migrations, or URL structure changes.')
            ->emptyStateActions([
                Tables\Actions\Action::make('create')
                    ->label(__('admin.create_redirect'))
                    ->url(static::getUrl('create'))
                    ->icon('heroicon-o-plus')
                    ->button(),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListRedirects::route('/'),
            'create' => Pages\CreateRedirect::route('/create'),
            'view'   => Pages\ViewRedirect::route('/{record}'),
            'edit'   => Pages\EditRedirect::route('/{record}/edit'),
        ];
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['from_url', 'to_url'];
    }
}


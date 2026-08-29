<?php

namespace App\Filament\Resources;

use App\Enums\RedirectType;
use App\Filament\Resources\RedirectResource\Pages;
use App\Filament\Support\AdminUi;
use App\Models\Redirect;
use App\Services\RedirectLoopDetector;
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

                                                        return;
                                                    }

                                                    // The direct-pair check above only catches a 2-hop
                                                    // loop (A->B, B->A). A longer chain (A->B, B->C,
                                                    // then saving C->A) went undetected — walk the full
                                                    // chain instead. Kept as a separate check below the
                                                    // direct-pair one (rather than replacing it) because
                                                    // its error message is more specific for the common
                                                    // 2-hop case.
                                                    $loopNode = app(RedirectLoopDetector::class)->findLoop($from, $to, $record?->getKey());

                                                    if ($loopNode !== null) {
                                                        $fail("Saving this would create a redirect loop — the chain eventually comes back to \"{$loopNode}\".");
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
            ->actions(AdminUi::recordActionsWithoutView([static::testRedirectAction()]))
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

    /**
     * No way existed to check whether a redirect's own destination is
     * actually healthy short of clicking through it manually (or waiting
     * for the Health Dashboard's redirect-health widget, which only
     * checks internal OEM hub targets against the products table, not an
     * arbitrary to_url). Makes a real, non-redirect-following HTTP request
     * so a chained/broken destination is caught immediately rather than
     * discovered later via a visitor's 404.
     */
    private static function testRedirectAction(): Actions\Action
    {
        return Actions\Action::make('testRedirect')
            ->label('Test')
            ->icon('heroicon-o-signal')
            ->color('gray')
            ->action(function (Redirect $record): void {
                $target = str_starts_with($record->to_url, 'http') ? $record->to_url : url($record->to_url);

                try {
                    $response = \Illuminate\Support\Facades\Http::timeout(5)->withoutRedirecting()->get($target);
                    $status = $response->status();

                    if ($status >= 200 && $status < 300) {
                        Notification::make()->title("Destination responds {$status} OK")->success()->send();
                    } elseif ($status >= 300 && $status < 400) {
                        Notification::make()
                            ->title("Destination itself redirects ({$status})")
                            ->body('This redirect chains into another one — Location: '.($response->header('Location') ?: 'unknown'))
                            ->warning()
                            ->send();
                    } else {
                        Notification::make()->title("Destination responded with HTTP {$status}")->danger()->send();
                    }
                } catch (\Throwable $e) {
                    Notification::make()->title('Could not reach the destination')->body($e->getMessage())->danger()->send();
                }
            });
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


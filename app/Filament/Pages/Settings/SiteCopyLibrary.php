<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Support\AdminUi;
use App\Models\Setting;
use App\Services\SettingsService;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Browsable/searchable editor for the ~344 `ui.*` text-override rows that
 * UiCopyInstaller seeded from lang/en/{search,cart,navbar}.php but which
 * never got any admin UI — see memory/project_ui_copy_text_override_gap.md
 * for the full history. Deliberately scoped to just the search_/cart_/
 * nav_ prefixes UiCopyInstaller actually seeded; checkout_/account_/
 * footer_ (~382 more ui_copy() call sites) are an explicit backlog item,
 * not built here — the query below excludes them defensively even though
 * nothing seeds them today. The 22 curated hero_* rows stay on
 * CustomizationSettings' own "Hero & UI Copy" tab, unchanged — this is an
 * ADDITIONAL browser for everything else, not a replacement.
 *
 * Same HasTable-on-a-bare-Page shape as BackupDashboard/FailedJobsPage —
 * the only precedent in this codebase for a settings-adjacent tool that
 * isn't a SettingsPage subclass (registered as type:'tool' in
 * SettingsRegistry, exempt from its strict SettingsPage 1:1 test).
 *
 * Values are 5-locale JSON blobs, not scalars, so editing happens via a
 * row-action modal reusing AdminUi::translatableTabs() — the same
 * multi-locale UX every other translatable field in the app already uses
 * — rather than an inline TextInputColumn (ProductResource's price-column
 * precedent doesn't apply to a JSON blob).
 */
class SiteCopyLibrary extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'settings/site-copy-library';

    protected static ?string $title = 'Site Copy Library';

    protected string $view = 'filament.pages.settings.site-copy-library';

    protected ?string $subheading = 'Browse and edit the storefront cart/search/navbar text overrides that have no dedicated settings field.';

    private const PREFIXES = ['cart_', 'search_', 'nav_'];

    private const LOCALES = ['en', 'de', 'lt', 'fr', 'es'];

    public static function getNavigationIcon(): string
    {
        return 'heroicon-o-document-text';
    }

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Setting::query()
                    ->where('group', 'ui')
                    ->where(function (Builder $query): void {
                        foreach (self::PREFIXES as $prefix) {
                            $query->orWhere('key', 'like', $prefix . '%');
                        }
                    })
            )
            ->defaultSort('key')
            ->searchable(false)
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->label('Key')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('category')
                    ->label('Category')
                    ->state(fn (Setting $record): string => self::categoryFor($record->key))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Cart' => 'info',
                        'Search' => 'warning',
                        'Navbar' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('english_preview')
                    ->label('English Value')
                    ->state(fn (Setting $record): string => self::englishPreview($record->value))
                    ->limit(60)
                    ->wrap(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('prefix')
                    ->label('Category')
                    ->options([
                        'cart_' => 'Cart',
                        'search_' => 'Search',
                        'nav_' => 'Navbar',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (filled($data['value'] ?? null)) {
                            $query->where('key', 'like', $data['value'] . '%');
                        }

                        return $query;
                    }),
            ])
            ->actions([
                Tables\Actions\Action::make('edit')
                    ->label('Edit')
                    ->icon('heroicon-o-pencil')
                    ->modalWidth('2xl')
                    ->modalHeading(fn (Setting $record): string => 'Edit: ' . $record->key)
                    ->fillForm(function (Setting $record): array {
                        $decoded = json_decode((string) $record->value, true);
                        $decoded = is_array($decoded) ? $decoded : [];

                        return [
                            'value' => array_merge(array_fill_keys(self::LOCALES, ''), $decoded),
                        ];
                    })
                    ->form([
                        AdminUi::translatableTabs('Text', [
                            'value' => [
                                'label' => 'Text',
                                'type' => 'textarea',
                                'rows' => 2,
                            ],
                        ]),
                    ])
                    ->action(function (Setting $record, array $data): void {
                        $record->update([
                            'value' => json_encode($data['value'], JSON_UNESCAPED_UNICODE),
                        ]);

                        app(SettingsService::class)->forget('ui');

                        Notification::make()
                            ->title('Saved')
                            ->body("ui.{$record->key} updated.")
                            ->success()
                            ->send();
                    }),
            ])
            ->emptyStateHeading('No matching text overrides')
            ->emptyStateIcon('heroicon-o-document-text');
    }

    private static function categoryFor(string $key): string
    {
        return match (true) {
            str_starts_with($key, 'cart_') => 'Cart',
            str_starts_with($key, 'search_') => 'Search',
            str_starts_with($key, 'nav_') => 'Navbar',
            default => 'Other',
        };
    }

    private static function englishPreview(?string $rawValue): string
    {
        $decoded = json_decode((string) $rawValue, true);

        if (! is_array($decoded)) {
            return (string) $rawValue;
        }

        return (string) ($decoded['en'] ?? '');
    }
}

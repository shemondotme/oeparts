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
 * Browsable/searchable editor for the `ui.*` text-override rows that
 * UiCopyInstaller seeds from lang/en/{search,cart,navbar,checkout,account,
 * footer}.php but which never got any admin UI — see
 * memory/project_ui_copy_text_override_gap.md for the full history.
 * search_/cart_/nav_ shipped first (Phase 7, ~344 rows); checkout_/
 * account_/footer_ (~512 more rows) followed as the deliberately-deferred
 * fast-follow once that surface proved out. The query below still
 * excludes every OTHER prefix defensively (e.g. hero_) even though
 * nothing seeds them under this Page. The 22 curated hero_* rows stay on
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

    // Only reachable via the Settings hub (Brand & Storefront →
    // Customization) — every real SettingsPage already hides itself the
    // same way (SettingsPage::$shouldRegisterNavigation = false); this
    // page just never matched that convention despite living in the same
    // family, so it showed up twice: once correctly in the hub grid, once
    // more as an ungrouped, floating top-level sidebar item.
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'Site Copy Library';

    protected string $view = 'filament.pages.settings.site-copy-library';

    protected ?string $subheading = 'Browse and edit storefront text overrides (cart, search, navbar, checkout, account, footer) that have no dedicated settings field.';

    private const PREFIXES = ['cart_', 'search_', 'nav_', 'checkout_', 'account_', 'footer_'];

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
            ->groups([
                // No SQL GROUP BY here — Filament's table grouping only
                // collapses *adjacent* rows sharing a computed key into a
                // visual section, it never aggregates rows away (confirmed
                // against the one other ->groups() precedent in this
                // codebase, FailedJobsPage). ->column('key') exists purely
                // so orderQuery() has a real column to ORDER BY — sorting
                // by the raw key alphabetically already clusters same-
                // prefix rows adjacently on its own (no two of the six
                // prefixes share a common first character), which is what
                // actually keeps each category's rows together; the group
                // key/title themselves come from the closures below, not
                // from that column's raw value.
                Tables\Grouping\Group::make('category')
                    ->label('Category')
                    ->collapsible()
                    ->titlePrefixedWithLabel(false)
                    ->column('key')
                    ->getKeyFromRecordUsing(fn (Setting $record): string => self::categoryFor($record->key))
                    ->getTitleFromRecordUsing(fn (Setting $record): string => self::categoryFor($record->key)),
            ])
            ->defaultGroup('category')
            ->groupingSettingsHidden()
            ->columns([
                Tables\Columns\TextColumn::make('key')
                    ->label('Key')
                    ->searchable()
                    ->sortable()
                    ->fontFamily('mono'),

                Tables\Columns\TextColumn::make('english_preview')
                    ->label('English Value')
                    ->state(fn (Setting $record): string => self::englishPreview($record->value))
                    ->limit(60)
                    ->wrap()
                    // 'value' is a raw 5-locale JSON blob, not a plain
                    // column — LIKE against the raw text still matches
                    // correctly since the searched string is a literal
                    // substring of the JSON either way, and it happens to
                    // also match non-English locales, which is a bonus not
                    // a problem for finding a row by its visible content.
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->orWhere('value', 'like', "%{$search}%")),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('prefix')
                    ->label('Category')
                    ->options([
                        'cart_' => 'Cart',
                        'search_' => 'Search',
                        'nav_' => 'Navbar',
                        'checkout_' => 'Checkout',
                        'account_' => 'Account',
                        'footer_' => 'Footer',
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
            str_starts_with($key, 'checkout_') => 'Checkout',
            str_starts_with($key, 'account_') => 'Account',
            str_starts_with($key, 'footer_') => 'Footer',
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

<?php

namespace App\Filament\Support;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\RefundStatus;
use App\Filament\Pages\Settings\SettingsPage;
use App\Filament\Resources\AbandonedCartResource;
use App\Filament\Resources\ActivityLogResource;
use App\Filament\Resources\AdminResource;
use App\Filament\Resources\BlogPostResource;
use App\Filament\Resources\CarModelResource;
use App\Filament\Resources\CarrierResource;
use App\Filament\Resources\CategoryResource;
use App\Filament\Resources\ConditionResource;
use App\Filament\Resources\ContactMessageResource;
use App\Filament\Resources\CouponResource;
use App\Filament\Resources\CronLogResource;
use App\Filament\Resources\CustomerResource;
use App\Filament\Resources\EmailLogResource;
use App\Filament\Resources\FailedSearchLogResource;
use App\Filament\Resources\FaqResource;
use App\Filament\Resources\IpBlocklistResource;
use App\Filament\Resources\LanguageResource;
use App\Filament\Resources\LoginLogResource;
use App\Filament\Resources\ManufacturerResource;
use App\Filament\Resources\MediaFileResource;
use App\Filament\Resources\MenuResource;
use App\Filament\Resources\NewsletterCampaignResource;
use App\Filament\Resources\NewsletterSubscriberResource;
use App\Filament\Resources\OrderResource;
use App\Filament\Resources\PageResource;
use App\Filament\Resources\PartInquiryResource;
use App\Filament\Resources\PaymentResource;
use App\Filament\Resources\ProductResource;
use App\Filament\Resources\RedirectResource;
use App\Filament\Resources\RefundRequestResource;
use App\Filament\Resources\RoleResource;
use App\Filament\Resources\SearchLogResource;
use App\Filament\Resources\SectionResource;
use App\Filament\Resources\SeoMetaResource;
use App\Filament\Resources\ShippingMethodResource;
use App\Filament\Resources\ShippingZoneResource;
use App\Filament\Resources\TestimonialResource;
use App\Filament\Resources\TranslationResource;
use Closure;
use Filament\Actions;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\Str;

/**
 * Shared admin UI conventions (Products pilot standard).
 */
final class AdminUi
{
    /**
     * Enterprise UI baseline helpers.
     *
     * NOTE: Keep this class small + deterministic; resources/widgets call into it.
     */

    /** @var array<string, string> Locale code => native name */
    public const LOCALES = [
        'en' => 'English',
        'de' => 'Deutsch',
        'lt' => 'Lietuvių',
        'fr' => 'Français',
        'es' => 'Español',
    ];

    /**
     * Enterprise standard: widget card drilldown target + optional filter payload.
     */
    public static function drilldown(string $url, array $filters = []): array
    {
        return [
            'url' => $url,
            'filters' => $filters,
        ];
    }

    /**
     * Enterprise standard empty state copy.
     */
    public static function emptyState(string $heading, string $description, ?string $ctaLabel = null, ?string $ctaUrl = null): array
    {
        return [
            'heading' => $heading,
            'description' => $description,
            'ctaLabel' => $ctaLabel,
            'ctaUrl' => $ctaUrl,
        ];
    }

    /**
     * Resolve a translatable JSON field to a display string.
     */
    public static function localizedName(mixed $name, string $fallback = '—'): string
    {
        if (is_array($name)) {
            $value = trans_field($name);

            return $value !== '' ? $value : $fallback;
        }

        return filled($name) ? (string) $name : $fallback;
    }

    /**
     * Neutralize CSV/formula injection (CWE-1236): a cell starting with
     * =/+/-/@ (or tab/CR) is executed as a formula by Excel/Google Sheets
     * the moment the file is opened, not just displayed as text. Several
     * exportCsvBulkAction() columns carry unauthenticated-visitor-supplied
     * free text (review comments, refund reasons, contact/part-inquiry
     * messages) straight through to the CSV a super_admin later opens — a
     * value like `=HYPERLINK("http://evil","x")` submitted as a review
     * comment would run as a live formula for that admin. Prefixing with a
     * single quote is the standard mitigation: spreadsheet apps treat the
     * cell as literal text instead of a formula, and the quote itself isn't
     * rendered.
     */
    protected static function escapeCsvFormula(string $value): string
    {
        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    /**
     * Standard copyable text column with monospace font and copy feedback.
     */
    public static function copyableColumn(string $name, string $label, string $copyMessage = 'Copied to clipboard'): TextColumn
    {
        return TextColumn::make($name)
            ->label($label)
            ->copyable()
            ->copyMessage($copyMessage)
            ->copyMessageDuration(1500)
            ->fontMono()
            ->weight(FontWeight::Medium)
            ->extraAttributes(['class' => 'cursor-pointer']);
    }

    /**
     * OEM number column — copyable with monospace + bold weight.
     */
    public static function oemColumn(string $name = 'oem_number', string $copyMessage = 'OEM number copied'): TextColumn
    {
        return self::copyableColumn($name, 'OEM Number', $copyMessage)
            ->weight(FontWeight::Bold)
            ->searchable()
            ->sortable()
            ->extraAttributes(['class' => 'oem-number cursor-pointer']);
    }

    /**
     * Standard boolean icon column with consistent green/red styling.
     */
    public static function booleanColumn(string $name, string $label, bool $toggledHiddenByDefault = false): IconColumn
    {
        $col = IconColumn::make($name)
            ->label($label)
            ->boolean()
            ->alignCenter();

        if ($toggledHiddenByDefault) {
            $col->toggleable(isToggledHiddenByDefault: true);
        }

        return $col;
    }

    /**
     * Standard sort_order numeric column.
     */
    public static function sortOrderColumn(string $name = 'sort_order', string $label = 'Sort', bool $toggledHiddenByDefault = true): TextColumn
    {
        return TextColumn::make($name)
            ->label($label)
            ->numeric()
            ->sortable()
            ->alignCenter()
            ->toggleable(isToggledHiddenByDefault: $toggledHiddenByDefault);
    }

    /**
     * Standard count column with monospace font.
     */
    public static function countColumn(string $name, string $label, ?string $relationName = null, bool $toggledHiddenByDefault = false): TextColumn
    {
        $col = TextColumn::make($name)
            ->label($label)
            ->numeric()
            ->sortable()
            ->fontMono()
            ->alignCenter();

        if ($relationName !== null) {
            $col->counts($relationName);
        }

        if ($toggledHiddenByDefault) {
            $col->toggleable(isToggledHiddenByDefault: true);
        }

        return $col;
    }

    /**
     * Standard money input with EUR prefix, step 0.01, and bcmath-compatible validation.
     */
    public static function moneyInput(string $name, string $label, bool $required = false): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->numeric()
            ->prefix('€')
            ->required($required)
            ->minValue(0)
            ->step(0.01)
            ->rule('decimal:0,2');
    }

    /**
     * Slug auto-generation input — auto-fills from a source field on blur if slug is empty.
     *
     * @param  string  $name  Field name (e.g. 'slug')
     * @param  string  $label  Display label
     * @param  string  $sourceCode  Locale code of the source name field (e.g. 'en')
     * @param  string  $sourceField  Source field name to watch (e.g. 'name')
     * @param  string|null  $uniqueGuard  Column for unique(ignoreRecord:true), or null to skip
     */
    public static function slugInput(string $name, string $label, string $sourceCode = 'en', string $sourceField = 'name', ?string $uniqueGuard = null): TextInput
    {
        $field = TextInput::make($name)
            ->label($label)
            ->placeholder(__(sprintf('e.g. %s', Str::slug('example-slug'))))
            ->helperText('Auto-filled from the English name. Used in page URLs.')
            ->required()
            ->maxLength(200)
            ->live(onBlur: true)
            ->afterStateUpdated(function ($state, callable $set, callable $get) use ($sourceCode, $sourceField): void {
                $source = $get("{$sourceField}.{$sourceCode}");
                if (filled($source) && blank($get('slug'))) {
                    $set('slug', Str::slug($source));
                }
            })
            ->columnSpanFull();

        if ($uniqueGuard !== null) {
            $field->unique(ignoreRecord: true);
        }

        return $field;
    }

    /**
     * Standard multilingual tabs for translatable JSON content.
     *
     * @param  array<string, array{label: string, required: bool, placeholder?: string, placeholders?: array<string, string>, helperText?: string, slugSync?: bool}>  $fields  Field definitions. Set slugSync=>true on a text field to auto-fill $slugSyncTarget from its English value. `placeholders` (keyed by locale code) overrides `placeholder` per-locale when a field needs a localized example value; falls back to `placeholder` on the English tab only when absent.
     * @param  array<string, string>|null  $locales  Locale map (defaults to AdminUi::LOCALES)
     * @param  string|null  $slugSyncTarget  Field name to auto-fill (e.g. 'slug') from the field(s) marked slugSync=>true. Null = no autofill (default).
     * @param  string  $slugSyncMode  'fill-if-blank' (default; fills any time the target is currently empty) or 'create-only' (fills only while $operation === 'create', regardless of the target's current value)
     */
    public static function translatableTabs(
        string $tabsLabel,
        array $fields,
        ?array $locales = null,
        ?string $slugSyncTarget = null,
        string $slugSyncMode = 'fill-if-blank',
    ): Tabs {
        $locales ??= self::LOCALES;

        return Tabs::make($tabsLabel)
            ->schema(
                collect($locales)
                    ->map(fn (string $localeLabel, string $code) => Tab::make($localeLabel)
                        ->badge($code === 'en' ? 'Primary' : null)
                        ->schema(
                            collect($fields)
                                ->map(function (array $config, string $fieldName) use ($code, $slugSyncTarget, $slugSyncMode) {
                                    $required = ($config['required'] ?? false) && $code === 'en';
                                    $maxLength = $config['maxLength'] ?? 255;
                                    $rows = $config['rows'] ?? null;
                                    $syncsSlug = $slugSyncTarget !== null && $code === 'en' && ($config['slugSync'] ?? false);
                                    // Opt-in only (default false) — existing callers are
                                    // completely unaffected. A caller that also wants a
                                    // live-updating preview alongside this field (e.g. a
                                    // template with placeholders) sets this per-field.
                                    $wantsLive = $config['live'] ?? false;

                                    if (($config['type'] ?? 'text') === 'textarea') {
                                        $field = Textarea::make("{$fieldName}.{$code}")
                                            ->label($config['label'])
                                            ->required($required)
                                            ->rows($rows ?? 5)
                                            ->placeholder($config['placeholders'][$code] ?? ($code === 'en' ? ($config['placeholder'] ?? null) : null))
                                            ->helperText($code === 'en'
                                                ? ($config['helperText'] ?? null)
                                                : 'Leave blank to fall back to the English value.');

                                        if ($wantsLive) {
                                            $field->live(debounce: 500);
                                        }

                                        return $field;
                                    }

                                    if (($config['type'] ?? 'text') === 'richeditor') {
                                        return RichEditor::make("{$fieldName}.{$code}")
                                            ->label($config['label'])
                                            ->nullable()
                                            ->columnSpanFull();
                                    }

                                    $field = TextInput::make("{$fieldName}.{$code}")
                                        ->label($config['label'])
                                        ->required($required)
                                        ->maxLength($maxLength)
                                        ->placeholder($config['placeholders'][$code] ?? ($code === 'en' ? ($config['placeholder'] ?? null) : null))
                                        ->helperText($code === 'en'
                                            ? ($config['helperText'] ?? 'English value is required and used as the default fallback.')
                                            : 'Leave blank to fall back to the English value.');

                                    if ($wantsLive && ! $syncsSlug) {
                                        $field->live(debounce: 500);
                                    }

                                    if ($syncsSlug) {
                                        // Not onBlur: the field that loses
                                        // focus is usually blurred BY the
                                        // click on the Create/Save button
                                        // itself, so an onBlur round trip
                                        // races that same click's native
                                        // form submission — the required
                                        // slug input is still empty at the
                                        // instant the browser validates it
                                        // synchronously, silently blocking
                                        // submission with no visible error
                                        // (confirmed live: Category/
                                        // Manufacturer/BlogPost/Page all
                                        // share this helper and all hit it).
                                        // A debounce firing while the name
                                        // is still being typed clears the
                                        // slug well before Create is ever
                                        // clicked.
                                        $field->live(debounce: 500);

                                        $field->afterStateUpdated($slugSyncMode === 'create-only'
                                            ? function ($state, callable $set, ?string $operation) use ($slugSyncTarget): void {
                                                if ($operation === 'create' && is_string($state) && filled($state)) {
                                                    $set($slugSyncTarget, Str::slug($state));
                                                }
                                            }
                                            : function ($state, callable $set, callable $get) use ($slugSyncTarget): void {
                                                if (filled($state) && blank($get($slugSyncTarget))) {
                                                    $set($slugSyncTarget, Str::slug($state));
                                                }
                                            });
                                    }

                                    return $field;
                                })
                                ->values()
                                ->all()
                        )
                    )
                    ->values()
                    ->all()
            )
            ->columnSpanFull();
    }

    /**
     * Standard boolean toggle for sidebar settings (active, visible, etc.).
     */
    public static function toggleField(string $name, string $label, ?string $helperText = null, bool $default = true): Toggle
    {
        return Toggle::make($name)
            ->label($label)
            ->helperText($helperText)
            ->default($default);
    }

    /**
     * Standard read-only display field — shown but not submitted.
     */
    public static function readOnlyField(string $name, string $label, ?string $helperText = null): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->readOnly()
            ->helperText($helperText);
    }

    /**
     * Standard list-table behaviour: persisted state, pagination, striping.
     */
    public static function configureTable(Table $table): Table
    {
        return $table
            ->filtersFormColumns(2)
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->persistSearchInSession()
            ->defaultPaginationPageOption(25)
            ->striped()
            ->deferLoading()
            ->paginated([10, 25, 50, 100])
            ->recordUrl(fn ($record): string => self::getResourceUrl($record));
    }

    /**
     * Read-only section for view pages — consistent heading + description + disabled fields.
     */
    public static function readOnlySection(string $heading, string $description, array $schema, int $columns = 2): Section
    {
        return Section::make($heading)
            ->description($description)
            ->schema($schema)
            ->columns($columns);
    }

    /**
     * Standard bulk action with impact summary in the confirmation modal.
     *
     * @param  string  $name  Action name (used as Livewire key)
     * @param  string  $label  Button label
     * @param  string  $color  Filament color (success, warning, danger, gray, info)
     * @param  string  $icon  Heroicon name
     * @param  Closure  $action  fn(Collection $records, array $data): void
     * @param  Closure|null  $summary  fn($record): ?array Returns ['key'=>..., 'old'=>..., 'new'=>..., 'masked'=>bool] or null to skip
     * @param  array<int, Component>  $form  Optional modal form schema
     * @param  Closure|null  $visible  fn(Collection $records): bool
     */
    public static function impactBulkAction(
        string $name,
        string $label,
        string $color,
        string $icon,
        Closure $action,
        ?Closure $summary = null,
        array $form = [],
        ?Closure $visible = null,
    ): BulkAction {
        return BulkAction::make($name)
            ->label($label)
            ->color($color)
            ->icon($icon)
            ->requiresConfirmation()
            ->deselectRecordsAfterCompletion()
            ->modalHeading($label)
            ->modalSubmitActionLabel('Yes, proceed')
            ->when($visible !== null, fn (BulkAction $a) => $a->visible($visible))
            ->when(! empty($form), fn (BulkAction $a) => $a->form($form))
            ->modalDescription(function (Collection $records) use ($summary, $form) {
                $count = $records->count();

                if (! empty($form)) {
                    return "This will affect {$count} records. Enter the values below to proceed.";
                }

                if ($summary === null) {
                    return "Are you sure you want to apply this action to {$count} records?";
                }

                $changes = $records
                    ->map(fn ($record) => $summary($record))
                    ->filter(fn ($item) => $item !== null)
                    ->values()
                    ->toArray();

                if (empty($changes)) {
                    return 'No records will be affected.';
                }

                return '';
            })
            ->modalContent(function (Collection $records) use ($label, $summary, $form) {
                if (! empty($form) || $summary === null) {
                    return null;
                }

                $changes = $records
                    ->map(fn ($record) => $summary($record))
                    ->filter(fn ($item) => $item !== null)
                    ->values()
                    ->toArray();

                if (empty($changes)) {
                    return null;
                }

                return view('components.bulk-change-preview', [
                    'changes' => $changes,
                    'heading' => count($changes).' '.lcfirst($label),
                ]);
            })
            ->action(fn (Collection $records, array $data) => $action($records, $data))
            // Gate::callPolicyMethod() strips a lone leading string argument,
            // assuming it's just a class hint for an ability like 'create' —
            // that collapses BasePolicy::update($admin, $record)'s call to 1
            // arg and throws. Passing the class twice survives the strip
            // (one copy is removed, one remains as the $record argument),
            // which BasePolicy never dereferences anyway.
            ->authorize(fn (?string $model): bool => $model === null || (auth('admin')->user()?->can('update', [$model, $model]) ?? false));
    }

    /**
     * Reusable Export CSV bulk action.
     *
     * @param  array<string, string>  $columns  Column accessor => CSV header label
     */
    public static function exportCsvBulkAction(string $label = 'Export CSV', array $columns = []): BulkAction
    {
        return BulkAction::make('exportCsv')
            ->label($label)
            ->icon('heroicon-o-arrow-down-tray')
            ->action(function (Collection $records) use ($columns) {
                $headers = array_values($columns);
                $accessors = array_keys($columns);

                return Response::streamDownload(function () use ($records, $headers, $accessors) {
                    // Echoes row by row instead of building the whole CSV as
                    // ONE string in $csv first (the original implementation)
                    // — this is a shared BulkAction, and Filament's default
                    // "select all X records" crosses pagination, so on a
                    // table at real production scale (Products: 1M+ rows;
                    // the dev seed is only ~90 — see
                    // [[project_production_catalog_scale]]) that meant
                    // holding the full Eloquent Collection, a second mapped
                    // array of the same size, AND an ever-growing
                    // accumulator string all in memory simultaneously —
                    // easily exceeding shared hosting's typical 128-256M
                    // memory_limit on a genuinely large "select all" export.
                    // Echoing keeps peak memory flat regardless of row
                    // count; @set_time_limit(0) below matches, since this is
                    // still a synchronous request (right for every OTHER
                    // resource on this shared helper, all small reference/
                    // config tables where an instant download is the
                    // correct UX) that could otherwise be killed mid-export
                    // by PHP's default max_execution_time on a legitimately
                    // large selection.
                    @set_time_limit(0);

                    echo collect($headers)->implode(',')."\n";

                    foreach ($records as $record) {
                        // A column accessor like 'manufacturer.name' can
                        // resolve to a translatable array-cast attribute
                        // (Product/Manufacturer names are all {locale =>
                        // value} JSON, not plain strings) — (string) $cell
                        // on an array crashed every export whose columns
                        // touched one, confirmed live via a real "Export
                        // Products" click 500ing outright.
                        //
                        // A backed enum (e.g. Redirect::type) crashed the
                        // SAME way — PHP backed enums don't implement
                        // Stringable, so (string) $cell threw "could not be
                        // converted to string" on every "Export Redirects"
                        // click, confirmed live via a real RedirectType-cast
                        // column.
                        $line = collect($accessors)
                            ->map(fn ($accessor) => data_get($record, $accessor, ''))
                            ->map(function ($cell) {
                                $value = match (true) {
                                    $cell instanceof \BackedEnum => (string) $cell->value,
                                    is_array($cell) => static::localizedName($cell),
                                    default => (string) $cell,
                                };

                                return '"'.str_replace('"', '""', static::escapeCsvFormula($value)).'"';
                            })
                            ->implode(',');

                        echo $line."\n";
                    }
                }, 'export-'.now()->format('Y-m-d-His').'.csv');
            })
            ->authorize(fn (?string $model): bool => $model === null || (auth('admin')->user()?->can('viewAny', $model) ?? false))
            ->deselectRecordsAfterCompletion();
    }

    /**
     * View / edit / delete grouped into a row action menu.
     *
     * @param  array<int, Actions\Action>  $before
     * @param  array<int, Actions\Action>  $after
     * @return array<int, Actions\ActionGroup>
     */
    public static function recordActions(array $before = [], array $after = []): array
    {
        $actions = array_merge(
            $before,
            [
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
            ],
            $after,
            [
                Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Record')
                    ->modalDescription('Are you sure you want to delete this record? This action cannot be undone.')
                    ->modalSubmitActionLabel('Yes, delete'),
            ],
        );

        return [
            Actions\ActionGroup::make($actions),
        ];
    }

    /**
     * Edit / delete only (resources without a view page).
     *
     * @param  array<int, Actions\Action>  $before
     * @param  array<int, Actions\Action>  $after
     * @return array<int, Actions\ActionGroup>
     */
    public static function recordActionsWithoutView(array $before = [], array $after = []): array
    {
        $actions = array_merge(
            $before,
            [
                Actions\EditAction::make(),
            ],
            $after,
            [
                Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Record')
                    ->modalDescription('Are you sure you want to delete this record? This action cannot be undone.')
                    ->modalSubmitActionLabel('Yes, delete'),
            ],
        );

        return [
            Actions\ActionGroup::make($actions),
        ];
    }

    /**
     * View / edit / delete / restore / force-delete for SoftDeletes models —
     * RestoreAction/ForceDeleteAction only render for an already-trashed
     * record, so this is safe to use even before any row has been deleted.
     * Pair with trashedFilter() in the table's ->filters() so deleted rows
     * are actually reachable to restore.
     *
     * @param  array<int, Actions\Action>  $before
     * @param  array<int, Actions\Action>  $after
     * @return array<int, Actions\ActionGroup>
     */
    public static function recordActionsWithTrash(array $before = [], array $after = []): array
    {
        $actions = array_merge(
            $before,
            [
                Actions\ViewAction::make(),
                Actions\EditAction::make(),
            ],
            $after,
            [
                Actions\DeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Delete Record')
                    ->modalDescription('Are you sure you want to delete this record? It can be restored later from the Trashed filter.')
                    ->modalSubmitActionLabel('Yes, delete'),
                Actions\RestoreAction::make(),
                Actions\ForceDeleteAction::make()
                    ->requiresConfirmation()
                    ->modalHeading('Permanently Delete Record')
                    ->modalDescription('This permanently erases the record — unlike Delete, this cannot be undone.')
                    ->modalSubmitActionLabel('Yes, permanently delete'),
            ],
        );

        return [
            Actions\ActionGroup::make($actions),
        ];
    }

    /**
     * A "Trashed" table filter for SoftDeletes models — without this,
     * deleted rows are invisible everywhere with no path back, even to a
     * super_admin, despite RestoreAction being wired up in the row menu.
     */
    public static function trashedFilter(): Tables\Filters\TrashedFilter
    {
        return Tables\Filters\TrashedFilter::make();
    }

    /**
     * View-only action group for log / read-only resources.
     *
     * @return array<int, Actions\ActionGroup>
     */
    public static function recordActionsReadOnly(array $before = [], array $after = []): array
    {
        $actions = array_merge(
            $before,
            [
                Actions\ViewAction::make(),
            ],
            $after,
        );

        return [
            Actions\ActionGroup::make($actions),
        ];
    }

    public static function orderStatusColor(OrderStatus $status): string
    {
        return $status->color();
    }

    public static function paymentStatusColor(PaymentStatus|PaymentTransactionStatus $status): string
    {
        return match ($status) {
            PaymentStatus::Pending, PaymentTransactionStatus::Pending => 'warning',
            PaymentStatus::Paid, PaymentTransactionStatus::Captured => 'success',
            PaymentStatus::Failed, PaymentTransactionStatus::Failed => 'danger',
            PaymentStatus::Refunded, PaymentTransactionStatus::Refunded => 'gray',
            PaymentTransactionStatus::Authorized => 'info',
        };
    }

    public static function refundStatusColor(RefundStatus $status): string
    {
        return match ($status) {
            RefundStatus::Pending => 'warning',
            RefundStatus::Approved => 'info',
            RefundStatus::Rejected => 'danger',
            RefundStatus::Processed => 'success',
        };
    }

    /**
     * Build a resource index URL with optional pre-filter parameters for drilldowns.
     *
     * @param  string  $resourceClass  Fully-qualified Filament resource class
     * @param  array|null  $tableFilters  Filters to apply, e.g. ['status' => ['value' => 'pending']]
     * @param  string|null  $tableSearch  Search query string
     * @param  string|null  $tableSort  Sort string, e.g. 'created_at:desc'
     */
    /**
     * "Settings" header link from a module list to its settings page —
     * the WooCommerce-style module↔settings bridge. Hidden for admins who
     * can't access the settings page.
     *
     * @param  class-string<SettingsPage>  $settingsPage
     */
    public static function settingsLinkAction(string $settingsPage, string $tooltip = 'Open the settings for this module'): Actions\Action
    {
        return Actions\Action::make('moduleSettings')
            ->label('Settings')
            ->icon('heroicon-o-cog-6-tooth')
            ->color('gray')
            ->url(fn (): string => $settingsPage::getUrl())
            ->tooltip($tooltip)
            ->visible(fn (): bool => $settingsPage::canAccess());
    }

    public static function drilldownUrl(
        string $resourceClass,
        ?array $tableFilters = null,
        ?string $tableSearch = null,
        ?string $tableSort = null,
    ): string {
        $params = [];

        if ($tableFilters !== null) {
            $params['tableFilters'] = $tableFilters;
        }

        if ($tableSearch !== null && $tableSearch !== '') {
            $params['tableSearch'] = $tableSearch;
        }

        if ($tableSort !== null && $tableSort !== '') {
            $params['tableSort'] = $tableSort;
        }

        return $resourceClass::getUrl('index', $params);
    }

    /**
     * Resolve the view URL for a record based on its resource.
     */
    private static function getResourceUrl($record): string
    {
        $resourceClass = match (class_basename($record)) {
            // Commerce
            'Order' => OrderResource::class,
            'Payment' => PaymentResource::class,
            'RefundRequest' => RefundRequestResource::class,
            'ShippingZone' => ShippingZoneResource::class,
            'ShippingMethod' => ShippingMethodResource::class,
            'Carrier' => CarrierResource::class,
            // Catalog
            'Product' => ProductResource::class,
            'Manufacturer' => ManufacturerResource::class,
            'CarModel' => CarModelResource::class,
            'Category' => CategoryResource::class,
            'Condition' => ConditionResource::class,
            // Customers
            'User' => CustomerResource::class,
            'ContactMessage' => ContactMessageResource::class,
            'PartInquiry' => PartInquiryResource::class,
            // Content
            'BlogPost' => BlogPostResource::class,
            'Section' => SectionResource::class,
            'Page' => PageResource::class,
            'Faq' => FaqResource::class,
            'Menu' => MenuResource::class,
            'MediaFile' => MediaFileResource::class,
            // Marketing
            'Coupon' => CouponResource::class,
            'AbandonedCart' => AbandonedCartResource::class,
            'Testimonial' => TestimonialResource::class,
            'NewsletterSubscriber' => NewsletterSubscriberResource::class,
            'NewsletterCampaign' => NewsletterCampaignResource::class,
            'EmailLog' => EmailLogResource::class,
            // System
            'Admin' => AdminResource::class,
            'Role' => RoleResource::class,
            'ActivityLog' => ActivityLogResource::class,
            'SearchLog' => SearchLogResource::class,
            'FailedSearchLog' => FailedSearchLogResource::class,
            'LoginLog' => LoginLogResource::class,
            'CronLog' => CronLogResource::class,
            'Translation' => TranslationResource::class,
            'SeoMeta' => SeoMetaResource::class,
            'Language' => LanguageResource::class,
            'Redirect' => RedirectResource::class,
            'IpBlocklist' => IpBlocklistResource::class,
            default => null,
        };

        if ($resourceClass && class_exists($resourceClass)) {
            // Not every resource registers a 'view' page (e.g. MediaFile is
            // index+edit only) — building a URL for an unregistered page
            // throws RouteNotFoundException DURING TABLE RENDER, 500ing the
            // whole list the moment it has rows. Fall back gracefully.
            $pages = $resourceClass::getPages();

            foreach (['view', 'edit'] as $page) {
                if (isset($pages[$page])) {
                    return $resourceClass::getUrl($page, ['record' => $record]);
                }
            }

            return $resourceClass::getUrl('index');
        }

        return '#';
    }
}

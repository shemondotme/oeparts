<?php

namespace App\Filament\Support;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentTransactionStatus;
use App\Enums\RefundStatus;
use Closure;
use Filament\Actions;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\FontWeight;
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
        return static::copyableColumn($name, 'OEM Number', $copyMessage)
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
     * @param  string        $name        Field name (e.g. 'slug')
     * @param  string        $label       Display label
     * @param  string        $sourceCode  Locale code of the source name field (e.g. 'en')
     * @param  string        $sourceField Source field name to watch (e.g. 'name')
     * @param  string|null   $uniqueGuard Column for unique(ignoreRecord:true), or null to skip
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
        $locales ??= static::LOCALES;

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

                                    if (($config['type'] ?? 'text') === 'textarea') {
                                        return \Filament\Forms\Components\Textarea::make("{$fieldName}.{$code}")
                                            ->label($config['label'])
                                            ->required($required)
                                            ->rows($rows ?? 5)
                                            ->placeholder($config['placeholders'][$code] ?? ($code === 'en' ? ($config['placeholder'] ?? null) : null))
                                            ->helperText($code === 'en'
                                                ? ($config['helperText'] ?? null)
                                                : 'Leave blank to fall back to the English value.');
                                    }

                                    if (($config['type'] ?? 'text') === 'richeditor') {
                                        return \Filament\Forms\Components\RichEditor::make("{$fieldName}.{$code}")
                                            ->label($config['label'])
                                            ->nullable()
                                            ->columnSpanFull();
                                    }

                                    $field = \Filament\Forms\Components\TextInput::make("{$fieldName}.{$code}")
                                        ->label($config['label'])
                                        ->required($required)
                                        ->maxLength($maxLength)
                                        ->placeholder($config['placeholders'][$code] ?? ($code === 'en' ? ($config['placeholder'] ?? null) : null))
                                        ->helperText($code === 'en'
                                            ? ($config['helperText'] ?? 'English value is required and used as the default fallback.')
                                            : 'Leave blank to fall back to the English value.');

                                    if ($syncsSlug) {
                                        $field->live(onBlur: true);

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
            ->recordUrl(fn ($record): string => static::getResourceUrl($record));
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
     * @param  string         $name    Action name (used as Livewire key)
     * @param  string         $label   Button label
     * @param  string         $color   Filament color (success, warning, danger, gray, info)
     * @param  string         $icon    Heroicon name
     * @param  Closure        $action  fn(Collection $records, array $data): void
     * @param  Closure|null   $summary fn($record): ?array Returns ['key'=>..., 'old'=>..., 'new'=>..., 'masked'=>bool] or null to skip
     * @param  array<int, Component> $form  Optional modal form schema
     * @param  Closure|null   $visible fn(Collection $records): bool
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
            ->modalDescription(function (Collection $records) use ($label, $summary, $form) {
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
                    return "No records will be affected.";
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

                return view('components.impact-summary', [
                    'changes' => $changes,
                    'heading' => count($changes) . ' ' . lcfirst($label),
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
                $rows = $records->map(fn ($record) => array_map(
                    fn ($accessor) => data_get($record, $accessor, ''),
                    array_keys($columns),
                ));

                $csv = collect($headers)->implode(',') . "\n";
                $rows->each(function ($row) use (&$csv) {
                    $csv .= collect($row)->map(fn ($cell) => '"' . str_replace('"', '""', (string) $cell) . '"')->implode(',') . "\n";
                });

                return Response::streamDownload(fn () => print($csv), 'export-' . now()->format('Y-m-d-His') . '.csv');
            })
            ->authorize(fn (?string $model): bool => $model === null || (auth('admin')->user()?->can('viewAny', $model) ?? false))
            ->deselectRecordsAfterCompletion();
    }

    /**
     * Reusable "Import CSV" header action — file upload + update-existing
     * toggle, dispatches the given queued job. The job class must accept
     * (string $storagePath, int $adminId, bool $updateExisting) as its
     * constructor arguments (matching App\Jobs\ProcessCsvImport's shape).
     */
    public static function importCsvHeaderAction(
        string $jobClass,
        string $modalHeading = 'Import via CSV',
        string $modalDescription = 'Upload a CSV file to bulk import or update records. A background job will process the file asynchronously.',
        string $csvHelperText = 'Upload a CSV file.',
    ): Actions\Action {
        return Actions\Action::make('importCsv')
            ->label('Import CSV')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->modalHeading($modalHeading)
            ->modalDescription($modalDescription)
            ->schema([
                \Filament\Forms\Components\FileUpload::make('csv_file')
                    ->label('CSV File')
                    ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel'])
                    ->required()
                    ->helperText($csvHelperText),
                Toggle::make('update_existing')
                    ->label('Update Existing Records')
                    ->helperText('When enabled, records with matching identifiers will be updated instead of skipped.'),
            ])
            ->action(function (array $data) use ($jobClass): void {
                dispatch(new $jobClass(
                    $data['csv_file'],
                    auth('admin')->id(),
                    $data['update_existing'] ?? false,
                ));

                \Filament\Notifications\Notification::make()
                    ->title('CSV import started')
                    ->body('Processing in background')
                    ->success()
                    ->send();
            });
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
     * @param  string       $resourceClass  Fully-qualified Filament resource class
     * @param  array|null   $tableFilters   Filters to apply, e.g. ['status' => ['value' => 'pending']]
     * @param  string|null  $tableSearch    Search query string
     * @param  string|null  $tableSort      Sort string, e.g. 'created_at:desc'
     */
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
            'Order' => \App\Filament\Resources\OrderResource::class,
            'Payment' => \App\Filament\Resources\PaymentResource::class,
            'RefundRequest' => \App\Filament\Resources\RefundRequestResource::class,
            'ShippingZone' => \App\Filament\Resources\ShippingZoneResource::class,
            'ShippingMethod' => \App\Filament\Resources\ShippingMethodResource::class,
            'Carrier' => \App\Filament\Resources\CarrierResource::class,
            // Catalog
            'Product' => \App\Filament\Resources\ProductResource::class,
            'Manufacturer' => \App\Filament\Resources\ManufacturerResource::class,
            'CarModel' => \App\Filament\Resources\CarModelResource::class,
            'Category' => \App\Filament\Resources\CategoryResource::class,
            'Condition' => \App\Filament\Resources\ConditionResource::class,
            // Customers
            'User' => \App\Filament\Resources\CustomerResource::class,
            'ContactMessage' => \App\Filament\Resources\ContactMessageResource::class,
            'PartInquiry' => \App\Filament\Resources\PartInquiryResource::class,
            // Content
            'BlogPost' => \App\Filament\Resources\BlogPostResource::class,
            'Section' => \App\Filament\Resources\SectionResource::class,
            'Page' => \App\Filament\Resources\PageResource::class,
            'Faq' => \App\Filament\Resources\FaqResource::class,
            'Menu' => \App\Filament\Resources\MenuResource::class,
            'MediaFile' => \App\Filament\Resources\MediaFileResource::class,
            // Marketing
            'Coupon' => \App\Filament\Resources\CouponResource::class,
            'AbandonedCart' => \App\Filament\Resources\AbandonedCartResource::class,
            'Testimonial' => \App\Filament\Resources\TestimonialResource::class,
            'NewsletterSubscriber' => \App\Filament\Resources\NewsletterSubscriberResource::class,
            'NewsletterCampaign' => \App\Filament\Resources\NewsletterCampaignResource::class,
            'EmailLog' => \App\Filament\Resources\EmailLogResource::class,
            // System
            'Admin' => \App\Filament\Resources\AdminResource::class,
            'Role' => \App\Filament\Resources\RoleResource::class,
            'ActivityLog' => \App\Filament\Resources\ActivityLogResource::class,
            'SearchLog' => \App\Filament\Resources\SearchLogResource::class,
            'FailedSearchLog' => \App\Filament\Resources\FailedSearchLogResource::class,
            'LoginLog' => \App\Filament\Resources\LoginLogResource::class,
            'CronLog' => \App\Filament\Resources\CronLogResource::class,
            'Translation' => \App\Filament\Resources\TranslationResource::class,
            'SeoMeta' => \App\Filament\Resources\SeoMetaResource::class,
            'Language' => \App\Filament\Resources\LanguageResource::class,
            'Redirect' => \App\Filament\Resources\RedirectResource::class,
            'IpBlocklist' => \App\Filament\Resources\IpBlocklistResource::class,
            default => null,
        };

        if ($resourceClass && class_exists($resourceClass)) {
            return $resourceClass::getUrl('view', ['record' => $record]);
        }

        return '#';
    }

    /**
     * Topbar Quick-Create registry: key => [label, url, icon, permission].
     *
     * 'permission' preserves the exact checks the old hardcoded quick-create
     * partial used — NOT Filament's canCreate() / Policy 'create' ability,
     * because RolesSeeder has no "create orders" / "create customers"
     * permission (edit implies create-access for those two by design). Using
     * canCreate() here would silently hide Order/Customer quick-create from
     * every non-super_admin role.
     *
     * @var array<string, array{label: string, url: string, icon: string, permission: string}>
     */
    public const QUICK_CREATE_REGISTRY = [
        'order' => [
            'label' => 'Order',
            'url' => '/admin/filament/orders/create',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z" />',
            'permission' => 'edit orders',
        ],
        'product' => [
            'label' => 'Product',
            'url' => '/admin/products/create',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M21 7.5l-2.25-1.313M21 7.5v2.25m0-2.25l-2.25 1.313M3 7.5l2.25-1.313M3 7.5l2.25 1.313M3 7.5v2.25m9 3l2.25-1.313M12 12.75l-2.25-1.313M12 12.75V15m0 6.75l2.25-1.313M12 21.75V19.5m0 2.25l-2.25-1.313m0-16.875L12 2.25l2.25 1.313M21 14.25v2.25l-2.25 1.313m-13.5 0L3 16.5v-2.25" />',
            'permission' => 'create products',
        ],
        'customer' => [
            'label' => 'Customer',
            'url' => '/admin/customers/create',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />',
            'permission' => 'edit customers',
        ],
        'coupon' => [
            'label' => 'Coupon',
            'url' => '/admin/coupons/create',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0c1.1.128 1.907 1.077 1.907 2.185zM9.75 9h.008v.008H9.75V9zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm4.125 4.5h.008v.008h-.008V13.5zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z" />',
            'permission' => 'create coupons',
        ],
        'manufacturer' => [
            'label' => 'Manufacturer',
            'url' => '/admin/manufacturers/create',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />',
            'permission' => 'create manufacturers',
        ],
        'car_model' => [
            'label' => 'Car Model',
            'url' => '/admin/car-models/create',
            'icon' => '<path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 10-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.125-.504 1.125-1.125V11.25c0-.418-.232-.79-.6-.975l-3.75-1.875a1.125 1.125 0 00-.5-.12H14.25M5.25 18.75h-1.5m1.5 0V12m0 6.75h6m6-12V9.75m0-3.75H6.375a1.125 1.125 0 00-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h.75m6-9.75h1.875c.341 0 .67.136.91.378l3.387 3.388c.24.24.378.567.378.909V18a1.125 1.125 0 01-1.125 1.125H18.75" />',
            'permission' => 'create car models',
        ],
    ];

    /**
     * Role-default Quick-Create item keys, most-privileged role first.
     * Mirrors WidgetPreferenceService::ROLE_DEFAULT_DASHBOARDS's exact shape
     * and first-matching-role-wins resolution pattern.
     *
     * @var array<string, list<string>>
     */
    public const ROLE_DEFAULT_QUICK_CREATE = [
        'super_admin' => ['order', 'product', 'customer', 'coupon', 'manufacturer', 'car_model'],
        'admin' => ['order', 'product', 'customer', 'coupon'],
        'manager' => ['order', 'product', 'customer', 'coupon'],
        'catalog_admin' => ['product', 'manufacturer', 'car_model'],
        'support' => ['order', 'customer'],
    ];

    /**
     * Resolve the role-appropriate Quick-Create items for an admin, gating
     * each item on its actual registered permission (super_admin bypasses
     * all checks, matching BasePolicy's convention).
     *
     * @return list<array{label: string, url: string, icon: string}>
     */
    public static function quickCreateItemsFor(\App\Models\Admin $admin): array
    {
        $keys = self::ROLE_DEFAULT_QUICK_CREATE['support'];

        foreach (array_keys(self::ROLE_DEFAULT_QUICK_CREATE) as $role) {
            if ($admin->hasRole($role)) {
                $keys = self::ROLE_DEFAULT_QUICK_CREATE[$role];
                break;
            }
        }

        $items = [];

        foreach ($keys as $key) {
            $entry = self::QUICK_CREATE_REGISTRY[$key] ?? null;

            if ($entry === null) {
                continue;
            }

            if (! $admin->hasRole('super_admin') && ! $admin->hasPermissionTo($entry['permission'])) {
                continue;
            }

            $items[] = [
                'label' => $entry['label'],
                'url' => $entry['url'],
                'icon' => $entry['icon'],
            ];
        }

        return $items;
    }

    /**
     * Role-default sidebar nav group to auto-open on first visit (when the
     * client has no persisted oeparts.navGroup yet). Mirrors
     * ROLE_DEFAULT_QUICK_CREATE / WidgetPreferenceService::ROLE_DEFAULT_DASHBOARDS'
     * exact shape and first-matching-role-wins resolution pattern.
     *
     * @var array<string, string>
     */
    public const ROLE_DEFAULT_NAV_GROUP = [
        'super_admin' => 'Commerce',
        'admin' => 'Commerce',
        'manager' => 'Commerce',
        'catalog_admin' => 'Catalog',
        'support' => 'Customers',
    ];

    /**
     * Resolve the role-appropriate default-open sidebar nav group label.
     */
    public static function defaultNavGroupFor(\App\Models\Admin $admin): string
    {
        foreach (array_keys(self::ROLE_DEFAULT_NAV_GROUP) as $role) {
            if ($admin->hasRole($role)) {
                return self::ROLE_DEFAULT_NAV_GROUP[$role];
            }
        }

        return self::ROLE_DEFAULT_NAV_GROUP['support'];
    }
}

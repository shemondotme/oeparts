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
     * @param  array<string, array{label: string, required: bool, placeholder?: string, helperText?: string}>  $fields  Field definitions
     * @param  array<string, string>|null  $locales  Locale map (defaults to AdminUi::LOCALES)
     */
    public static function translatableTabs(string $tabsLabel, array $fields, ?array $locales = null): Tabs
    {
        $locales ??= static::LOCALES;

        return Tabs::make($tabsLabel)
            ->schema(
                collect($locales)
                    ->map(fn (string $localeLabel, string $code) => Tab::make($localeLabel)
                        ->badge($code === 'en' ? 'Primary' : null)
                        ->schema(
                            collect($fields)
                                ->map(function (array $config, string $fieldName) use ($code) {
                                    $required = ($config['required'] ?? false) && $code === 'en';
                                    $maxLength = $config['maxLength'] ?? 255;
                                    $rows = $config['rows'] ?? null;

                                    if (($config['type'] ?? 'text') === 'textarea') {
                                        return \Filament\Forms\Components\Textarea::make("{$fieldName}.{$code}")
                                            ->label($config['label'])
                                            ->required($required)
                                            ->rows($rows ?? 5)
                                            ->placeholder($code === 'en' ? ($config['placeholder'] ?? null) : null)
                                            ->helperText($code === 'en'
                                                ? ($config['helperText'] ?? null)
                                                : 'Leave blank to fall back to the English value.');
                                    }

                                    return \Filament\Forms\Components\TextInput::make("{$fieldName}.{$code}")
                                        ->label($config['label'])
                                        ->required($required)
                                        ->maxLength($maxLength)
                                        ->placeholder($code === 'en' ? ($config['placeholder'] ?? null) : null)
                                        ->helperText($code === 'en'
                                            ? ($config['helperText'] ?? 'English value is required and used as the default fallback.')
                                            : 'Leave blank to fall back to the English value.');
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
                    return '';
                }

                $changes = $records
                    ->map(fn ($record) => $summary($record))
                    ->filter(fn ($item) => $item !== null)
                    ->values()
                    ->toArray();

                if (empty($changes)) {
                    return '';
                }

                return view('components.impact-summary', [
                    'changes' => $changes,
                    'heading' => count($changes) . ' ' . lcfirst($label),
                ])->render();
            })
            ->action(fn (Collection $records, array $data) => $action($records, $data));
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
}

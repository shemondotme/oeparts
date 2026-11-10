<?php

namespace App\Filament\Pages\Settings;

use App\Filament\Widgets\Migration\WizardSubmit;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\SettingsService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Wizard;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Schemas\Schema;

/**
 * SEO Setup Guide — the handful of settings that decide whether a new site gets found, in the order a person
 * should answer them, with a plain-language reason for each. It writes the very same settings as the SEO Control
 * Center (which stays the place for everything else) and ends on the SEO Audit.
 */
class SeoSetupWizard extends Page
{
    protected string $view = 'filament.pages.settings.seo-setup-wizard';

    protected static ?string $title = 'SEO Setup Guide';

    protected static ?string $slug = 'seo-settings/setup';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array<string, mixed> */
    public ?array $data = [];

    /** field => settings group. Every field here is one the Control Center also edits. */
    private const FIELDS = [
        'canonical_host' => 'seo',
        'default_description' => 'seo',
        'google_verification' => 'seo',
        'bing_verification' => 'seo',
        'default_robots' => 'seo',
        'detail_pages_enabled' => 'seo',
        'thin_content_noindex' => 'seo',
        'thin_content_min_words' => 'seo',
        'audit_alerts_enabled' => 'seo',
        'ai_crawler_tracking' => 'seo',
        'llms_txt_auto' => 'crawlers',
    ];

    private const BOOLEANS = ['detail_pages_enabled', 'thin_content_noindex', 'audit_alerts_enabled', 'ai_crawler_tracking', 'llms_txt_auto'];

    public static function canAccess(): bool
    {
        return auth('admin')->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    public function getSubheading(): ?string
    {
        return 'Four short steps: your address, search engines, what may be indexed, and safeguards. Everything here can be changed later in the SEO Control Center.';
    }

    public function mount(): void
    {
        $this->data = $this->current();
        $this->getSchema('form')->fill($this->data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToSeoSettings')
                ->label('Back to SEO Control Center')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->outlined()
                ->url(SeoControlCenter::getUrl()),
        ];
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])->id('form'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Wizard::make([
                Step::make('Your address')
                    ->description('Which web address is the real one')
                    ->schema([
                        TextInput::make('canonical_host')
                            ->label('Main web address (host name only)')
                            ->placeholder('oeparts.com')
                            ->maxLength(255)
                            ->regex('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/i')
                            ->validationMessages(['regex' => 'Enter only the host name, for example oeparts.com: no https://, no path, no port.'])
                            ->dehydrateStateUsing(fn ($state) => is_string($state) ? strtolower(trim($state)) : $state)
                            ->helperText('Visitors who arrive on any other spelling (www or not) are sent to this one, so search engines see a single site instead of two copies. Leave empty until the domain and its certificate are fully set up.'),
                        Textarea::make('default_description')
                            ->label('Default description for search results')
                            ->rows(2)
                            ->maxLength(160)
                            ->helperText('Used for every page that has no description of its own. One or two sentences on what the shop sells and where it delivers (up to 160 characters).'),
                    ]),

                Step::make('Search engines')
                    ->description('Prove the site is yours')
                    ->schema([
                        TextInput::make('google_verification')
                            ->label('Google Search Console verification code')
                            ->maxLength(255)
                            ->helperText('The value of the "content" in the meta tag Search Console gives you (not the whole tag). It lets you see how Google views the site. Connecting the Search Console account itself is done in the SEO Control Center.'),
                        TextInput::make('bing_verification')
                            ->label('Bing Webmaster Tools verification code')
                            ->maxLength(255)
                            ->helperText('The same for Bing, which also feeds several other search engines.'),
                    ]),

                Step::make('What may be indexed')
                    ->description('The rules for pages without their own choice')
                    ->schema([
                        Select::make('default_robots')
                            ->label('Default for pages')
                            ->options([
                                'index,follow' => 'Index, follow (recommended)',
                                'noindex,follow' => 'No index, follow',
                                'index,nofollow' => 'Index, no follow',
                                'noindex,nofollow' => 'No index, no follow',
                            ])
                            ->native(false)
                            ->required()
                            ->live(),
                        Text::make(fn (Get $get): string => str_contains((string) $get('default_robots'), 'noindex')
                            ? 'Careful: with "no index" as the default, every page that has no choice of its own stays out of search results.'
                            : '')
                            ->color('danger')
                            ->visible(fn (Get $get): bool => str_contains((string) $get('default_robots'), 'noindex')),
                        Toggle::make('detail_pages_enabled')
                            ->label('Give every part its own page as well')
                            ->helperText('Off: a part number has one page listing all offers. On: a part number with a single offer goes straight to that offer\'s own page. Choose before launch — changing it later moves addresses around.'),
                        Toggle::make('thin_content_noindex')
                            ->label('Keep pages and posts with almost no text out of search')
                            ->helperText('Very short pages rarely deserve a place in results and can make the site look low-quality.')
                            ->live(),
                        TextInput::make('thin_content_min_words')
                            ->label('Minimum words')
                            ->numeric()->minValue(10)->maxValue(2000)
                            ->visible(fn (Get $get): bool => (bool) $get('thin_content_noindex')),
                    ]),

                Step::make('Safeguards')
                    ->description('So problems get noticed')
                    ->schema([
                        Toggle::make('audit_alerts_enabled')
                            ->label('Tell the admins about critical SEO problems')
                            ->helperText('The audit runs every morning; a serious problem (like the site being hidden from search) arrives as a notification and an email.'),
                        Toggle::make('ai_crawler_tracking')
                            ->label('Count visits by AI crawlers')
                            ->helperText('One daily counter per crawler, no addresses or pages. Shown on the AI Crawlers page.'),
                        Toggle::make('llms_txt_auto')
                            ->label('List the main pages, posts and brands in llms.txt')
                            ->helperText('Helps AI assistants find the useful pages of the site.'),
                    ]),

                Step::make('Finish')
                    ->description('Save and check')
                    ->schema([
                        Text::make(fn (): string => $this->summary()),
                    ]),
            ])
                ->alpineSubmitHandler('$wire.finish()')
                ->submitAction(WizardSubmit::button('Save and run the SEO audit', 'finish', 'primary', 'heroicon-o-check'))
                ->skippable(),
        ]);
    }

    /** What saving would change, in words. */
    public function summary(): string
    {
        $changes = $this->changes();

        if ($changes === []) {
            return 'Everything already matches the current settings — nothing to save. The audit will still run.';
        }

        return 'Saving changes '.count($changes).' setting(s): '.implode('; ', array_map(
            fn (array $c): string => $c['label'].' → '.$c['new'],
            $changes
        )).'.';
    }

    public function finish(): void
    {
        $this->getSchema('form')->validate();

        $changes = $this->changes();
        $service = app(SettingsService::class);
        $old = [];
        $new = [];

        foreach ($changes as $key => $change) {
            $service->set(self::FIELDS[$key].'.'.$key, $change['value']);
            $old[$key] = $change['old'];
            $new[$key] = $change['value'];
        }

        if ($changes !== [] && ($admin = auth('admin')->user())) {
            ActivityLog::create([
                'admin_id' => $admin->id,
                'action' => 'settings_updated',
                'model_type' => Setting::class,
                'model_id' => null,
                'old_values' => $old,
                'new_values' => $new,
                'ip_address' => request()->ip(),
                'created_at' => now(),
            ]);
        }

        Notification::make()
            ->title($changes === [] ? 'Nothing to change' : 'Saved '.count($changes).' setting(s)')
            ->success()
            ->send();

        $this->redirect(SeoAudit::getUrl());
    }

    // ── internals ─────────────────────────────────────────────────────────

    /** @return array<string, mixed> the stored values, ready for the form */
    private function current(): array
    {
        $stored = Setting::query()
            ->where(fn ($q) => $q->where('group', 'seo')->orWhere('group', 'crawlers'))
            ->whereIn('key', array_keys(self::FIELDS))
            ->get()
            ->mapWithKeys(fn (Setting $s) => [$s->key => $s->value]);

        $values = [];
        foreach (self::FIELDS as $key => $group) {
            $raw = $stored[$key] ?? null;
            $values[$key] = in_array($key, self::BOOLEANS, true)
                ? filter_var($raw, FILTER_VALIDATE_BOOLEAN)
                : ($key === 'thin_content_min_words' ? (int) ($raw ?: 100) : (string) ($raw ?? ''));
        }
        $values['default_robots'] = $values['default_robots'] ?: 'index,follow';

        return $values;
    }

    /** @return array<string, array{label: string, old: string, new: string, value: string}> only the fields that differ */
    private function changes(): array
    {
        $current = $this->current();
        $state = $this->data ?? [];
        $changes = [];

        foreach (self::FIELDS as $key => $group) {
            if (! array_key_exists($key, $state)) {
                continue;
            }

            $value = $state[$key];
            if (in_array($key, self::BOOLEANS, true)) {
                $value = (bool) $value;
                $store = $value ? 'true' : 'false';
                $show = $value ? 'on' : 'off';
                $was = $current[$key] ? 'on' : 'off';
            } elseif ($key === 'thin_content_min_words') {
                $value = (int) $value;
                $store = $show = (string) $value;
                $was = (string) $current[$key];
            } else {
                $value = is_string($value) ? trim($value) : (string) $value;
                $value = $key === 'canonical_host' ? strtolower($value) : $value;
                $store = $show = $value;
                $was = (string) $current[$key];
            }

            if ($value === $current[$key]) {
                continue;
            }

            $changes[$key] = [
                'label' => str_replace('_', ' ', $key),
                'old' => $was,
                'new' => $show === '' ? '(empty)' : $show,
                'value' => $store,
            ];
        }

        return $changes;
    }
}

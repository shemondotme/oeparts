<?php

namespace App\Filament\Support;

use App\Services\ContentAnalyzer;
use App\Support\LocaleRegistry;
use Filament\Forms;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\HtmlString;

/**
 * The one SEO editor block for every content type that has its own
 * translatable meta title / description (CMS pages, blog posts, brands, car
 * models): a tab per locale with the two fields, a live Google-style search
 * preview (desktop/mobile) and length meters — what Yoast and Rank Math put
 * under every editor.
 *
 * The preview shows what Google would actually show: the editor's own text
 * when written, otherwise the same fallback the storefront uses (so an empty
 * field is never shown as a blank snippet). Meters count the EDITOR'S text
 * only; a fallback is labelled as such.
 */
final class SeoFormFields
{
    public const TITLE_MIN = 30;

    public const TITLE_MAX = 60;

    public const DESC_MIN = 70;

    public const DESC_MAX = 160;

    /**
     * @param  array{
     *     url: \Closure(Get, string): string,
     *     title: \Closure(Get, string): string,
     *     description: \Closure(Get, string): string,
     * }  $fallbacks  What the storefront shows when the field is empty, plus the page's public URL, per locale code.
     */
    /** The fallbacks of the editor block built last — the social preview in the same form reuses them. */
    private static array $lastFallbacks = [];

    public static function localizedMetaTabs(array $fallbacks, string $name = 'SeoLocales', bool $withAnalysis = false): Tabs
    {
        self::$lastFallbacks = $fallbacks;

        return Tabs::make($name)
            ->schema(
                collect(AdminUi::LOCALES)
                    ->map(fn (string $label, string $code) => Tab::make($label)
                        ->schema(array_filter([
                            // Written content (pages, posts): the phrase this text should be found for.
                            $withAnalysis
                                ? Forms\Components\TextInput::make("focus_keyword.$code")
                                    ->label('Focus keyword')
                                    ->maxLength(100)
                                    ->nullable()
                                    ->live(debounce: 500)
                                    ->helperText('The phrase you want this page to be found for in '.$label.' (e.g. "brake pads"). The analysis below checks it is used where search engines look.')
                                : null,
                            Forms\Components\TextInput::make("meta_title.$code")
                                ->label(__('admin.meta_title'))
                                ->maxLength(255)
                                ->nullable()
                                ->live(debounce: 400)
                                ->placeholder(fn (Get $get): string => $fallbacks['title']($get, $code))
                                ->helperText('Aim for 30–60 characters. Leave empty to use the automatic title shown as the placeholder.'),
                            Forms\Components\Textarea::make("meta_description.$code")
                                ->label(__('admin.meta_description'))
                                ->rows(3)
                                ->maxLength(500)
                                ->nullable()
                                ->live(debounce: 400)
                                ->placeholder(fn (Get $get): string => $fallbacks['description']($get, $code))
                                ->helperText('Aim for 70–160 characters. Leave empty to use the automatic description shown as the placeholder.'),
                            Forms\Components\Placeholder::make("serp_preview_$code")
                                ->label('Search result preview')
                                ->content(fn (Get $get): HtmlString => self::preview($get, $code, $fallbacks)),
                            $withAnalysis
                                ? Forms\Components\Placeholder::make("content_analysis_$code")
                                    ->label('Content analysis')
                                    ->content(fn (Get $get): HtmlString => self::analysis($get, $code, $fallbacks))
                                : null,
                        ])))
                    ->values()
                    ->all()
            )
            ->columnSpanFull();
    }

    /**
     * "How it looks when shared" (Facebook / LinkedIn / WhatsApp style card) for the Advanced SEO section. Uses
     * the section's own social title / description / image, falling back to the same automatic text the
     * search preview uses (English tab) and — for the image — to what the page would really share.
     *
     * Must be placed AFTER localizedMetaTabs() in the same form: it reuses that block's fallbacks.
     */
    public static function socialPreview(string $imageFallbackNote = 'The site-wide default image is shared'): Group
    {
        $fallbacks = self::$lastFallbacks;

        $preview = Forms\Components\Placeholder::make('social_preview')
            ->label('Share preview')
            ->content(function (Get $get, $livewire) use ($fallbacks, $imageFallbackNote): HtmlString {
                // The fallbacks read the form's own fields (title, slug, content…) by plain name, but this
                // field sits inside the seoMeta relationship — so read them from the page's form data root
                // (the live values, as typed).
                $root = new class($livewire)
                {
                    public function __construct(private $livewire) {}

                    public function __invoke(string $path = ''): mixed
                    {
                        return data_get((array) ($this->livewire->data ?? []), $path);
                    }
                };

                // Same order the storefront uses: the social text, then the page's own search title / description, then the automatic text.
                // The language shown is the one picked above the card (the social title / description / image
                // themselves are the same in every language).
                $code = (string) ($get('social_preview_locale') ?: LocaleRegistry::defaultCode());
                $title = trim((string) $get('og_title')) ?: trim((string) $root("meta_title.$code")) ?: ($fallbacks ? $fallbacks['title']($root, $code) : '');
                $description = trim((string) $get('og_description')) ?: trim((string) $root("meta_description.$code")) ?: ($fallbacks ? $fallbacks['description']($root, $code) : '');
                $url = $fallbacks ? $fallbacks['url']($root, $code) : url('/');

                $imageId = $get('og_image_id');
                $image = $imageId ? \App\Models\MediaFile::find($imageId)?->file_url : null;

                return new HtmlString(view('filament.components.social-preview', [
                    'title' => $title,
                    'description' => $description,
                    'url' => $url,
                    'image' => $image,
                    'imageNote' => $imageFallbackNote,
                ])->render());
            });

        return Group::make([
            Forms\Components\Select::make('social_preview_locale')
                ->label('Preview language')
                ->options(collect(LocaleRegistry::languages())->mapWithKeys(fn (array $l): array => [$l['code'] => $l['native_name'] ?: $l['name']])->all())
                ->default(LocaleRegistry::defaultCode())
                ->native(false)
                ->selectablePlaceholder(false)
                ->live()
                ->dehydrated(false),
            $preview,
        ]);
    }

    /**
     * The SEO score shown in the Pages / Blog lists: the default language's text, focus keyword when one is set, content-only
     * scoring when it is not. A few queries-free string checks per row — fine for a paginated list.
     *
     * @return array{score: int, label: string}
     */
    public static function listScore(\Illuminate\Database\Eloquent\Model $record): array
    {
        // The site's default language (not a hard-coded English): that is the text the shop is judged on first.
        $code = \App\Support\LocaleRegistry::defaultCode();
        $field = fn (string $attribute): string => trim((string) (((array) $record->{$attribute})[$code] ?? ''));

        $result = app(ContentAnalyzer::class)->analyze(
            $field('focus_keyword'),
            $field('meta_title'),
            $field('meta_description'),
            (string) $record->slug,
            ((array) $record->content)[$code] ?? '',
            $field('title'),
            requireKeyword: false,
        );

        return ['score' => $result['score'], 'label' => $result['label']];
    }

    /**
     * The on-page analysis for one language tab: focus keyword placement, length, structure, readability.
     * Uses the text that would actually be shown (the editor's own title/description, else the automatic one).
     */
    private static function analysis(Get $get, string $code, array $fallbacks): HtmlString
    {
        $title = trim((string) $get("meta_title.$code")) ?: $fallbacks['title']($get, $code);
        $description = trim((string) $get("meta_description.$code")) ?: $fallbacks['description']($get, $code);

        $result = app(ContentAnalyzer::class)->analyze(
            (string) $get("focus_keyword.$code"),
            $title,
            $description,
            (string) $get('slug'),
            $get("content.$code"),
            (string) $get("title.$code"),
        );

        return new HtmlString(view('filament.components.content-analysis', ['analysis' => $result])->render());
    }

    /**
     * Plain text of a rich-editor state — which is HTML while stored but a Tiptap document (an array) while the
     * form is open, and a bare (string) cast of an array fails.
     */
    public static function plainText(mixed $state): string
    {
        $html = app(ContentAnalyzer::class)->toHtml($state);

        return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace('<', ' <', $html)), ENT_QUOTES | ENT_HTML5)));
    }

    private static function preview(Get $get, string $code, array $fallbacks): HtmlString
    {
        $ownTitle = trim((string) $get("meta_title.$code"));
        $ownDescription = trim((string) $get("meta_description.$code"));

        $title = $ownTitle !== '' ? $ownTitle : $fallbacks['title']($get, $code);
        $description = $ownDescription !== '' ? $ownDescription : $fallbacks['description']($get, $code);

        return new HtmlString(view('filament.components.serp-preview', [
            'title' => $title,
            'description' => $description,
            'url' => $fallbacks['url']($get, $code),
            'titleStatus' => self::status(mb_strlen($ownTitle), self::TITLE_MIN, self::TITLE_MAX),
            'descStatus' => self::status(mb_strlen($ownDescription), self::DESC_MIN, self::DESC_MAX),
        ])->render());
    }

    /**
     * @return array{length: int, label: string, color: 'good'|'warn'|'bad'|'muted'}
     */
    public static function status(int $length, int $min, int $max): array
    {
        return match (true) {
            $length === 0 => ['length' => 0, 'label' => 'using the automatic text', 'color' => 'muted'],
            $length < $min => ['length' => $length, 'label' => 'a little short', 'color' => 'warn'],
            $length <= $max => ['length' => $length, 'label' => 'good', 'color' => 'good'],
            default => ['length' => $length, 'label' => 'too long, will be cut off', 'color' => 'bad'],
        };
    }
}

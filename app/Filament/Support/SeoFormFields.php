<?php

namespace App\Filament\Support;

use App\Services\ContentAnalyzer;
use Filament\Forms;
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
    public static function localizedMetaTabs(array $fallbacks, string $name = 'SeoLocales', bool $withAnalysis = false): Tabs
    {
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

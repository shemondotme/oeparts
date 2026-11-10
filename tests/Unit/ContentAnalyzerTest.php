<?php

namespace Tests\Unit;

use App\Services\ContentAnalyzer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/** Keyword placement, length, structure and readability — accent- and case-insensitive, language-neutral. */
class ContentAnalyzerTest extends TestCase
{
    private function checkStatus(array $result, string $id): ?string
    {
        foreach ($result['checks'] as $check) {
            if ($check['id'] === $id) {
                return $check['status'];
            }
        }

        return null;
    }

    private function body(string $keyword = 'brake pads', int $paragraphs = 8): string
    {
        $html = "<p>Choosing {$keyword} is simple when you know what to look for. This short guide explains it clearly.</p>";
        $html .= '<h2>How to pick '.$keyword.'</h2>';
        for ($i = 0; $i < $paragraphs; $i++) {
            $html .= '<p>Always check the fit for your exact model before you order. Compare the part number with the old one. Ask us if anything is unclear.</p>';
        }
        $html .= '<p>See also <a href="/en/brands">our brands</a>.</p>';

        return $html;
    }

    #[Test]
    public function a_well_optimised_text_scores_good(): void
    {
        $result = (new ContentAnalyzer)->analyze('brake pads', 'Brake pads: how to choose the right set', 'Learn how to choose brake pads that fit your car, with part-number tips.', 'how-to-choose-brake-pads', $this->body());

        foreach (['keyword_title', 'keyword_description', 'keyword_slug', 'keyword_intro', 'keyword_heading', 'internal_links', 'sentences'] as $id) {
            $this->assertSame('good', $this->checkStatus($result, $id), $id);
        }
        $this->assertSame('good', $this->checkStatus($result, 'keyword_density'));
        $this->assertGreaterThanOrEqual(75, $result['score']);
        $this->assertSame('Good', $result['label']);
    }

    #[Test]
    public function without_a_keyword_the_keyword_checks_are_skipped_and_a_prompt_is_shown(): void
    {
        $result = (new ContentAnalyzer)->analyze('', 'Title', 'Desc', 'slug', $this->body());

        $this->assertSame('bad', $this->checkStatus($result, 'keyword_set'));
        $this->assertNull($this->checkStatus($result, 'keyword_title'));
        $this->assertNotNull($this->checkStatus($result, 'length'), 'content checks still run');
    }

    #[Test]
    public function matching_ignores_case_and_accents(): void
    {
        $result = (new ContentAnalyzer)->analyze('Škoda Bremsscheibe', 'SKODA BREMSSCHEIBE günstig kaufen', 'Alle skoda bremsscheibe Modelle.', 'skoda-bremsscheibe', '<p>Die škoda bremsscheibe passt.</p>');

        $this->assertSame('good', $this->checkStatus($result, 'keyword_title'));
        $this->assertSame('good', $this->checkStatus($result, 'keyword_description'));
        $this->assertSame('good', $this->checkStatus($result, 'keyword_slug'));
        $this->assertSame('good', $this->checkStatus($result, 'keyword_intro'));
    }

    #[Test]
    public function a_keyword_missing_from_title_description_and_slug_is_flagged(): void
    {
        $result = (new ContentAnalyzer)->analyze('brake pads', 'Welcome', 'Nothing relevant here.', 'welcome', $this->body('rotors'));

        $this->assertSame('bad', $this->checkStatus($result, 'keyword_title'));
        $this->assertSame('warn', $this->checkStatus($result, 'keyword_description'));
        $this->assertSame('warn', $this->checkStatus($result, 'keyword_slug'));
        $this->assertSame('bad', $this->checkStatus($result, 'keyword_density'), 'does not appear in the text at all');
    }

    #[Test]
    public function keyword_stuffing_is_caught(): void
    {
        $stuffed = '<p>'.str_repeat('brake pads cheap brake pads best brake pads buy brake pads now. ', 12).'</p>';

        $this->assertSame('bad', $this->checkStatus((new ContentAnalyzer)->analyze('brake pads', 'Brake pads', 'Brake pads', 'brake-pads', $stuffed), 'keyword_density'));
    }

    #[Test]
    public function length_is_judged_in_three_bands(): void
    {
        $analyze = fn (string $html) => (new ContentAnalyzer)->analyze('x', 'x', 'x', 'x', $html);

        $this->assertSame('bad', $this->checkStatus($analyze('<p>'.str_repeat('word ', 40).'</p>'), 'length'));
        $this->assertSame('warn', $this->checkStatus($analyze('<p>'.str_repeat('word ', 200).'</p>'), 'length'));
        $this->assertSame('good', $this->checkStatus($analyze('<p>'.str_repeat('word ', 320).'</p>'), 'length'));
    }

    #[Test]
    public function long_sentences_and_a_long_text_without_subheadings_are_flagged(): void
    {
        $longSentence = '<p>'.str_repeat('word ', 40).'end. '.str_repeat('more ', 40).'end. '.str_repeat('again ', 40).'end. '.str_repeat('text ', 40).'end. '.str_repeat('words ', 40).'end. '.str_repeat('and ', 40).'end. '.str_repeat('so ', 40).'end. '.str_repeat('on ', 40).'end.</p>';

        $result = (new ContentAnalyzer)->analyze('word', 'word', 'word', 'word', $longSentence);

        $this->assertSame('bad', $this->checkStatus($result, 'sentences'));
        $this->assertSame('warn', $this->checkStatus($result, 'structure'));
        $this->assertSame('warn', $this->checkStatus($result, 'paragraphs'));
    }

    #[Test]
    public function internal_links_count_but_external_and_mail_links_do_not(): void
    {
        $with = (new ContentAnalyzer)->analyze('x', 'x', 'x', 'x', '<p>a <a href="/en/brands">b</a></p>');
        $without = (new ContentAnalyzer)->analyze('x', 'x', 'x', 'x', '<p>a <a href="https://example.org">b</a> <a href="mailto:a@b.c">m</a></p>');

        $this->assertSame('good', $this->checkStatus($with, 'internal_links'));
        $this->assertSame('warn', $this->checkStatus($without, 'internal_links'));
    }

    #[Test]
    public function the_keyword_in_an_image_description_is_recognised_when_images_exist(): void
    {
        $has = (new ContentAnalyzer)->analyze('brake pads', 'brake pads', 'brake pads', 'brake-pads', '<p>x</p><img src="a.jpg" alt="Front brake pads set">');
        $lacks = (new ContentAnalyzer)->analyze('brake pads', 'brake pads', 'brake pads', 'brake-pads', '<p>x</p><img src="a.jpg" alt="photo"><img src="b.jpg">');
        $none = (new ContentAnalyzer)->analyze('brake pads', 'brake pads', 'brake pads', 'brake-pads', '<p>x</p>');

        $this->assertSame('good', $this->checkStatus($has, 'keyword_image'));
        $this->assertSame('warn', $this->checkStatus($lacks, 'keyword_image'));
        $this->assertNull($this->checkStatus($none, 'keyword_image'));
    }

    #[Test]
    public function a_tiptap_document_is_analysed_like_the_equivalent_html(): void
    {
        $doc = ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Brake pads explained.']]],
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Choosing brake pads']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'see ', 'marks' => []], ['type' => 'text', 'text' => 'brands', 'marks' => [['type' => 'link', 'attrs' => ['href' => '/en/brands']]]]]],
        ]];

        $result = (new ContentAnalyzer)->analyze('brake pads', 'brake pads', 'brake pads', 'brake-pads', $doc);

        $this->assertSame('good', $this->checkStatus($result, 'keyword_intro'));
        $this->assertSame('good', $this->checkStatus($result, 'keyword_heading'));
        $this->assertSame('good', $this->checkStatus($result, 'internal_links'));
    }

    #[Test]
    public function empty_or_null_content_never_throws_and_scores_low(): void
    {
        foreach ([null, '', [], '<p></p>'] as $content) {
            $result = (new ContentAnalyzer)->analyze(null, null, null, null, $content);
            $this->assertLessThan(45, $result['score']);
            $this->assertSame('Needs work', $result['label']);
        }
    }
}

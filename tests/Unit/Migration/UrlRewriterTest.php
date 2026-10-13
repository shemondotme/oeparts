<?php

namespace Tests\Unit\Migration;

use App\Services\Migration\Rewrite\UrlRewriter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UrlRewriterTest extends TestCase
{
    private function rewriter(string|array $from = 'old.example', string $to = 'https://new.example'): UrlRewriter
    {
        return new UrlRewriter($from, $to);
    }

    // ---- the four forms -------------------------------------------------------------------------------

    #[Test]
    #[DataProvider('rewritten')]
    public function it_rewrites_every_form_an_address_of_the_old_site_takes(string $input, string $expected): void
    {
        $this->assertSame($expected, $this->rewriter()->rewrite($input));
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function rewritten(): array
    {
        return [
            'https' => ['<a href="https://old.example/parts/1">x</a>', '<a href="https://new.example/parts/1">x</a>'],
            'http becomes the scheme of the new address' => ['http://old.example/', 'https://new.example/'],
            'www is the same site' => ['https://www.old.example/p', 'https://new.example/p'],
            'upper case host and scheme' => ['HTTPS://OLD.EXAMPLE/P', 'https://new.example/P'],
            'with a port' => ['http://old.example:8080/p', 'https://new.example/p'],
            'json escaped' => ['{"u":"https:\/\/old.example\/p"}', '{"u":"https:\/\/new.example\/p"}'],
            'url encoded in a query string' => ['/r?to=https%3A%2F%2Fold.example%2Fp', '/r?to=https%3A%2F%2Fnew.example%2Fp'],
            'url encoded lower case' => ['/r?to=https%3a%2f%2fold.example%2fp', '/r?to=https%3a%2f%2fnew.example%2fp'],
            'protocol relative' => ['<img src="//old.example/a.png">', '<img src="//new.example/a.png">'],
            'protocol relative after an equals sign' => ['src=//old.example/a.png', 'src=//new.example/a.png'],
            'protocol relative in css url()' => ['background:url(//old.example/a.png)', 'background:url(//new.example/a.png)'],
            'protocol relative json escaped' => ['{"u":"\/\/old.example\/a.png"}', '{"u":"\/\/new.example\/a.png"}'],
            'two in one text' => ['a https://old.example/1 b http://www.old.example/2', 'a https://new.example/1 b https://new.example/2'],
            'at the very end of the text' => ['see https://old.example', 'see https://new.example'],
            'followed by a full stop that ends the sentence' => ['Visit https://old.example.', 'Visit https://new.example.'],
            'followed by a query' => ['https://old.example?x=1', 'https://new.example?x=1'],
            'followed by a hash' => ['https://old.example#top', 'https://new.example#top'],
            'in a srcset' => ['https://old.example/a.png 1x, https://old.example/b.png 2x', 'https://new.example/a.png 1x, https://new.example/b.png 2x'],
        ];
    }

    #[Test]
    public function the_new_authority_keeps_its_own_port_and_an_encoded_colon_is_encoded(): void
    {
        $rewriter = $this->rewriter('old.example', 'http://new.example:8080');

        $this->assertSame('http://new.example:8080/p', $rewriter->rewrite('https://old.example/p'));
        $this->assertSame('/r?u=http%3A%2F%2Fnew.example%3A8080%2Fp', $rewriter->rewrite('/r?u=https%3A%2F%2Fold.example%2Fp'));
    }

    // ---- what must not be touched ----------------------------------------------------------------------------

    #[Test]
    #[DataProvider('untouched')]
    public function it_leaves_alone_what_only_looks_like_the_old_address(string $input): void
    {
        $this->assertSame($input, $this->rewriter()->rewrite($input));
    }

    /** @return array<string, array{0: string}> */
    public static function untouched(): array
    {
        return [
            'a longer host that starts the same' => ['https://old.example.evil.com/p'],
            'a longer host with a dash' => ['https://old.example-shop.com/p'],
            'a longer host with an underscore' => ['https://old.example_backup/p'],
            'a sub-domain of the old host' => ['https://shop.old.example/p'],
            'a host that ends the same' => ['https://notold.example/p'],
            'an email address' => ['write to info@old.example please'],
            'an email address behind mailto' => ['<a href="mailto:info@old.example">mail</a>'],
            'a bare mention with no scheme' => ['we used to be at old.example before'],
            'user info in front of the host' => ['https://old.example@evil.com/p'],
            'a protocol-relative after another address' => ['https://evil.com//old.example/p'],
            'a different scheme' => ['ftp://old.example/p'],
            'nothing at all' => ['no address here'],
            'an empty string' => [''],
        ];
    }

    #[Test]
    public function an_address_that_ends_the_host_with_a_digit_port_boundary_is_respected(): void
    {
        $this->assertSame('https://old.example:808000/p', $this->rewriter()->rewrite('https://old.example:808000/p'), 'a six digit "port" is not a port');
    }

    // ---- a host written with a port --------------------------------------------------------------------------

    #[Test]
    public function an_old_host_written_with_a_port_matches_only_that_port(): void
    {
        $rewriter = $this->rewriter('localhost:8000');

        $this->assertSame('https://new.example/p', $rewriter->rewrite('http://localhost:8000/p'));
        $this->assertSame('http://localhost:8001/p', $rewriter->rewrite('http://localhost:8001/p'));
        $this->assertSame('http://localhost/p', $rewriter->rewrite('http://localhost/p'));
    }

    #[Test]
    public function several_old_names_are_all_rewritten(): void
    {
        $rewriter = $this->rewriter(['old.example', 'staging.old.example', '192.168.1.5:8000']);

        $this->assertSame(
            'https://new.example/a https://new.example/b https://new.example/c',
            $rewriter->rewrite('https://old.example/a http://staging.old.example/b http://192.168.1.5:8000/c'),
        );
    }

    #[Test]
    public function the_old_address_may_be_given_as_a_full_url_with_www(): void
    {
        $this->assertSame(['old.example'], $this->rewriter('https://www.Old.Example/some/path')->fromHosts());
    }

    // ---- idempotence and counting --------------------------------------------------------------------------

    #[Test]
    public function rewriting_twice_changes_nothing_the_second_time(): void
    {
        $text = '<a href="http://www.old.example/p">a</a> <img src="//old.example/i.png"> {"u":"https:\/\/old.example\/x"} ?u=https%3A%2F%2Fold.example';
        $rewriter = $this->rewriter();

        $once = $rewriter->rewrite($text);
        $count = 0;
        $twice = $rewriter->rewrite($once, $count);

        $this->assertSame($once, $twice);
        $this->assertSame(0, $count);
    }

    #[Test]
    public function a_new_host_that_contains_the_old_one_is_not_rewritten_again(): void
    {
        $rewriter = $this->rewriter('shop.test', 'https://new.shop.test');

        $once = $rewriter->rewrite('https://shop.test/a');

        $this->assertSame('https://new.shop.test/a', $once);
        $this->assertSame($once, $rewriter->rewrite($once));
    }

    #[Test]
    public function the_same_old_and_new_host_is_a_no_op_that_only_upgrades_nothing(): void
    {
        $rewriter = $this->rewriter('shop.example', 'https://shop.example');

        $this->assertSame('https://shop.example/p', $rewriter->rewrite('https://shop.example/p'));
        $this->assertSame('https://shop.example/p', $rewriter->rewrite('http://www.shop.example/p'));
    }

    #[Test]
    public function it_counts_what_it_replaced(): void
    {
        $count = 0;
        $this->rewriter()->rewrite('https://old.example/a //old.example/b https://other.example', $count);

        $this->assertSame(2, $count);
    }

    #[Test]
    public function would_change_says_whether_a_text_needs_rewriting(): void
    {
        $this->assertTrue($this->rewriter()->wouldChange('https://old.example/p'));
        $this->assertFalse($this->rewriter()->wouldChange('https://new.example/p'));
        $this->assertFalse($this->rewriter()->wouldChange('info@old.example'));
    }

    #[Test]
    public function the_prefilter_needles_are_the_bare_old_hosts_without_a_port(): void
    {
        $this->assertSame(['staging.old.example', 'localhost', 'old.example'], $this->rewriter(['old.example', 'staging.old.example', 'localhost:8000'])->prefilterNeedles());
    }

    // ---- JSON -----------------------------------------------------------------------------------------------

    #[Test]
    public function json_stays_valid_and_only_string_values_change(): void
    {
        $raw = json_encode(['logo' => 'https://old.example/l.png', 'n' => 5, 'list' => ['http://www.old.example/a', true, null], 'old.example' => 'key is kept']);

        $outcome = $this->rewriter()->rewriteJson((string) $raw);

        $this->assertFalse($outcome->invalidJson);
        $this->assertSame(2, $outcome->replacements);
        $decoded = json_decode($outcome->text, true);
        $this->assertSame('https://new.example/l.png', $decoded['logo']);
        $this->assertSame('https://new.example/a', $decoded['list'][0]);
        $this->assertSame(5, $decoded['n']);
        $this->assertSame('key is kept', $decoded['old.example']);
        $this->assertTrue($outcome->changed((string) $raw));
    }

    #[Test]
    public function json_with_nothing_to_rewrite_comes_back_untouched(): void
    {
        $raw = '{"a":"https://new.example/x","b":[1,2]}';

        $outcome = $this->rewriter()->rewriteJson($raw);

        $this->assertSame($raw, $outcome->text);
        $this->assertSame(0, $outcome->replacements);
        $this->assertFalse($outcome->changed($raw));
    }

    #[Test]
    public function a_rewrite_that_would_change_the_json_structure_is_refused_and_counted(): void
    {
        // The address sits inside a KEY: rewriting it would rename the key, which is not "only string values changed".
        $raw = '{"https://old.example/a":"x"}';

        $outcome = $this->rewriter()->rewriteJson($raw);

        $this->assertTrue($outcome->invalidJson);
        $this->assertSame($raw, $outcome->text, 'the original is kept');
        $this->assertFalse($outcome->changed($raw));
    }

    #[Test]
    public function text_that_is_not_json_is_rewritten_as_plain_text_by_the_json_method(): void
    {
        $outcome = $this->rewriter()->rewriteJson('not json https://old.example/a');

        $this->assertSame('not json https://new.example/a', $outcome->text);
        $this->assertFalse($outcome->invalidJson);
    }

    // ---- construction -----------------------------------------------------------------------------------------

    #[Test]
    #[DataProvider('badTargets')]
    public function the_new_address_must_be_a_full_http_url(string $to): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->rewriter('old.example', $to);
    }

    /** @return array<string, array{0: string}> */
    public static function badTargets(): array
    {
        return ['bare host' => ['new.example'], 'ftp' => ['ftp://new.example'], 'empty' => [''], 'scheme only' => ['https://']];
    }

    #[Test]
    public function there_must_be_an_old_address(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new UrlRewriter(['', '  '], 'https://new.example');
    }

    // ---- real-looking content --------------------------------------------------------------------------------

    #[Test]
    public function a_realistic_page_body_is_rewritten_where_it_should_be_and_nowhere_else(): void
    {
        $html = <<<'HTML'
<p>Questions? <a href="mailto:sales@old.example">sales@old.example</a> or call us.</p>
<img src="http://old.example/storage/media/a.jpg" srcset="//old.example/storage/media/a@2x.jpg 2x">
<a href="https://old.example/en/p/abc">part</a> and <a href="https://partner.example/?ref=old.example">partner</a>
<script>fetch("https:\/\/old.example\/api\/v1\/x")</script>
HTML;

        $out = $this->rewriter()->rewrite($html);

        $this->assertStringContainsString('mailto:sales@old.example', $out);
        $this->assertStringContainsString('>sales@old.example<', $out);
        $this->assertStringContainsString('src="https://new.example/storage/media/a.jpg"', $out);
        $this->assertStringContainsString('srcset="//new.example/storage/media/a@2x.jpg 2x"', $out);
        $this->assertStringContainsString('href="https://new.example/en/p/abc"', $out);
        $this->assertStringContainsString('?ref=old.example', $out);
        $this->assertStringContainsString('"https:\/\/new.example\/api\/v1\/x"', $out);
        $this->assertStringNotContainsString('http://old.example', $out);
    }

    #[Test]
    public function a_very_large_value_with_many_addresses_is_rewritten_completely(): void
    {
        $unit = '<a href="https://old.example/p/%d">link</a> text // and https://other.example/x ';
        $text = '';
        for ($i = 0; $i < 40000; $i++) {
            $text .= sprintf($unit, $i);
        }
        $this->assertGreaterThan(2_000_000, strlen($text));

        $count = 0;
        $out = $this->rewriter()->rewrite($text, $count);

        $this->assertSame(40000, $count);
        $this->assertStringNotContainsString('old.example', $out);
        $this->assertSame(substr_count($text, 'other.example'), substr_count($out, 'other.example'));
    }
}

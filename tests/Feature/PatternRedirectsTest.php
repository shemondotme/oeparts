<?php

namespace Tests\Feature;

use App\Enums\RedirectType;
use App\Models\Redirect;
use App\Services\RedirectPattern;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Wildcard / regex redirects and 410 Gone: one rule replaces thousands of
 * single rows; a rule can never redirect a visitor to a URL it matches
 * again; and a pattern can never break a request.
 */
class PatternRedirectsTest extends TestCase
{
    use RefreshDatabase;

    private function rule(string $from, string $to, string $match, RedirectType $type = RedirectType::Permanent): Redirect
    {
        return Redirect::create(['from_url' => $from, 'to_url' => $to, 'match_type' => $match, 'type' => $type, 'is_active' => true, 'hit_count' => 0]);
    }

    // ── Matching ──────────────────────────────────────────────────────────

    #[Test]
    public function a_wildcard_rule_moves_a_whole_section_keeping_the_captured_part(): void
    {
        $this->rule('en/old-shop/*', '/en/parts/$1', Redirect::MATCH_WILDCARD);

        $this->get('/old-shop/AB-123')->assertNotFound(); // no locale: not this rule
        $this->get('/en/old-shop/AB-123')->assertStatus(301)->assertRedirect('/en/parts/AB-123');
    }

    #[Test]
    public function a_wildcard_with_two_stars_fills_both_placeholders_and_keeps_the_query_string(): void
    {
        $this->rule('en/shop/*/item/*', '/en/parts/$2?from=$1', Redirect::MATCH_WILDCARD, RedirectType::Temporary);

        $this->get('/en/shop/brakes/item/X9?utm=a')->assertStatus(302)->assertRedirect('/en/parts/X9?from=brakes');
    }

    #[Test]
    public function a_regex_rule_matches_the_whole_path_without_regard_to_case(): void
    {
        $this->rule('en/legacy-(\d+)-part', '/en/parts/L$1', Redirect::MATCH_REGEX);

        $this->get('/en/LEGACY-4711-part')->assertStatus(301)->assertRedirect('/en/parts/L4711');
        $this->get('/en/legacy-4711-part-extra')->assertNotFound();
    }

    #[Test]
    public function an_exact_rule_always_wins_over_a_pattern(): void
    {
        $this->rule('en/old-shop/special', '/en/special-landing', Redirect::MATCH_EXACT);
        $this->rule('en/old-shop/*', '/en/parts/$1', Redirect::MATCH_WILDCARD);

        $this->get('/en/old-shop/special')->assertRedirect('/en/special-landing');
    }

    #[Test]
    public function inactive_rules_and_broken_patterns_are_ignored_and_never_break_a_request(): void
    {
        $inactive = $this->rule('en/gone-a/*', '/en/x/$1', Redirect::MATCH_WILDCARD);
        $inactive->update(['is_active' => false]);
        // An uncompilable regex saved by hand (validation would refuse it in the admin).
        \DB::table('redirects')->insert(['from_url' => '([unclosed', 'to_url' => '/en/y', 'match_type' => 'regex', 'type' => '301', 'is_active' => 1, 'hit_count' => 0, 'created_at' => now(), 'updated_at' => now()]);
        Cache::forget(Redirect::PATTERN_CACHE_KEY);

        $this->get('/en/gone-a/zzz')->assertNotFound();
        $this->get('/en/parts')->assertOk();
    }

    #[Test]
    public function a_rule_whose_destination_is_the_page_it_matched_is_skipped_at_runtime(): void
    {
        $this->rule('en/parts*', '/en/parts', Redirect::MATCH_WILDCARD);

        $this->get('/en/parts')->assertOk(); // would otherwise redirect to itself forever
    }

    #[Test]
    public function captured_text_cannot_inject_a_header_or_turn_into_a_link_to_another_site(): void
    {
        $this->rule('en/go/*', '/$1', Redirect::MATCH_WILDCARD);

        $location = $this->get('/en/go/%2Fevil.example.com/x')->headers->get('Location');

        $this->assertStringNotContainsString("\n", (string) $location);
        $this->assertDoesNotMatchRegularExpression('#^//|^https?://evil#', (string) $location);
    }

    // ── 410 Gone ──────────────────────────────────────────────────────────

    #[Test]
    public function a_410_rule_answers_gone_with_no_redirect(): void
    {
        $this->rule('en/discontinued-line', '', Redirect::MATCH_EXACT, RedirectType::Gone);

        $response = $this->get('/en/discontinued-line');

        $response->assertStatus(410);
        $this->assertNull($response->headers->get('Location'));
        $response->assertSee('noindex', false);
    }

    #[Test]
    public function a_wildcard_410_retires_a_whole_section(): void
    {
        $this->rule('en/old-catalog/*', '', Redirect::MATCH_WILDCARD, RedirectType::Gone);

        $this->get('/en/old-catalog/anything/at/all')->assertStatus(410);
    }

    #[Test]
    public function hits_are_counted_for_pattern_and_gone_rules(): void
    {
        $this->rule('en/old-shop/*', '/en/parts/$1', Redirect::MATCH_WILDCARD);
        $this->get('/en/old-shop/A1');
        $this->get('/en/old-shop/A2');

        $this->assertSame(2, (int) Redirect::first()->hit_count);
    }

    // ── Compile / self-redirect helpers ───────────────────────────────────

    #[Test]
    public function compile_refuses_what_cannot_be_a_pattern(): void
    {
        $this->assertNull(RedirectPattern::compile(Redirect::MATCH_REGEX, '([unclosed'));
        $this->assertNull(RedirectPattern::compile(Redirect::MATCH_REGEX, '   '));
        $this->assertNull(RedirectPattern::compile(Redirect::MATCH_EXACT, 'en/a'));
        $this->assertNotNull(RedirectPattern::compile(Redirect::MATCH_WILDCARD, 'en/a/*'));
    }

    #[Test]
    public function redirects_to_itself_is_detected_before_saving(): void
    {
        $this->assertTrue(RedirectPattern::redirectsToItself(Redirect::MATCH_WILDCARD, 'en/*', '/en/new/$1'));
        $this->assertTrue(RedirectPattern::redirectsToItself(Redirect::MATCH_REGEX, 'en/(.*)', '/en/$1'));
        $this->assertFalse(RedirectPattern::redirectsToItself(Redirect::MATCH_WILDCARD, 'en/old/*', '/en/new/$1'));
        $this->assertFalse(RedirectPattern::redirectsToItself(Redirect::MATCH_WILDCARD, 'en/old/*', ''));
    }

    #[Test]
    public function a_regex_source_is_stored_untouched_so_its_meaning_cannot_change(): void
    {
        $rule = $this->rule('en/code-\D+-\S+', '/en/x', Redirect::MATCH_REGEX);

        $this->assertSame('en/code-\D+-\S+', $rule->fresh()->from_url);
    }
}

<?php

namespace Tests\Feature;

use App\Models\LanguageString;
use App\Support\DatabaseTranslationLoader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * TranslationResource (backed by LanguageString) had no code path merging
 * its rows into Laravel's translator at all — editing/adding a translation
 * in the admin had zero effect on any __()/trans() call anywhere in the
 * app; the real strings only ever came from the static lang/ files.
 *
 * Laravel's Translator memoizes each (locale, group) in memory once loaded
 * within a request/process — real, correct behavior (a file edit mid-request
 * wouldn't be seen either), not something this fix changes. Tests that need
 * to observe an effect "as of the next resolution" drop the cached
 * translator instance to simulate that, mirroring what a fresh request does.
 */
class DatabaseTranslationOverrideTest extends TestCase
{
    use RefreshDatabase;

    private function freshTranslator(): void
    {
        $this->app->forgetInstance('translator');
    }

    #[Test]
    public function a_db_translation_overrides_the_file_based_one(): void
    {
        LanguageString::create([
            'lang_code' => 'en', 'group' => 'auth',
            'key' => 'welcome_back', 'value' => 'Custom Welcome Text',
        ]);
        $this->freshTranslator();

        $this->assertSame('Custom Welcome Text', __('auth.welcome_back'));
    }

    #[Test]
    public function a_key_with_no_db_override_still_falls_back_to_the_file(): void
    {
        LanguageString::create([
            'lang_code' => 'en', 'group' => 'auth',
            'key' => 'welcome_back', 'value' => 'Overridden',
        ]);
        $this->freshTranslator();

        // 'create_account' has no override row — must still resolve from
        // lang/en/auth.php, proving the loader merges rather than replaces.
        $this->assertSame('Create account', __('auth.create_account'));
    }

    #[Test]
    public function editing_a_translation_takes_effect_on_the_next_resolution(): void
    {
        $string = LanguageString::create([
            'lang_code' => 'en', 'group' => 'auth',
            'key' => 'welcome_back', 'value' => 'First Version',
        ]);
        $this->freshTranslator();
        $this->assertSame('First Version', __('auth.welcome_back'));

        $string->update(['value' => 'Second Version']);
        $this->freshTranslator();

        $this->assertSame('Second Version', __('auth.welcome_back'));
    }

    #[Test]
    public function deleting_a_translation_reverts_to_the_file_value(): void
    {
        $string = LanguageString::create([
            'lang_code' => 'en', 'group' => 'auth',
            'key' => 'welcome_back', 'value' => 'Overridden',
        ]);
        $this->freshTranslator();
        $this->assertSame('Overridden', __('auth.welcome_back'));

        $string->delete();
        $this->freshTranslator();

        $this->assertSame('Welcome back', __('auth.welcome_back'));
    }

    #[Test]
    public function an_override_for_one_locale_does_not_leak_into_another(): void
    {
        LanguageString::create([
            'lang_code' => 'de', 'group' => 'auth',
            'key' => 'welcome_back', 'value' => 'Deutscher Text',
        ]);
        $this->freshTranslator();

        $this->assertSame('Deutscher Text', __('auth.welcome_back', [], 'de'));
        $this->assertSame('Welcome back', __('auth.welcome_back', [], 'en'));
    }

    #[Test]
    public function loader_falls_back_cleanly_when_the_table_is_unusable(): void
    {
        Schema::drop('language_strings');
        DatabaseTranslationLoader::forget('en', 'auth');
        $this->freshTranslator();

        $this->assertSame('Welcome back', __('auth.welcome_back'));
    }

    // Phase 14 (Monitoring/Logging Audit): overridesFor()'s catch used to
    // be inside the rememberForever() callback and completely silent — the
    // fix (move the try/catch to wrap the whole rememberForever() call,
    // log a warning) is the exact same pattern already verified end-to-end
    // in MenuRegistryTest::a_broken_menus_query_logs_a_warning_and_is_not_
    // cached_forever(). No equivalent synthetic-exception test here: a
    // dropped column doesn't reliably throw against SQLite (its grammar
    // double-quotes identifiers, and SQLite silently reinterprets an
    // unresolvable quoted identifier as a string literal rather than
    // erroring) and, unlike MenuRegistry::items(), this method has no
    // eager-loaded related table to drop instead. A DB::purge('sqlite')-
    // based connection break was tried and reverted: on the :memory:
    // driver this doesn't just reconnect, it destroys and replaces the
    // whole in-memory database, corrupting RefreshDatabase's state for
    // every other test in the same PHPUnit process (confirmed live — it
    // broke 23 unrelated tests with "table migrations already exists").
}

<?php

namespace Tests\Feature;

use App\Services\Cleanup\TargetRegistry;
use App\Services\Instance\InstanceState;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\Stages\FinCheckMigrationsStage;
use App\Services\Migration\Import\Stages\FinNormalizeStage;
use App\Services\Migration\Import\Stages\FinOriginResetStage;
use App\Services\Migration\Import\Stages\FinRewriteStage;
use App\Services\Migration\Import\Stages\FinSecretsStage;
use App\Services\Migration\KeyEscrow;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Package\PackageReader;
use App\Support\EncryptedSettingValue;
use Illuminate\Encryption\Encrypter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\BuildsImportPackages;
use Tests\TestCase;

/**
 * Phase 7.1 (second half): the stages that finish an import: secrets under this site's key, nothing pending, every old
 * address rewritten, the few settings that must differ, and what belonged to the old site reset.
 */
class ImportFinalisingTest extends TestCase
{
    use BuildsImportPackages;
    use RefreshDatabase;

    private string $ledgerBackup = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareImportTest();
        (new ImportMarker)->remove();

        $ledger = InstanceState::file()->path();
        $this->ledgerBackup = is_file($ledger) ? (string) file_get_contents($ledger) : '';
        @unlink($ledger);
        @unlink($ledger.'.prev');
    }

    protected function tearDown(): void
    {
        (new ImportMarker)->remove();
        $ledger = InstanceState::file()->path();
        @unlink($ledger);
        @unlink($ledger.'.prev');

        if ($this->ledgerBackup !== '') {
            file_put_contents($ledger, $this->ledgerBackup);
        }

        $this->cleanUpImportTest();

        parent::tearDown();
    }

    // ---- fixtures ---------------------------------------------------------------------------------------------

    private function state(array $flags, array $options = [], array $package = []): ImportState
    {
        $state = new ImportState('fin'.bin2hex(random_bytes(6)), options: $options);
        $state->package = $package + ['source' => ['domain' => 'old.example', 'url' => 'https://old.example'], 'export' => ['secrets_included' => false]];

        foreach ($flags as $flag) {
            $state->setFlag($flag);
        }

        (new ImportMarker)->write($state->id);
        (new ImportStateStore)->save($state);

        return $state;
    }

    private function context(float $seconds = 60.0): ImportContext
    {
        return new ImportContext(microtime(true) + $seconds, app(KeyEscrow::class));
    }

    private function setting(string $group, string $key, ?string $value, string $type = 'string', bool $encrypted = false): void
    {
        DB::table('settings')->updateOrInsert(['group' => $group, 'key' => $key], ['value' => $value, 'type' => $type, 'is_encrypted' => $encrypted, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function stored(string $group, string $key): ?string
    {
        $value = DB::table('settings')->where('group', $group)->where('key', $key)->value('value');

        return $value === null ? null : (string) $value;
    }

    /** A package whose locked secrets entry holds these lines, with the keys of its run in the escrow. */
    private function packageWithSecrets(ImportState $state, array $secrets): void
    {
        $writer = $this->newWriter('secrets.oeparts');
        $writer->addEntryFromString('db/settings.0000', 'db', "{\"\$\":\"oe-rows/1\",\"table\":\"settings\"}\n", ['group' => 'core', 'rows' => 0, 'attrs' => ['table' => 'settings']]);
        $writer->addSecrets(implode("\n", array_map(fn (array $s): string => json_encode($s), $secrets))."\n");
        $writer->seal();

        $state->package['path'] = $this->packagePath('secrets.oeparts');
        (new ImportStateStore)->save($state);

        $reader = PackageReader::open($state->package['path'], $this->passphrase, $this->weakKdf());
        app(KeyEscrow::class)->put(ImportContext::escrowId($state->id), $reader->packageIdHex(), $reader->keys());
        $reader->close();
    }

    // ---- secrets ----------------------------------------------------------------------------------------------

    #[Test]
    public function a_secret_this_sites_key_cannot_open_is_emptied_and_named_and_one_it_can_open_is_left_alone(): void
    {
        $foreign = (new Encrypter(random_bytes(32), 'AES-256-CBC'))->encryptString('the old sites password');
        $this->setting('mail', 'smtp_password', $foreign, 'encrypted', true);
        $this->setting('payment', 'stripe_key', Crypt::encryptString('sk_live_mine'), 'encrypted', true);
        $this->setting('mail', 'empty_one', null, 'encrypted', true);
        $state = $this->state(['files.done']);

        $result = app(FinSecretsStage::class)->step($state, $this->context());

        $this->assertTrue($result->done);
        $this->assertNull($this->stored('mail', 'smtp_password'), 'what cannot be opened is emptied: no "configured" password that never worked');
        $this->assertSame('sk_live_mine', EncryptedSettingValue::reveal($this->stored('payment', 'stripe_key'))->value);
        $this->assertSame(['mail.smtp_password'], $state->package['secrets']['reenter']);
        $this->assertSame(0, $state->package['secrets']['written']);
    }

    #[Test]
    public function the_secrets_of_the_package_are_written_with_the_key_of_this_site_when_the_person_asked_for_them(): void
    {
        $this->setting('mail', 'smtp_password', null, 'encrypted', true);
        $this->setting('payment', 'stripe_key', null, 'encrypted', true);
        $state = $this->state(['files.done'], ['include_secrets' => true], ['export' => ['secrets_included' => true]]);
        $this->packageWithSecrets($state, [
            ['group' => 'mail', 'key' => 'smtp_password', 'value' => 'hunter2-é'],
            ['group' => 'payment', 'key' => 'stripe_key', 'value' => 'sk_live_123'],
            ['group' => 'ghost', 'key' => 'nothing_here', 'value' => 'x'],
        ]);

        app(FinSecretsStage::class)->step($state, $this->context());

        $this->assertSame('hunter2-é', EncryptedSettingValue::reveal($this->stored('mail', 'smtp_password'))->value);
        $this->assertSame('sk_live_123', EncryptedSettingValue::reveal($this->stored('payment', 'stripe_key'))->value);
        $this->assertNotSame('hunter2-é', $this->stored('mail', 'smtp_password'), 'stored encrypted, never plain');
        $this->assertSame(2, $state->package['secrets']['written']);
        $this->assertSame(['ghost.nothing_here'], $state->package['secrets']['not_found']);
        $this->assertStringNotContainsString('hunter2', json_encode($state->package), 'no value in the run state');
    }

    #[Test]
    public function the_secrets_stay_in_the_package_when_the_person_did_not_ask_for_them(): void
    {
        $this->setting('mail', 'smtp_password', null, 'encrypted', true);
        $state = $this->state(['files.done'], ['include_secrets' => false], ['export' => ['secrets_included' => true]]);
        $this->packageWithSecrets($state, [['group' => 'mail', 'key' => 'smtp_password', 'value' => 'hunter2']]);

        app(FinSecretsStage::class)->step($state, $this->context());

        $this->assertNull($this->stored('mail', 'smtp_password'));
        $this->assertSame(0, $state->package['secrets']['written']);
    }

    #[Test]
    public function writing_the_secrets_again_after_a_crash_gives_the_same_result(): void
    {
        $this->setting('mail', 'smtp_password', null, 'encrypted', true);
        $state = $this->state(['files.done'], ['include_secrets' => true], ['export' => ['secrets_included' => true]]);
        $this->packageWithSecrets($state, [['group' => 'mail', 'key' => 'smtp_password', 'value' => 'again']]);

        app(FinSecretsStage::class)->step($state, $this->context());
        $state->flags['fin.secrets.done'] = false; // killed before the flag was saved
        app(FinSecretsStage::class)->step($state, $this->context());

        $this->assertSame('again', EncryptedSettingValue::reveal($this->stored('mail', 'smtp_password'))->value);
    }

    #[Test]
    public function the_finishing_stages_refuse_to_run_on_an_open_site(): void
    {
        foreach ([[FinSecretsStage::class, 'files.done'], [FinCheckMigrationsStage::class, 'fin.secrets.done'], [FinRewriteStage::class, 'fin.migrations.done'], [FinNormalizeStage::class, 'fin.rewrite.done'], [FinOriginResetStage::class, 'fin.normalize.done']] as [$class, $flag]) {
            $state = $this->state([$flag]);
            (new ImportMarker)->remove();

            try {
                app($class)->step($state, $this->context());
                $this->fail("{$class} must refuse while the site is open");
            } catch (MigrationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    #[Test]
    public function the_finishing_stages_refuse_to_run_out_of_order(): void
    {
        foreach ([[FinSecretsStage::class, 'locate.done'], [FinCheckMigrationsStage::class, 'files.done'], [FinRewriteStage::class, 'fin.secrets.done'], [FinNormalizeStage::class, 'fin.migrations.done'], [FinOriginResetStage::class, 'fin.rewrite.done']] as [$class, $flag]) {
            try {
                app($class)->step($this->state([$flag]), $this->context());
                $this->fail("{$class} must refuse when the stage before it has not finished");
            } catch (MigrationException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    // ---- migrations ---------------------------------------------------------------------------------------------

    #[Test]
    public function nothing_pending_passes_and_a_pending_migration_stops_the_run(): void
    {
        $state = $this->state(['fin.secrets.done']);
        $this->assertTrue(app(FinCheckMigrationsStage::class)->step($state, $this->context())->done);
        $this->assertTrue($state->flag('fin.migrations.done'));

        $name = (string) DB::table('migrations')->orderByDesc('id')->value('migration');
        DB::table('migrations')->where('migration', $name)->delete();

        $this->expectException(MigrationException::class);
        app(FinCheckMigrationsStage::class)->step($this->state(['fin.secrets.done']), $this->context());
    }

    // ---- the rewrite ----------------------------------------------------------------------------------------------

    #[Test]
    public function every_old_address_is_rewritten_in_steps_and_a_repeat_changes_nothing(): void
    {
        $this->setting('general', 'site_url', 'https://old.example');
        DB::table('redirects')->insert(['from_url' => '/a', 'to_url' => 'https://old.example/landing', 'type' => '301', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('redirects')->insert(['from_url' => '/b', 'to_url' => 'https://elsewhere.example/keep', 'type' => '301', 'created_at' => now(), 'updated_at' => now()]);
        $state = $this->state(['fin.migrations.done'], ['url' => 'https://new.example']);

        $steps = 0;
        do {
            $current = (new ImportStateStore)->find($state->id);
            $result = app(FinRewriteStage::class)->step($current, new ImportContext(microtime(true) - 1, app(KeyEscrow::class)));
            (new ImportStateStore)->save($current);
            app()->forgetInstance(FinRewriteStage::class);
        } while (! $result->done && ++$steps < 200);

        $final = (new ImportStateStore)->find($state->id);
        $this->assertTrue($final->flag('fin.rewrite.done'));
        $this->assertSame('https://new.example', $this->stored('general', 'site_url'));
        $this->assertSame('https://new.example/landing', DB::table('redirects')->where('from_url', '/a')->value('to_url'));
        $this->assertSame('https://elsewhere.example/keep', DB::table('redirects')->where('from_url', '/b')->value('to_url'), 'another site\'s address is not touched');
        $this->assertGreaterThanOrEqual(2, $final->package['rewrite']['rows_changed']);
        $this->assertFalse($final->package['rewrite']['skipped']);

        // Again, as a fresh run would (same cursor file, nothing left to do).
        $final->flags['fin.rewrite.done'] = false;
        app(FinRewriteStage::class)->step($final, $this->context());
        $this->assertSame('https://new.example/landing', DB::table('redirects')->where('from_url', '/a')->value('to_url'));
    }

    #[Test]
    public function with_no_old_address_to_replace_the_rewrite_says_so_and_finishes(): void
    {
        $same = $this->state(['fin.migrations.done'], ['url' => 'https://old.example']);
        $unknown = $this->state(['fin.migrations.done'], ['url' => 'https://new.example'], ['source' => ['domain' => null]]);

        foreach ([$same, $unknown] as $state) {
            $this->assertTrue(app(FinRewriteStage::class)->step($state, $this->context())->done);
            $this->assertTrue($state->package['rewrite']['skipped']);
        }
    }

    // ---- the settings that must differ -----------------------------------------------------------------------------

    private function normalize(array $options, array $package = []): ImportState
    {
        $state = $this->state(['fin.rewrite.done'], $options, $package);
        app(FinNormalizeStage::class)->step($state, $this->context());

        return $state;
    }

    #[Test]
    public function a_live_launch_sets_the_address_and_the_canonical_host_and_clears_what_belongs_to_the_old_site(): void
    {
        $this->setting('general', 'site_url', 'https://old.example');
        $this->setting('seo', 'canonical_host', 'old.example');
        $this->setting('seo', 'gsc_property_url', 'sc-domain:old.example');
        $this->setting('maintenance', 'enabled', '1', 'boolean');
        $this->setting('security', 'otp_enabled', '1', 'boolean');

        $state = $this->normalize(['url' => 'https://new.example', 'launch' => 'live']);

        $this->assertSame('https://new.example', $this->stored('general', 'site_url'));
        $this->assertSame('new.example', $this->stored('seo', 'canonical_host'));
        $this->assertSame('', $this->stored('seo', 'gsc_property_url'));
        $this->assertSame('0', $this->stored('maintenance', 'enabled'));
        $this->assertSame('0', $this->stored('security', 'otp_enabled'), 'no mail password travelled, so no code could be sent');
        $this->assertSame(['general.site_url', 'seo.canonical_host', 'seo.gsc_property_url', 'maintenance.enabled', 'security.otp_enabled'], array_keys($state->package['normalized']));
        $this->assertStringNotContainsString('old.example', json_encode($state->package['normalized']), 'the list names settings, never values');
    }

    #[Test]
    public function a_staging_launch_empties_the_canonical_host_and_remembers_the_old_one_for_going_live(): void
    {
        $this->setting('seo', 'canonical_host', 'www.old.example');

        $state = $this->normalize(['url' => 'https://staging.new.example', 'launch' => 'staging']);

        $this->assertSame('', $this->stored('seo', 'canonical_host'));
        $this->assertSame('www.old.example', $state->package['source_canonical_host']);
        $this->assertSame('www.old.example', (new InstanceState)->read()['import']['source_canonical_host']);
    }

    #[Test]
    public function a_source_with_no_canonical_host_gets_none(): void
    {
        $this->setting('seo', 'canonical_host', '');

        $this->normalize(['url' => 'https://new.example', 'launch' => 'live']);

        $this->assertSame('', $this->stored('seo', 'canonical_host'));
    }

    #[Test]
    public function the_sign_in_code_stays_on_when_the_mail_password_travelled(): void
    {
        $this->setting('security', 'otp_enabled', '1', 'boolean');

        $this->normalize(['url' => 'https://new.example', 'include_secrets' => true], ['export' => ['secrets_included' => true]]);

        $this->assertSame('1', $this->stored('security', 'otp_enabled'));
    }

    #[Test]
    public function a_run_killed_and_resumed_keeps_the_canonical_host_it_started_from(): void
    {
        $this->setting('seo', 'canonical_host', 'www.old.example');
        $state = $this->state(['fin.rewrite.done'], ['url' => 'https://staging.new.example', 'launch' => 'staging']);

        app(FinNormalizeStage::class)->step($state, $this->context());
        $this->assertSame('', $this->stored('seo', 'canonical_host'));

        // The process died before the stage's flag was saved; the next one starts from what the state remembers.
        $resumed = (new ImportStateStore)->find($state->id);
        $resumed->flags['fin.normalize.done'] = false;
        app(FinNormalizeStage::class)->step($resumed, $this->context());

        $this->assertSame('www.old.example', $resumed->package['source_canonical_host']);
        $this->assertSame('www.old.example', (new InstanceState)->read()['import']['source_canonical_host']);
    }

    // ---- what belongs to the old site -------------------------------------------------------------------------------

    #[Test]
    public function what_belongs_to_the_old_site_does_not_come_along_alive(): void
    {
        DB::table('admin_push_subscriptions')->count(); // the table exists
        DB::table('otps')->delete();
        DB::table('password_reset_tokens')->insert(['email' => 'someone@example.test', 'token' => 'abc', 'created_at' => now()]);
        $this->setting('cleanup', 'auto_enabled', '1', 'boolean');
        $this->setting('backup', 'schedule_enabled', '1', 'boolean');
        $this->setting('updates', 'auto_apply_security', '1', 'boolean');
        $targets = array_values((new TargetRegistry)->all());
        $first = $targets[0]->key();
        $this->setting('cleanup', 'auto_'.$first, '1', 'boolean');
        $state = $this->state(['fin.normalize.done']);

        $result = app(FinOriginResetStage::class)->step($state, $this->context());

        $this->assertTrue($result->done);
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
        $this->assertSame(['password_reset_tokens' => 1], $state->package['origin_reset']['tables']);

        foreach (['cleanup.auto_enabled', 'backup.schedule_enabled', 'updates.auto_apply_security', 'cleanup.auto_'.$first] as $qualified) {
            [$group, $key] = explode('.', $qualified, 2);
            $this->assertSame('0', $this->stored($group, $key), "{$qualified} starts OFF");
            $this->assertContains($qualified, $state->package['origin_reset']['switched_off']);
        }

        foreach ($targets as $target) {
            $this->assertSame('0', $this->stored('cleanup', 'auto_'.$target->key()), "a row exists for {$target->key()}, so a default can never switch it on");
        }

        $this->assertContains('cleanup.auto_enabled', (new InstanceState)->read()['import']['switched_off']);
    }

    #[Test]
    public function resetting_twice_changes_nothing_more(): void
    {
        $state = $this->state(['fin.normalize.done']);
        app(FinOriginResetStage::class)->step($state, $this->context());
        $first = $state->package['origin_reset'];

        $state->flags['fin.origin.done'] = false;
        app(FinOriginResetStage::class)->step($state, $this->context());

        $this->assertSame([], $state->package['origin_reset']['switched_off'], 'everything was already off');
        $this->assertNotEmpty($first['switched_off']);
    }
}

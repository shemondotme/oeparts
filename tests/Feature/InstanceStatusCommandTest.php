<?php

namespace Tests\Feature;

use App\Services\Instance\InstanceState;
use App\Services\Instance\SafeMode;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 4.7: oeparts:instance:status says what the installation is, in words and as one JSON document, and never
 * prints a setting value, a key or an address.
 */
class InstanceStatusCommandTest extends TestCase
{
    private string $ledgerBackup = '';

    protected function setUp(): void
    {
        parent::setUp();

        SafeMode::resetLogMemory();
        $path = InstanceState::file()->path();
        $this->ledgerBackup = is_file($path) ? (string) file_get_contents($path) : '';
        @unlink($path);
        @unlink($path.'.prev');
    }

    protected function tearDown(): void
    {
        $path = InstanceState::file()->path();
        @unlink($path);
        @unlink($path.'.prev');
        if ($this->ledgerBackup !== '') {
            file_put_contents($path, $this->ledgerBackup);
        }

        parent::tearDown();
    }

    /** @return array<string, mixed> */
    private function report(): array
    {
        $this->assertSame(0, Artisan::call('oeparts:instance:status', ['--json' => true]));

        return json_decode(Artisan::output(), true, 512, JSON_THROW_ON_ERROR);
    }

    #[Test]
    public function on_the_real_shop_it_says_production_and_that_nothing_is_held_or_masked(): void
    {
        config(['instance.mode' => null]);

        $report = $this->report();

        $this->assertSame('production', $report['mode']);
        $this->assertNull($report['configured_value']);
        $this->assertSame([], $report['held']['reasons']);
        $this->assertSame(0, $report['masked_settings']);
        $this->assertTrue($report['web_cron_allowed']);
        $this->assertSame('ORD', $report['sequence_example']);
        $this->assertSame([], $report['warnings']);
    }

    #[Test]
    public function on_staging_it_describes_what_is_held_masked_and_how_mail_is_handled(): void
    {
        config(['instance.mode' => 'staging', 'instance.mail.allowlist' => 'dev@example.com,@team.example', 'instance.nonprod_http_cron' => false]);

        $report = $this->report();

        $this->assertSame('staging', $report['mode']);
        $this->assertContains('this is a staging copy of the shop', $report['held']['reasons']);
        $this->assertSame('CustomerOnly', $report['held']['level']);
        $this->assertSame(['always' => true, 'internal' => true, 'customer' => false], $report['schedule']);
        $this->assertSame(2, $report['mail']['allowlist_entries']);
        $this->assertStringContainsString('allow-list', $report['mail']['what']);
        $this->assertFalse($report['web_cron_allowed']);
        $this->assertSame('T-ORD', $report['sequence_example']);
        $this->assertGreaterThan(10, $report['masked_settings']);
        $this->assertContains('push.enabled', $report['overrides']);
    }

    #[Test]
    public function a_typo_is_reported_as_quarantine_with_the_bad_value_quoted(): void
    {
        config(['instance.mode' => 'stagging']);

        $report = $this->report();

        $this->assertSame('quarantine', $report['mode']);
        $this->assertSame('stagging', $report['configured_value']);
        $this->assertStringContainsString('stagging', $report['warnings'][0]);
    }

    #[Test]
    public function drift_between_the_ledger_and_env_is_a_warning(): void
    {
        (new InstanceState)->recordEnvChange('test', ['OE_MODE' => ['from' => '', 'to' => 'staging']]);
        config(['instance.mode' => 'production']);

        $report = $this->report();

        $this->assertSame('staging', $report['ledger']['recorded_mode']);
        $this->assertNotNull($report['ledger']['drift']);
        $this->assertCount(1, $report['warnings']);
        $this->assertSame(['OE_MODE'], $report['ledger']['recent_changes'][0]['keys']);
    }

    #[Test]
    public function the_text_form_is_readable_and_lists_the_masked_settings_only_with_v(): void
    {
        config(['instance.mode' => 'staging']);

        Artisan::call('oeparts:instance:status');
        $plain = Artisan::output();
        $this->assertStringContainsString('Mode:           STAGING', $plain);
        $this->assertStringContainsString('-v to list them', $plain);
        $this->assertStringNotContainsString('push.enabled', $plain);

        Artisan::call('oeparts:instance:status', ['-v' => true]);
        $this->assertStringContainsString('push.enabled', Artisan::output());
    }

    #[Test]
    public function it_never_prints_a_secret_or_an_address(): void
    {
        config(['instance.mode' => 'staging', 'instance.mail.allowlist' => 'secret.person@example.com']);

        Artisan::call('oeparts:instance:status', ['--json' => true]);
        $json = Artisan::output();
        Artisan::call('oeparts:instance:status');
        $text = Artisan::output();

        $this->assertStringNotContainsString('secret.person@example.com', $json);
        $this->assertStringNotContainsString('secret.person@example.com', $text);
        $this->assertStringNotContainsString((string) config('app.key'), $json.$text);
    }
}

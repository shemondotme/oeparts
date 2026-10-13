<?php

namespace Tests\Feature;

use App\Services\Instance\Capability;
use App\Services\Instance\InstanceMode;
use App\Services\Instance\InstanceModeBlockedException;
use App\Services\Instance\InstanceState;
use App\Services\Instance\SafeMode;
use App\Services\Instance\SystemGate;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 4.1: what a copy of the shop may do is decided in ONE place, from ONE value in .env. Nothing here is
 * enforced anywhere yet; these tests pin the rules the later steps wire in.
 */
class SafeModeTest extends TestCase
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
        SafeMode::resetLogMemory();

        parent::tearDown();
    }

    private function mode(?string $value): SafeMode
    {
        config(['instance.mode' => $value]);

        return new SafeMode;
    }

    // ---- the mode itself ---------------------------------------------------------------------------

    #[Test]
    #[DataProvider('productionValues')]
    public function a_missing_or_empty_value_is_production_so_existing_installs_do_not_change(?string $value): void
    {
        $this->assertSame(InstanceMode::Production, $this->mode($value)->mode());
        $this->assertFalse($this->mode($value)->hasInvalidValue());
    }

    /** @return array<string, array{0: string|null}> */
    public static function productionValues(): array
    {
        return ['null' => [null], 'empty' => [''], 'spaces' => ['  '], 'word' => ['production'], 'capitals' => ['PRODUCTION']];
    }

    #[Test]
    public function staging_and_quarantine_are_read_as_written(): void
    {
        $this->assertSame(InstanceMode::Staging, $this->mode('staging')->mode());
        $this->assertSame(InstanceMode::Quarantine, $this->mode('quarantine')->mode());
        $this->assertSame(InstanceMode::Staging, $this->mode(' Staging ')->mode());
    }

    #[Test]
    public function a_typo_fails_closed_to_quarantine_and_is_logged_critical_exactly_once(): void
    {
        Log::shouldReceive('critical')->once()->withArgs(fn (string $message): bool => str_contains($message, 'stagging') && str_contains($message, 'quarantine'));

        $safe = $this->mode('stagging');

        $this->assertSame(InstanceMode::Quarantine, $safe->mode());
        $this->assertTrue($safe->hasInvalidValue());
        $this->assertSame(InstanceMode::Quarantine, $safe->mode(), 'asked again: still quarantine, no second log line');
        $this->assertFalse($safe->allows(Capability::SendMail));
    }

    // ---- capabilities ------------------------------------------------------------------------------

    #[Test]
    public function the_real_shop_may_do_everything(): void
    {
        $safe = $this->mode('production');

        foreach (Capability::cases() as $capability) {
            $this->assertTrue($safe->allows($capability), $capability->value);
        }
    }

    #[Test]
    public function a_staging_copy_may_only_charge_a_sandbox_card_settle_and_run_housekeeping(): void
    {
        $safe = $this->mode('staging');

        $allowed = [Capability::ChargeCard, Capability::SettlePayment, Capability::RunInternalSchedule];

        foreach (Capability::cases() as $capability) {
            $this->assertSame(in_array($capability, $allowed, true), $safe->allows($capability), $capability->value);
        }
    }

    #[Test]
    public function a_quarantined_copy_may_do_nothing_that_reaches_beyond_itself(): void
    {
        $safe = $this->mode('quarantine');

        foreach (Capability::cases() as $capability) {
            $this->assertFalse($safe->allows($capability), $capability->value);
        }
    }

    #[Test]
    public function assert_allowed_throws_a_named_exception_that_says_why(): void
    {
        $safe = $this->mode('staging');

        try {
            $safe->assertAllowed(Capability::UsePaysera, 'Paysera has no sandbox.');
            $this->fail('expected a refusal');
        } catch (InstanceModeBlockedException $e) {
            $this->assertSame(Capability::UsePaysera, $e->capability);
            $this->assertSame(InstanceMode::Staging, $e->mode);
            $this->assertStringContainsString('Paysera has no sandbox', $e->getMessage());
        }

        $this->mode('production')->assertAllowed(Capability::UsePaysera);
        $this->addToAssertionCount(1);
    }

    // ---- payments ----------------------------------------------------------------------------------

    #[Test]
    public function a_copied_payment_cannot_be_settled_but_a_new_one_can(): void
    {
        $state = new InstanceState;
        $state->setWatermarks(['payments' => 100]);
        config(['instance.mode' => 'staging']);
        $safe = new SafeMode($state);

        $this->assertTrue($safe->isCopiedPayment(100));
        $this->assertTrue($safe->isCopiedPayment(1));
        $this->assertFalse($safe->isCopiedPayment(101));

        $safe->assertPaymentAllowed(Capability::SettlePayment, 101); // made on the copy itself: fine
        $safe->assertPaymentAllowed(Capability::ChargeCard);
        $this->addToAssertionCount(2);

        $this->expectException(InstanceModeBlockedException::class);
        $safe->assertPaymentAllowed(Capability::SettlePayment, 100);
    }

    #[Test]
    public function the_real_shop_never_treats_a_payment_as_copied_even_with_a_stale_watermark(): void
    {
        $state = new InstanceState;
        $state->setWatermarks(['payments' => 100]);
        config(['instance.mode' => 'production']);
        $safe = new SafeMode($state);

        $this->assertFalse($safe->isCopiedPayment(5));
        $safe->assertPaymentAllowed(Capability::SettlePayment, 5);
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function paysera_is_refused_on_a_copy_and_a_quarantined_copy_refuses_every_payment(): void
    {
        $this->expectException(InstanceModeBlockedException::class);
        $this->mode('staging')->assertPaymentAllowed(Capability::UsePaysera);
    }

    #[Test]
    public function quarantine_refuses_even_a_sandbox_charge(): void
    {
        $this->expectException(InstanceModeBlockedException::class);
        $this->mode('quarantine')->assertPaymentAllowed(Capability::ChargeCard);
    }

    // ---- scheduler, cron, robots, numbers ------------------------------------------------------------

    #[Test]
    public function the_schedule_classes_follow_the_mode(): void
    {
        $classes = [SystemGate::ALWAYS, SystemGate::INTERNAL, SystemGate::CUSTOMER, SystemGate::UNKNOWN];
        $verdicts = fn (string $mode): array => array_map(fn (string $c): bool => $this->mode($mode)->scheduledAllowed($c), $classes);

        $this->assertSame([true, true, true, true], $verdicts('production'));
        $this->assertSame([true, true, false, false], $verdicts('staging'));
        $this->assertSame([true, false, false, false], $verdicts('quarantine'));
    }

    #[Test]
    public function the_web_cron_fallback_is_off_on_a_copy_unless_asked_for(): void
    {
        config(['instance.nonprod_http_cron' => false]);
        $this->assertTrue($this->mode('production')->httpCronAllowed());
        $this->assertFalse($this->mode('staging')->httpCronAllowed());
        $this->assertFalse($this->mode('quarantine')->httpCronAllowed());

        config(['instance.nonprod_http_cron' => true]);
        $this->assertTrue($this->mode('staging')->httpCronAllowed());
    }

    #[Test]
    public function robots_keep_crawlers_out_of_a_copy_only(): void
    {
        $this->assertNull($this->mode('production')->robotsDirective());
        $this->assertSame("User-agent: *\nDisallow: /", $this->mode('staging')->robotsDirective());
        $this->assertSame("User-agent: *\nDisallow: /", $this->mode('quarantine')->robotsDirective());
    }

    #[Test]
    public function numbers_on_a_copy_are_visibly_test_numbers_and_never_too_long(): void
    {
        $this->assertSame('INV-', $this->mode('production')->sequencePrefix('INV-'));
        $this->assertSame('T-INV-', $this->mode('staging')->sequencePrefix('INV-'));

        $long = str_repeat('X', 60);
        $this->assertSame(30, strlen($this->mode('quarantine')->sequencePrefix($long)));
        $this->assertStringStartsWith('T-X', $this->mode('quarantine')->sequencePrefix($long));
    }

    // ---- mail --------------------------------------------------------------------------------------

    #[Test]
    public function mail_is_delivered_on_production_dropped_in_quarantine_and_filtered_on_staging(): void
    {
        config(['instance.mail.allowlist' => 'Dev@Example.com, @team.example ']);

        $this->assertSame('send', $this->mode('production')->mailVerdict('anyone@customer.test'));
        $this->assertSame('drop', $this->mode('quarantine')->mailVerdict('dev@example.com'));

        $staging = $this->mode('staging');
        $this->assertSame('send', $staging->mailVerdict('dev@example.com'));
        $this->assertSame('send', $staging->mailVerdict(' DEV@EXAMPLE.COM '));
        $this->assertSame('send', $staging->mailVerdict('anna@team.example'));
        $this->assertSame('drop', $staging->mailVerdict('customer@shop.test'));
        $this->assertSame('drop', $staging->mailVerdict('anna@team.example.evil.com'), 'a domain rule matches the end of the address only');
        $this->assertSame('[STAGING] ', $staging->mailSubjectPrefix());
        $this->assertSame('', $this->mode('production')->mailSubjectPrefix());
    }

    #[Test]
    public function an_empty_allow_list_allows_nothing(): void
    {
        config(['instance.mail.allowlist' => '']);

        $this->assertSame([], $this->mode('staging')->mailAllowList());
        $this->assertSame('drop', $this->mode('staging')->mailVerdict('dev@example.com'));
    }

    #[Test]
    public function a_copy_points_the_mailer_at_the_log_and_the_real_shop_is_left_alone(): void
    {
        config(['mail.default' => 'smtp']);
        $this->mode('production')->applyToConfig();
        $this->assertSame('smtp', config('mail.default'));

        $this->mode('staging')->applyToConfig();
        $this->assertSame('log', config('mail.default'));

        config(['mail.default' => 'smtp', 'instance.mail.allowlist' => 'dev@example.com']);
        $this->mode('staging')->applyToConfig();
        $this->assertSame('smtp', config('mail.default'), 'a staging copy with an allow-list must really reach those addresses');

        $this->mode('quarantine')->applyToConfig();
        $this->assertSame('log', config('mail.default'), 'quarantine never mails, allow-list or not');
    }

    // ---- hiding settings -----------------------------------------------------------------------------

    #[Test]
    public function the_real_shop_sees_its_settings_untouched(): void
    {
        $values = ['indexnow_enabled' => true, 'indexnow_api_key' => 'abc', 'canonical_host' => 'shop.example'];

        $this->assertSame($values, $this->mode('production')->maskSettings('seo', $values));
        $this->assertSame([], $this->mode('production')->activeOverrides());
    }

    #[Test]
    public function a_copy_sees_the_override_instead_of_the_stored_value_and_wildcards_cover_a_family_of_keys(): void
    {
        $values = ['indexnow_enabled' => true, 'indexnow_api_key' => 'abc', 'canonical_host' => 'shop.example', 'gsc_property_url' => 'https://shop.example/', 'gsc_token' => 't', 'site_name' => 'Shop'];

        $masked = $this->mode('staging')->maskSettings('seo', $values);

        $this->assertFalse($masked['indexnow_enabled']);
        $this->assertSame('', $masked['indexnow_api_key']);
        $this->assertSame('', $masked['canonical_host']);
        $this->assertSame('', $masked['gsc_property_url']);
        $this->assertSame('', $masked['gsc_token']);
        $this->assertSame('Shop', $masked['site_name'], 'a key with no override is left alone');
    }

    #[Test]
    public function a_named_override_applies_even_when_nothing_is_stored_but_a_wildcard_only_covers_what_exists(): void
    {
        $masked = $this->mode('staging')->maskSettings('seo', ['site_name' => 'Shop']);

        $this->assertFalse($masked['indexnow_enabled'], 'a code default of "on" must not slip through an unstored key');
        $this->assertSame('', $masked['canonical_host']);
        $this->assertArrayNotHasKey('gsc_property_url', $masked, 'a wildcard cannot invent keys');
        $this->assertSame('Shop', $masked['site_name']);
        $this->assertSame(['x' => 1], $this->mode('staging')->maskSettings('a_group_with_no_overrides', ['x' => 1]));
    }

    #[Test]
    public function the_active_overrides_are_listed_for_the_admin_notice_on_a_copy(): void
    {
        $list = $this->mode('staging')->activeOverrides();

        $this->assertNotEmpty($list);
        $this->assertContains(['group' => 'push', 'key' => 'enabled', 'shows' => false], $list);
        $this->assertContains(['group' => 'payment', 'key' => 'airwallex_environment', 'shows' => 'sandbox'], $list);
    }

    // ---- the ledger ----------------------------------------------------------------------------------

    #[Test]
    public function the_ledger_notices_when_it_and_env_disagree_and_env_wins(): void
    {
        $state = new InstanceState;
        $this->assertNull($state->drift(InstanceMode::Production), 'a ledger that recorded nothing cannot disagree');

        $state->recordEnvChange('test', ['OE_MODE' => ['from' => '', 'to' => 'staging']]);
        $this->assertSame('staging', $state->recordedMode());
        $this->assertNull($state->drift(InstanceMode::Staging));
        $this->assertStringContainsString('.env wins', (string) $state->drift(InstanceMode::Production));
    }

    #[Test]
    public function an_unreadable_ledger_never_stops_the_site(): void
    {
        $path = InstanceState::file()->path();
        @mkdir(dirname($path), 0775, true);
        file_put_contents($path, '{not json');

        $state = new InstanceState;

        $this->assertSame(InstanceState::defaults(), $state->read());
        $this->assertNull($state->watermark('payments'));
    }
}

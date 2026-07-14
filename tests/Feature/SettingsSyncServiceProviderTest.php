<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Providers\SettingsSyncServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Regression test for a real bug found during the Go-Live Blockers
 * accessibility live-verification pass: a customer login (which dispatches
 * SendOtpEmail) returned a 500 the instant an admin had configured an
 * email.reply_to setting, because SettingsSyncServiceProvider only set
 * mail.reply_to.address, never .name — and Laravel's own
 * MailManager::setGlobalAddress() unconditionally dereferences both keys
 * for every mailer resolution. Confirmed via a live reproduction (real
 * browser login attempt -> storage/logs/laravel.log: ErrorException
 * "Undefined array key \"name\"" at MailManager.php:495) before the fix.
 */
class SettingsSyncServiceProviderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function a_configured_reply_to_address_does_not_break_mailer_resolution(): void
    {
        Setting::create([
            'group' => 'email',
            'key'   => 'reply_to',
            'value' => 'support@oeparts.test',
            'type'  => \App\Enums\SettingType::String,
        ]);

        (new SettingsSyncServiceProvider(app()))->boot();

        $this->assertSame('support@oeparts.test', config('mail.reply_to.address'));
        $this->assertNotNull(config('mail.reply_to.name'), 'mail.reply_to.name must be set alongside .address — MailManager::setGlobalAddress() requires both.');

        // The actual failure mode: resolving the mailer (which every Mail::
        // send/queue call does internally) must not throw.
        Mail::mailer('smtp');
        $this->assertTrue(true, 'Resolving the smtp mailer did not throw.');
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Changing your email in Account Settings used to keep email_verified_at
 * untouched — since login only ever checks is_null(email_verified_at), a
 * customer could "claim" any address (a typo, or one they don't control) as
 * verified just by typing it into this form, with no proof they can
 * actually receive mail there.
 */
class AccountEmailChangeTest extends TestCase
{
    use RefreshDatabase;

    private function settingsPayload(User $user, string $email): array
    {
        [$first, $last] = array_pad(explode(' ', $user->name, 2), 2, '');

        return [
            'first_name' => $first ?: 'Test',
            'last_name' => $last ?: 'User',
            'phone' => $user->phone,
            'email' => $email,
        ];
    }

    #[Test]
    public function changing_email_resets_verification_and_requires_login_again_when_otp_is_enabled(): void
    {
        app(SettingsService::class)->set('security.otp_enabled', '1');

        $user = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($user, 'web');

        $response = $this->post('/en/account/settings', $this->settingsPayload($user, 'new-address@example.com'));

        $response->assertRedirect();
        $response->assertSessionHas('show_auth_modal', true);

        $fresh = $user->fresh();
        $this->assertSame('new-address@example.com', $fresh->email);
        $this->assertNull($fresh->email_verified_at);
        $this->assertGuest('web');
    }

    #[Test]
    public function changing_email_auto_verifies_when_otp_is_globally_disabled(): void
    {
        app(SettingsService::class)->set('security.otp_enabled', '0');

        $user = User::factory()->create(['email_verified_at' => now()->subDay()]);
        $this->actingAs($user, 'web');

        $response = $this->post('/en/account/settings', $this->settingsPayload($user, 'new-address@example.com'));

        $response->assertRedirect(route('frontend.account.settings', ['lang' => 'en']));

        $fresh = $user->fresh();
        $this->assertSame('new-address@example.com', $fresh->email);
        $this->assertNotNull($fresh->email_verified_at);
        $this->assertAuthenticatedAs($fresh, 'web');
    }

    #[Test]
    public function saving_settings_without_changing_email_leaves_verification_untouched(): void
    {
        $verifiedAt = now()->subWeek();
        $user = User::factory()->create(['email_verified_at' => $verifiedAt]);
        $this->actingAs($user, 'web');

        $response = $this->post('/en/account/settings', $this->settingsPayload($user, $user->email));

        $response->assertRedirect(route('frontend.account.settings', ['lang' => 'en']));
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertEquals($verifiedAt->timestamp, $user->fresh()->email_verified_at->timestamp);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\SettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SocialAuthController::callback() used to log a visitor into any existing
 * local account purely by matching the provider-reported email, with no
 * check that the email was actually verified by the provider, no is_active
 * check (unlike the password login path), no registration-enabled check,
 * and no session-ID rotation.
 */
class SocialLoginTest extends TestCase
{
    use RefreshDatabase;

    private function fakeSocialUser(array $attributes): void
    {
        $socialUser = SocialiteUser::fake(array_merge([
            'id' => '1', 'nickname' => 'tester', 'name' => 'Tester', 'email' => 'social@example.com',
        ], $attributes));

        $provider = \Mockery::mock(SocialiteProvider::class);
        $provider->shouldReceive('user')->andReturn($socialUser);

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    #[Test]
    public function redirect_route_resolves_lang_and_provider_in_the_right_order(): void
    {
        // {lang} is captured before {provider} in the URI (it's the outer
        // prefix), and Laravel's ControllerDispatcher binds route params to
        // the method positionally — a (provider, lang)-ordered signature
        // received them swapped, so every "Log in with Google" click fell
        // into the "unsupported provider" branch and never reached
        // Socialite at all.
        $provider = \Mockery::mock(SocialiteProvider::class);
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.test/oauth'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/en/auth/google/redirect');

        $response->assertRedirect('https://accounts.google.test/oauth');
    }

    #[Test]
    public function new_user_is_logged_in_when_google_reports_the_email_as_verified(): void
    {
        $this->fakeSocialUser(['email' => 'verified@example.com', 'email_verified' => true]);

        $response = $this->get('/en/auth/google/callback');

        $response->assertRedirect();
        $this->assertAuthenticated('web');
        $this->assertDatabaseHas('users', ['email' => 'verified@example.com']);
    }

    #[Test]
    public function login_is_rejected_when_google_reports_the_email_as_unverified(): void
    {
        User::factory()->create(['email' => 'unverified@example.com']);

        $this->fakeSocialUser(['email' => 'unverified@example.com', 'email_verified' => false]);

        $response = $this->get('/en/auth/google/callback');

        $response->assertRedirect();
        $this->assertGuest('web');
    }

    #[Test]
    public function deactivated_local_account_cannot_log_in_via_social(): void
    {
        User::factory()->create(['email' => 'deactivated@example.com', 'is_active' => false]);

        $this->fakeSocialUser(['email' => 'deactivated@example.com', 'email_verified' => true]);

        $response = $this->get('/en/auth/google/callback');

        $response->assertRedirect();
        $this->assertGuest('web');
    }

    #[Test]
    public function social_signup_is_blocked_when_registration_is_disabled(): void
    {
        app(SettingsService::class)->set('auth.registration_enabled', '0');

        $this->fakeSocialUser(['email' => 'brand-new@example.com', 'email_verified' => true]);

        $response = $this->get('/en/auth/google/callback');

        $response->assertRedirect();
        $this->assertGuest('web');
        $this->assertDatabaseMissing('users', ['email' => 'brand-new@example.com']);
    }

    #[Test]
    public function existing_active_verified_user_can_still_log_in_via_social_and_session_is_rotated(): void
    {
        $user = User::factory()->create(['email' => 'existing@example.com', 'is_active' => true]);

        // Prime a session so we can observe the ID changing across the request.
        $this->withSession(['probe' => true]);
        $originalId = $this->app['session']->getId();

        $this->fakeSocialUser(['email' => 'existing@example.com', 'email_verified' => true]);

        $response = $this->get('/en/auth/google/callback');

        $response->assertRedirect();
        $this->assertAuthenticatedAs($user, 'web');
        $this->assertNotSame($originalId, $this->app['session']->getId());
    }
}

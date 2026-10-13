<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Security\ReauthGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * "Confirm your password" in front of every destructive admin action. It used to be
 * copied into three pages with no limit on wrong guesses.
 */
class ReauthGateTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $password = 'Correct-Horse-1!'): Admin
    {
        $admin = Admin::factory()->create(['password' => $password]);
        $this->actingAs($admin, 'admin');

        return $admin;
    }

    private function wrong(string $field = 'password'): ?string
    {
        try {
            ReauthGate::confirm('nope', $field);
        } catch (ValidationException $e) {
            return $e->errors()[$field][0] ?? null;
        }

        return null;
    }

    #[Test]
    public function the_right_password_passes(): void
    {
        $this->admin();

        ReauthGate::confirm('Correct-Horse-1!');

        $this->addToAssertionCount(1);
    }

    #[Test]
    public function a_wrong_or_missing_password_is_refused_with_the_standard_message(): void
    {
        $this->admin();

        $this->assertSame('Your password is incorrect.', $this->wrong());

        $this->expectException(ValidationException::class);
        ReauthGate::confirm(null);
    }

    #[Test]
    public function a_page_can_keep_its_own_translated_message_and_field(): void
    {
        $this->admin();

        try {
            ReauthGate::confirm('nope', 'applyPassword', 'Votre mot de passe est incorrect.');
            $this->fail('expected a ValidationException');
        } catch (ValidationException $e) {
            $this->assertSame(['applyPassword' => ['Votre mot de passe est incorrect.']], $e->errors());
        }
    }

    #[Test]
    public function after_five_wrong_passwords_even_the_right_one_is_refused(): void
    {
        $this->admin();

        for ($i = 0; $i < ReauthGate::MAX_ATTEMPTS; $i++) {
            $this->assertSame('Your password is incorrect.', $this->wrong());
        }

        try {
            ReauthGate::confirm('Correct-Horse-1!');
            $this->fail('the right password must be refused while locked out');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Too many wrong passwords', $e->errors()['password'][0]);
            $this->assertStringContainsString('minute', $e->errors()['password'][0]);
        }
    }

    #[Test]
    public function a_correct_password_before_the_limit_resets_the_count(): void
    {
        $this->admin();

        for ($i = 0; $i < ReauthGate::MAX_ATTEMPTS - 1; $i++) {
            $this->wrong();
        }

        ReauthGate::confirm('Correct-Horse-1!');

        // Four more wrong ones are fine again: the earlier four were cleared.
        for ($i = 0; $i < ReauthGate::MAX_ATTEMPTS - 1; $i++) {
            $this->assertSame('Your password is incorrect.', $this->wrong());
        }

        ReauthGate::confirm('Correct-Horse-1!');
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function the_lockout_is_per_admin_not_global(): void
    {
        $first = $this->admin();

        for ($i = 0; $i < ReauthGate::MAX_ATTEMPTS; $i++) {
            $this->wrong();
        }

        $second = Admin::factory()->create(['password' => 'Another-Pass-2@']);
        $this->actingAs($second, 'admin');

        ReauthGate::confirm('Another-Pass-2@');

        $this->actingAs($first, 'admin');
        $this->expectException(ValidationException::class);
        ReauthGate::confirm('Correct-Horse-1!');
    }

    #[Test]
    public function the_count_lives_in_the_cache_not_the_session(): void
    {
        $admin = $this->admin();
        $this->wrong();

        $this->assertSame(1, RateLimiter::attempts('reauth:'.$admin->getKey().'|'.request()->ip()));

        // A brand-new session (another browser, same admin and IP) sees the same count.
        $this->flushSession();
        $this->assertSame(1, RateLimiter::attempts('reauth:'.$admin->getKey().'|'.request()->ip()));
    }

    #[Test]
    public function no_admin_page_checks_a_password_on_its_own_any_more(): void
    {
        foreach (['BackupDashboard', 'CleanupDashboard', 'SystemUpdates'] as $page) {
            $source = (string) file_get_contents(app_path("Filament/Pages/System/{$page}.php"));

            $this->assertStringNotContainsString('Hash::check(', $source, "{$page} must confirm passwords through ReauthGate");
            $this->assertStringContainsString('ReauthGate::confirm(', $source);
        }
    }
}

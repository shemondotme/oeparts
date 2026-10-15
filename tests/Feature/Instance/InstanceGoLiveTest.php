<?php

namespace Tests\Feature\Instance;

use App\Filament\Pages\System\InstanceReadiness;
use App\Services\Instance\InstanceMode;
use App\Services\Instance\InstancePromoter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Instance\Concerns\ArrangesInstanceSite;
use Tests\Support\PlantsRows;
use Tests\TestCase;

/**
 * Phase 10.5: System > Site mode, taking a testing copy live: the host is typed out, the password gates it, activity on the copy and real card
 * payments have to be dealt with on purpose, and a refusal from a service reaches the person untouched.
 */
class InstanceGoLiveTest extends TestCase
{
    use ArrangesInstanceSite;
    use PlantsRows;
    use RefreshDatabase;

    // ---- go live ----------------------------------------------------------------------------------------------------------

    #[Test]
    public function a_copy_goes_live_with_its_host_typed_and_the_password(): void
    {
        $this->arrange('staging');

        Livewire::test(InstanceReadiness::class)
            ->callAction('goLive', data: ['host' => 'shop.example', 'password' => 'correct-horse'])
            ->assertHasNoActionErrors()
            ->assertNotified('The site is live')
            ->assertRedirect(InstanceReadiness::getUrl());

        $this->assertStringContainsString('OE_MODE="production"', $this->envText());
    }

    #[Test]
    public function the_host_is_typed_without_caring_about_capitals_or_spaces_like_the_command_line(): void
    {
        $this->arrange('staging');

        Livewire::test(InstanceReadiness::class)
            ->callAction('goLive', data: ['host' => '  Shop.Example ', 'password' => 'correct-horse'])
            ->assertHasNoActionErrors();

        $this->assertStringContainsString('OE_MODE="production"', $this->envText());
    }

    #[Test]
    public function the_wrong_host_is_an_error_on_the_host_field_and_the_copy_stays_a_copy(): void
    {
        $this->arrange('staging');

        Livewire::test(InstanceReadiness::class)
            ->callAction('goLive', data: ['host' => 'typo.example', 'password' => 'correct-horse'])
            ->assertHasActionErrors(['host'])
            ->assertNoRedirect();

        $this->assertStringContainsString('OE_MODE=staging', $this->envText());
    }

    #[Test]
    public function the_host_is_required(): void
    {
        $this->arrange('staging');

        Livewire::test(InstanceReadiness::class)
            ->callAction('goLive', data: ['password' => 'correct-horse'])
            ->assertHasActionErrors(['host' => 'required']);

        $this->assertStringContainsString('OE_MODE=staging', $this->envText());
    }

    #[Test]
    public function a_wrong_password_stops_going_live_before_any_service_is_asked(): void
    {
        $this->arrange('staging');

        Livewire::test(InstanceReadiness::class)
            ->callAction('goLive', data: ['host' => 'shop.example', 'password' => 'wrong'])
            ->assertHasActionErrors(['password']);

        $this->assertStringContainsString('OE_MODE=staging', $this->envText());
        $this->assertFileDoesNotExist($this->dir.'/readiness.json');
    }

    #[Test]
    public function activity_on_the_copy_blocks_going_live_until_the_person_chooses_to_discard_it(): void
    {
        $this->arrange('staging');
        $this->markTheCopy();
        $this->plantRow('contact_messages', []);

        Livewire::test(InstanceReadiness::class)
            ->callAction('goLive', data: ['host' => 'shop.example', 'password' => 'correct-horse'])
            ->assertNotified('This copy has been used')
            ->assertNoRedirect();
        $this->assertSame(1, DB::table('contact_messages')->count(), 'the refusal deleted nothing');
        $this->assertStringContainsString('OE_MODE=staging', $this->envText());

        Livewire::test(InstanceReadiness::class)
            ->callAction('goLive', data: ['host' => 'shop.example', 'password' => 'correct-horse', 'discard_activity' => true])
            ->assertNotified('The site is live');

        $this->assertSame(0, DB::table('contact_messages')->count());
        $this->assertStringContainsString('OE_MODE="production"', $this->envText());
    }

    #[Test]
    public function the_discard_choice_is_offered_only_when_the_copy_has_been_used(): void
    {
        $this->arrange('staging');
        $this->markTheCopy();

        Livewire::test(InstanceReadiness::class)->mountAction('goLive')->assertFormFieldDoesNotExist('discard_activity');

        $this->plantRow('contact_messages', []);

        Livewire::test(InstanceReadiness::class)->mountAction('goLive')->assertFormFieldExists('discard_activity');
    }

    #[Test]
    public function real_card_payments_have_to_be_confirmed_on_purpose_on_the_field_itself(): void
    {
        $this->arrange('staging');
        DB::table('settings')->updateOrInsert(['group' => 'payment', 'key' => 'airwallex_environment'], ['value' => 'live', 'type' => 'string', 'is_encrypted' => 0, 'created_at' => now(), 'updated_at' => now()]);

        Livewire::test(InstanceReadiness::class)
            ->callAction('goLive', data: ['host' => 'shop.example', 'password' => 'correct-horse'])
            ->assertHasActionErrors(['confirm_payments']);
        $this->assertStringContainsString('OE_MODE=staging', $this->envText());

        Livewire::test(InstanceReadiness::class)
            ->callAction('goLive', data: ['host' => 'shop.example', 'password' => 'correct-horse', 'confirm_payments' => true])
            ->assertNotified('The site is live');
        $this->assertStringContainsString('OE_MODE="production"', $this->envText());
    }

    #[Test]
    public function the_payments_field_is_there_only_when_the_card_gateway_is_live(): void
    {
        $this->arrange('staging');

        Livewire::test(InstanceReadiness::class)->mountAction('goLive')->assertFormFieldDoesNotExist('confirm_payments');

        DB::table('settings')->updateOrInsert(['group' => 'payment', 'key' => 'airwallex_environment'], ['value' => 'live', 'type' => 'string', 'is_encrypted' => 0, 'created_at' => now(), 'updated_at' => now()]);

        Livewire::test(InstanceReadiness::class)->mountAction('goLive')->assertFormFieldExists('confirm_payments');
    }

    #[Test]
    public function a_launch_check_that_fails_blocks_going_live_too(): void
    {
        $this->arrange('staging', [$this->probe('runtime_thing', 'fail', false)]);

        Livewire::test(InstanceReadiness::class)
            ->callAction('goLive', data: ['host' => 'shop.example', 'password' => 'correct-horse'])
            ->assertNotified('The launch checks are not all clear');

        $this->assertStringContainsString('OE_MODE=staging', $this->envText());
    }

    #[Test]
    public function a_worker_that_kept_the_staging_mode_stops_the_launch_and_the_copy_stays_a_copy(): void
    {
        $this->arrange('staging', [$this->probe('fine', 'pass')]);
        $switcher = $this->stubbornWorkerSwitcher();

        Livewire::test(InstanceReadiness::class)
            ->callAction('goLive', data: ['host' => 'shop.example', 'password' => 'correct-horse'])
            ->assertNotified('A queue worker kept the old mode')
            ->assertNoRedirect();

        $this->assertSame(['production', 'staging'], $switcher->asked);
        $this->assertStringContainsString('OE_MODE=staging', $this->envText());
    }

    #[Test]
    public function something_unexpected_is_logged_and_never_shown_as_a_stack_trace(): void
    {
        $this->arrange('quarantine');
        $this->app->instance(InstancePromoter::class, new class extends InstancePromoter
        {
            public function __construct() {}

            public function release(InstanceMode $to, array $waivers = [], string $by = 'command line', ?string $requestHost = null): array
            {
                throw new \RuntimeException('disk exploded at /var/www/secret/path');
            }
        });

        Livewire::test(InstanceReadiness::class)
            ->callAction('release', data: ['to' => 'staging', 'password' => 'correct-horse'])
            ->assertNotified('The change could not be made')
            ->assertDontSee('disk exploded')
            ->assertDontSee('/var/www/secret/path');
    }
}

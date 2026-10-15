<?php

namespace Tests\Feature\Instance;

use App\Filament\Pages\System\InstanceReadiness;
use App\Models\Admin;
use App\Services\Instance\InstanceState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Feature\Instance\Concerns\ArrangesInstanceSite;
use Tests\Support\PlantsRows;
use Tests\TestCase;

/**
 * Phase 10.5: System > Site mode, releasing a site from quarantine: the kind of site must be chosen, the admin's password gates it before a
 * service is asked, a failing check blocks it and the service's own sentence reaches the screen, a waivable failure gets a reason field.
 */
class InstanceReleaseTest extends TestCase
{
    use ArrangesInstanceSite;
    use PlantsRows;
    use RefreshDatabase;

    // ---- release from quarantine ------------------------------------------------------------------------------------------

    #[Test]
    public function the_two_launch_actions_show_only_in_the_mode_they_belong_to(): void
    {
        $this->arrange('production');
        Livewire::test(InstanceReadiness::class)->assertActionHidden('release')->assertActionHidden('goLive');

        $this->arrange('quarantine');
        Livewire::test(InstanceReadiness::class)->assertActionVisible('release')->assertActionHidden('goLive');

        $this->arrange('staging');
        Livewire::test(InstanceReadiness::class)->assertActionHidden('release')->assertActionVisible('goLive');
    }

    #[Test]
    public function a_clear_site_is_released_with_the_password_and_the_page_reloads_in_the_new_mode(): void
    {
        $this->arrange('quarantine');

        Livewire::test(InstanceReadiness::class)
            ->callAction('release', data: ['to' => 'staging', 'password' => 'correct-horse'])
            ->assertHasNoActionErrors()
            ->assertNotified('The site is released')
            ->assertRedirect(InstanceReadiness::getUrl());

        $this->assertStringContainsString('OE_MODE="staging"', $this->envText());
    }

    #[Test]
    public function the_kind_of_site_defaults_to_what_was_chosen_at_import_and_is_otherwise_empty(): void
    {
        $this->arrange('quarantine');
        Livewire::test(InstanceReadiness::class)->mountAction('release')->assertActionDataSet(['to' => null]);

        (new InstanceState)->update(function (array $ledger): array {
            $ledger['import'] = ['intent' => 'staging'];

            return $ledger;
        });
        Livewire::test(InstanceReadiness::class)->mountAction('release')->assertActionDataSet(['to' => 'staging']);
    }

    #[Test]
    public function the_kind_of_site_must_be_chosen(): void
    {
        $this->arrange('quarantine');

        Livewire::test(InstanceReadiness::class)
            ->callAction('release', data: ['password' => 'correct-horse'])
            ->assertHasActionErrors(['to' => 'required']);

        $this->assertStringContainsString('OE_MODE=quarantine', $this->envText());
    }

    #[Test]
    public function a_wrong_password_stops_a_release_before_any_service_is_asked(): void
    {
        $this->arrange('quarantine');

        Livewire::test(InstanceReadiness::class)
            ->callAction('release', data: ['to' => 'staging', 'password' => 'wrong'])
            ->assertHasActionErrors(['password']);

        $this->assertStringContainsString('OE_MODE=quarantine', $this->envText());
        $this->assertFileDoesNotExist($this->dir.'/readiness.json', 'not even the checks were run');
    }

    #[Test]
    public function after_five_wrong_passwords_even_the_right_one_is_refused_for_a_while(): void
    {
        $this->arrange('quarantine');

        for ($i = 0; $i < 5; $i++) {
            Livewire::test(InstanceReadiness::class)->callAction('release', data: ['to' => 'staging', 'password' => 'bad'.$i]);
        }

        Livewire::test(InstanceReadiness::class)
            ->callAction('release', data: ['to' => 'staging', 'password' => 'correct-horse'])
            ->assertHasActionErrors(['password']);

        $this->assertStringContainsString('OE_MODE=quarantine', $this->envText());
    }

    #[Test]
    public function a_failing_check_blocks_the_release_and_the_services_sentence_reaches_the_screen(): void
    {
        $this->arrange('quarantine', [$this->probe('rows_vs_manifest', 'fail', false, 'data', 'Counts differ.'), $this->probe('fine', 'pass')]);

        Livewire::test(InstanceReadiness::class)
            ->callAction('release', data: ['to' => 'production', 'password' => 'correct-horse'])
            ->assertNotified('The launch checks are not all clear')
            ->assertNoRedirect();

        $this->assertStringContainsString('OE_MODE=quarantine', $this->envText());
        $this->assertSame('rows_vs_manifest', json_decode((string) file_get_contents($this->dir.'/readiness.json'), true)['checks'][0]['key'], 'the run the refusal made is the report the tables show');
    }

    #[Test]
    public function a_failure_that_may_be_waived_gets_a_reason_field_and_the_reason_is_kept(): void
    {
        $this->arrange('quarantine', [$this->probe('storage_link', 'fail', true, 'runtime', 'The link is missing.'), $this->probe('fine', 'pass')]);
        $this->pageAfterChecks(); // the report on file is what decides which reason fields the release dialog has

        Livewire::test(InstanceReadiness::class)
            ->callAction('release', data: ['to' => 'staging', 'password' => 'correct-horse', 'waive_storage_link' => 'short'])
            ->assertHasActionErrors(['waive_storage_link']);
        $this->assertStringContainsString('OE_MODE=quarantine', $this->envText());

        Livewire::test(InstanceReadiness::class)
            ->callAction('release', data: ['to' => 'staging', 'password' => 'correct-horse', 'waive_storage_link' => 'the host panel makes the link after launch'])
            ->assertHasNoActionErrors();

        $this->assertStringContainsString('OE_MODE="staging"', $this->envText());
        $waiver = $this->ledger()['waivers'][0];
        $this->assertSame('storage_link', $waiver['key']);
        $this->assertSame('the host panel makes the link after launch', $waiver['reason']);
        $this->assertSame('admin #'.$this->admin->getKey(), $waiver['by']);
    }

    #[Test]
    public function a_failure_that_may_not_be_waived_has_no_reason_field_and_blocks_the_release(): void
    {
        $this->arrange('quarantine', [$this->probe('queue_worker_mode', 'fail', false, 'runtime', 'A worker is out of date.')]);
        $this->pageAfterChecks();

        Livewire::test(InstanceReadiness::class)
            ->callAction('release', data: ['to' => 'staging', 'password' => 'correct-horse', 'waive_queue_worker_mode' => 'we will restart it later, promise'])
            ->assertNotified('The launch checks are not all clear');

        $this->assertStringContainsString('OE_MODE=quarantine', $this->envText());
        $this->assertSame([], $this->ledger()['waivers'] ?? []);
    }

    #[Test]
    public function a_worker_that_keeps_the_old_mode_undoes_the_release_and_the_person_is_told(): void
    {
        $this->arrange('quarantine', [$this->probe('fine', 'pass')]);
        $switcher = $this->stubbornWorkerSwitcher();

        Livewire::test(InstanceReadiness::class)
            ->callAction('release', data: ['to' => 'production', 'password' => 'correct-horse'])
            ->assertNotified('A queue worker kept the old mode')
            ->assertNoRedirect();

        $this->assertSame(['production', 'quarantine'], $switcher->asked, 'the change was asked for, then put back');
    }
}

<?php

namespace Tests\Feature\Migration;

use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportStatus;
use App\Services\Updates\UpdateChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DrivesInstallerRuns;
use Tests\TestCase;

/**
 * The runner's confirm panel and the call behind it, through the REAL engine: a package built for this database, the installer's
 * pages, the engine's verify / schema / preflight stages, then the facts the panel shows and the POST that says "go".
 *
 * The point of the file is the part the installer cannot decide on its own. At step 4 the new site's database has no structure,
 * so what a choice drags in (product pictures need products) is resolved only now, from the real foreign keys; and ImportManager::confirm()
 * stays the judge of everything else (failed checks, every acknowledgement, a changed file). The run is stopped right after "go":
 * nothing here closes the site, writes `.env` or touches storage/app/migration.
 */
class InstallerRunnerConfirmTest extends TestCase
{
    use DrivesInstallerRuns;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareInstallerRunTest();
    }

    protected function tearDown(): void
    {
        $this->cleanUpInstallerRunTest();

        parent::tearDown();
    }

    /**
     * A package that says it carries `$packageGroups`, opened, chosen with `$choices`, started, and driven to the confirmation.
     *
     * @param  list<string>  $packageGroups
     * @param  array<string, mixed>  $choices
     * @param  array<string, mixed>  $meta
     * @return array{id: string, token: string, location: string, answer: array<string, mixed>}
     */
    private function runFor(array $packageGroups, array $choices, array $meta = []): array
    {
        $this->buildRunnablePackage('shop.oeparts', $packageGroups, $meta);
        $this->openRunnablePackage();
        $run = $this->startRunFromInstaller($choices);

        return $run + ['answer' => $this->advanceToConfirmation($run['id'], $run['token'])];
    }

    /** @return array<string, mixed> */
    private function confirmPost(string $id, string $token, array $body): TestResponse
    {
        return $this->postJson("/migrate/run/{$id}/confirm", $body, $this->tokenHeader($token));
    }

    // --- a preset: nothing to add ---

    #[Test]
    public function a_preset_the_package_can_honour_is_shown_and_confirmed_with_exactly_those_groups(): void
    {
        $run = $this->runFor(['core', 'catalog', 'catalog_files', 'media'], ['selection' => 'preset:catalog_only']);
        $answer = $run['answer'];

        $this->assertSame('awaiting_confirmation', $answer['status'], $this->whyNotReady($answer));
        $confirm = $answer['confirm'];
        $this->assertTrue($confirm['can_start'], $this->whyNotReady($answer));
        $this->assertFalse($confirm['blocked_by_checks']);
        $this->assertSame('installer', $confirm['door']);
        $this->assertFalse($confirm['typed_required'], 'the installer\'s target is new: there is nothing to type REPLACE for');
        $this->assertSame('live', $confirm['launch']);
        $this->assertSame('http://shop.example', $confirm['url']);
        $this->assertTrue($confirm['selection']['asked']);
        $this->assertSame([], $confirm['selection']['added']);
        $this->assertNull($confirm['selection']['error']);
        $this->assertEqualsCanonicalizing(['core', 'catalog', 'catalog_files', 'media'], $confirm['selection']['groups']);
        $this->assertEqualsCanonicalizing(['Site basics', 'Products', 'Product pictures', 'Media library'], $confirm['selection']['labels']);
        $this->assertNotEmpty($confirm['checks']);

        $this->confirmPost($run['id'], $run['token'], ['acks' => $this->allAcks($answer)])
            ->assertOk()
            ->assertJsonMissingPath('confirm')
            ->assertJsonPath('status', 'running');

        $state = $this->runStore()->find($run['id']);
        $this->assertTrue($state->flag('confirmed'));
        $this->assertSame(ImportStatus::Running, $state->status);
        $this->assertEqualsCanonicalizing(['core', 'catalog', 'catalog_files', 'media'], $state->options['groups']);
        $this->assertSame('installer', $state->options['door'], 'the door is the installer\'s, not the request\'s');
        $this->assertSame('http://shop.example', $state->options['url']);
        $this->assertFalse(app(ImportMarker::class)->isActive(), 'confirming closes nothing: the arm stage is the first thing that does');
    }

    // --- "Let me choose": a part that cannot work without another ---
    //
    // Orders alone add nothing: their references to customers are optional columns (the resolver cuts them in the copy, it does not
    // pull the customers in). The real case is a file group, which cannot work without the group whose rows point at its files:
    // Product pictures need Products.

    #[Test]
    public function product_pictures_alone_add_the_products_when_the_package_holds_them_show_why_and_need_a_second_confirm(): void
    {
        $run = $this->runFor(self::ALL_GROUPS, ['selection' => 'custom', 'groups' => ['catalog_files']]);
        $answer = $run['answer'];

        $this->assertSame('awaiting_confirmation', $answer['status'], $this->whyNotReady($answer));
        $selection = $answer['confirm']['selection'];
        $addedKeys = array_column($selection['added'], 'key');

        $this->assertSame(['catalog'], $addedKeys, 'product pictures cannot work without the products whose rows point at them');
        $this->assertContains('catalog_files', $selection['groups']);
        $this->assertContains('catalog', $selection['groups']);
        $this->assertContains('Products', $selection['labels']);
        $products = $selection['added'][0];
        $this->assertSame('Products', $products['label']);
        $this->assertContains('Product pictures', $products['because'], 'the page can say what needs it');
        $this->assertTrue($answer['confirm']['can_start'], $this->whyNotReady($answer));

        // Without the tick that says "I have read what was added", nothing starts.
        $this->confirmPost($run['id'], $run['token'], ['acks' => $this->allAcks($answer)])
            ->assertStatus(422)
            ->assertJsonPath('error', 'groups_not_acknowledged');
        $state = $this->runStore()->find($run['id']);
        $this->assertFalse($state->flag('confirmed'));
        $this->assertSame(ImportStatus::AwaitingConfirmation, $state->status);
        $this->assertArrayNotHasKey('groups', $state->options);

        // With it, the run is frozen with the groups the resolver gave.
        $this->confirmPost($run['id'], $run['token'], ['acks' => $this->allAcks($answer), 'groups_ack' => true])->assertOk();
        $state = $this->runStore()->find($run['id']);
        $this->assertTrue($state->flag('confirmed'));
        $this->assertEqualsCanonicalizing($selection['groups'], $state->options['groups']);
        $this->assertContains('catalog', $state->options['groups']);
    }

    #[Test]
    public function product_pictures_without_the_products_in_the_package_are_refused_in_plain_words_and_nothing_starts(): void
    {
        $run = $this->runFor(['core', 'catalog_files'], ['selection' => 'custom', 'groups' => ['catalog_files']]);
        $answer = $run['answer'];

        $this->assertSame('awaiting_confirmation', $answer['status'], $this->whyNotReady($answer));
        $confirm = $answer['confirm'];
        $this->assertFalse($confirm['can_start']);
        $this->assertFalse($confirm['blocked_by_checks'], 'the checks passed; it is the choice that cannot work');
        $this->assertStringContainsString('Products', $confirm['selection']['error']);
        $this->assertStringContainsString('this package does not hold', $confirm['selection']['error']);
        $this->assertStringNotContainsString('catalog', $confirm['selection']['error'], 'groups are named as people know them, not by key');

        $response = $this->confirmPost($run['id'], $run['token'], ['acks' => $this->allAcks($answer), 'groups_ack' => true])
            ->assertStatus(422)
            ->assertJsonPath('error', 'selection_refused');
        $this->assertStringContainsString('Products', (string) $response->json('message'));
        $this->assertNotSame('', (string) $response->json('fix'));

        $state = $this->runStore()->find($run['id']);
        $this->assertFalse($state->flag('confirmed'));
        $this->assertSame(ImportStatus::AwaitingConfirmation, $state->status);
        $this->assertSame('preflight', $state->stage);
        $this->assertArrayNotHasKey('groups', $state->options);
        $this->assertFalse(app(ImportMarker::class)->isActive());
    }

    #[Test]
    public function a_part_the_package_lacks_is_refused_even_when_the_request_names_one_to_use_instead(): void
    {
        $run = $this->runFor(['core', 'catalog_files'], ['selection' => 'custom', 'groups' => ['catalog_files']]);

        // A request cannot change the choice: it only says "go".
        $this->confirmPost($run['id'], $run['token'], ['groups' => ['core'], 'options' => ['groups' => ['core']], 'groups_ack' => true, 'acks' => $this->allAcks($run['answer'])])
            ->assertStatus(422)
            ->assertJsonPath('error', 'selection_refused');

        $this->assertArrayNotHasKey('groups', $this->runStore()->find($run['id'])->options);
    }

    // --- every warning has its own tick; the engine is the judge ---

    #[Test]
    public function every_warning_that_needs_an_acknowledgement_is_listed_and_the_engine_refuses_without_it(): void
    {
        $version = app(UpdateChecker::class)->currentVersion();
        $major = (int) explode('.', $version)[0];

        if ($version === 'unknown' || $major < 1) {
            $this->markTestSkipped('The release of this checkout is unknown, so a package of another major release cannot be made.');
        }

        $run = $this->runFor(['core', 'catalog', 'catalog_files', 'media'], ['selection' => 'preset:catalog_only'], ['app_version' => ($major - 1).'.0.0']);
        $answer = $run['answer'];

        $this->assertSame('awaiting_confirmation', $answer['status'], $this->whyNotReady($answer));
        $this->assertTrue($answer['confirm']['can_start'], $this->whyNotReady($answer));
        $this->assertContains('version_major_jump', $this->allAcks($answer), 'the warning is listed with its own tick');
        $listed = $answer['confirm']['acks'][array_search('version_major_jump', $this->allAcks($answer), true)];
        $this->assertNotSame('', $listed['label']);
        $this->assertNotSame('', $listed['message']);

        foreach ([[], ['something_else'], [''], 'version_major_jump'] as $given) {
            $this->confirmPost($run['id'], $run['token'], ['acks' => $given])
                ->assertStatus(422)
                ->assertJsonPath('error', 'acknowledgement_missing');
        }

        $this->assertFalse($this->runStore()->find($run['id'])->flag('confirmed'));

        $this->confirmPost($run['id'], $run['token'], ['acks' => $this->allAcks($answer)])->assertOk();
        $this->assertTrue($this->runStore()->find($run['id'])->flag('confirmed'));
        $this->assertSame($this->allAcks($answer), $this->runStore()->find($run['id'])->options['acks']);
    }

    // --- what a request can and cannot do ---

    #[Test]
    public function the_request_supplies_answers_only_never_options(): void
    {
        $run = $this->runFor(['core', 'catalog', 'catalog_files', 'media'], ['selection' => 'preset:catalog_only']);
        $before = $this->runStore()->find($run['id'])->options;

        $this->confirmPost($run['id'], $run['token'], [
            'acks' => $this->allAcks($run['answer']),
            'options' => ['groups' => ['system', 'logs'], 'adopt_escrow' => 'wiz-0123456789abcdef01234567', 'door' => 'cli', 'url' => 'https://evil.example', 'launch' => 'staging', 'include_secrets' => true],
            'groups' => ['system', 'logs'],
            'door' => 'cli',
            'url' => 'https://evil.example',
            'launch' => 'staging',
        ])->assertOk();

        $options = $this->runStore()->find($run['id'])->options;
        $this->assertEqualsCanonicalizing(['core', 'catalog', 'catalog_files', 'media'], $options['groups']);
        $this->assertSame('installer', $options['door']);
        $this->assertSame($before['url'], $options['url']);
        $this->assertSame($before['launch'], $options['launch']);
        $this->assertSame($before['adopt_escrow'], $options['adopt_escrow']);
        $this->assertArrayNotHasKey('include_secrets', $options);
        $this->assertEqualsCanonicalizing(['groups', 'acks', 'confirmed_at'], array_keys(array_diff_key($options, $before)), 'confirming adds exactly the frozen groups, the ticks and the time');
    }

    #[Test]
    public function the_call_needs_the_runs_token_and_a_run_that_is_waiting(): void
    {
        $run = $this->runFor(['core', 'catalog', 'catalog_files', 'media'], ['selection' => 'preset:catalog_only']);

        // The token is the only credential, and only in the header.
        $this->postJson("/migrate/run/{$run['id']}/confirm", ['acks' => []])->assertNotFound();
        $this->postJson("/migrate/run/{$run['id']}/confirm?token={$run['token']}", ['token' => $run['token']])->assertNotFound();
        $this->confirmPost($run['id'], str_repeat('0', 64), [])->assertNotFound();
        $this->confirmPost('0123456789abcdef', $run['token'], [])->assertNotFound();
        $this->assertFalse($this->runStore()->find($run['id'])->flag('confirmed'));

        // Once confirmed, a second call is "not waiting", not another confirmation.
        $this->confirmPost($run['id'], $run['token'], ['acks' => $this->allAcks($run['answer'])])->assertOk();
        $this->confirmPost($run['id'], $run['token'], ['acks' => $this->allAcks($run['answer'])])
            ->assertStatus(409)
            ->assertJsonPath('error', 'not_ready_to_confirm');
    }

    #[Test]
    public function a_run_that_is_still_checking_is_not_waiting(): void
    {
        $this->buildRunnablePackage();
        $this->openRunnablePackage();
        $run = $this->startRunFromInstaller(['selection' => 'preset:catalog_only']);

        // Located, not yet verified: the panel has nothing to show and the call has nothing to confirm.
        $this->getJson("/migrate/run/{$run['id']}/status", $this->tokenHeader($run['token']))->assertOk()->assertJsonMissingPath('confirm');
        $this->confirmPost($run['id'], $run['token'], [])->assertStatus(409)->assertJsonPath('error', 'not_ready_to_confirm');
    }

    #[Test]
    public function the_runner_page_carries_the_panel_and_the_status_carries_its_facts_only_while_waiting(): void
    {
        $run = $this->runFor(['core', 'catalog', 'catalog_files', 'media'], ['selection' => 'preset:catalog_only']);

        $page = $this->get("/migrate/run/{$run['id']}");
        $page->assertOk();
        $page->assertSee('id="oe-confirm"', false);
        $page->assertSee('id="oe-start"', false);
        $page->assertSee('oeRunnerPanels', false);
        $page->assertSee('/confirm', false);

        $status = $this->getJson("/migrate/run/{$run['id']}/status", $this->tokenHeader($run['token']))->assertOk();
        $status->assertJsonPath('confirm.can_start', true);
        $status->assertJsonMissingPath('report_url');
        // The facts name no path, key or token.
        $text = (string) $status->getContent();
        $this->assertStringNotContainsString($run['token'], $text);
        $this->assertStringNotContainsString($this->passphrase, $text);
        $this->assertStringNotContainsString($this->tmp, $text);
    }

    // --- the end of a run that did not finish: the signed report ---

    #[Test]
    public function a_run_that_stops_hands_out_a_signed_report_that_says_nothing_was_changed(): void
    {
        $this->buildRunnablePackage();
        $this->openRunnablePackage();
        $run = $this->startRunFromInstaller(['selection' => 'preset:catalog_only']);

        // The file is touched after it was pinned: the engine stops the run while it is still harmless.
        file_put_contents($this->inboxDir().DIRECTORY_SEPARATOR.'shop.oeparts', 'x', FILE_APPEND);

        $answer = $this->postJson("/migrate/run/{$run['id']}/advance", [], $this->tokenHeader($run['token']))->assertOk()->json();

        $this->assertSame('failed', $answer['status']);
        $this->assertSame('package_changed', $answer['error']['code']);
        $this->assertIsString($answer['report_url']);
        $this->assertStringContainsString('/install/import/report/'.$run['id'], $answer['report_url']);
        $this->assertStringContainsString('signature=', $answer['report_url']);

        $page = $this->get($answer['report_url']);
        $page->assertOk();
        $page->assertSee('The import did not finish');
        $page->assertSee('before anything was changed');
        $content = (string) $page->getContent();
        $this->assertStringNotContainsString($run['token'], $content);
        $this->assertStringNotContainsString($this->passphrase, $content);

        // The token died with the run; the signed report is what is left.
        $this->postJson("/migrate/run/{$run['id']}/advance", [], $this->tokenHeader($run['token']))->assertNotFound();
        $this->get("/install/import/report/{$run['id']}")->assertForbidden();
    }
}

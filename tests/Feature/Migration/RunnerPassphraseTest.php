<?php

namespace Tests\Feature\Migration;

use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\DrivesInstallerRuns;
use Tests\TestCase;

/**
 * The runner's passphrase call (POST /migrate/run/{id}/passphrase): the way out for a run that lost its stored key. Same door as
 * the other runner calls (the run's token in the header only), the engine's own throttle, and the passphrase and the keys derived
 * from it written nowhere: not in an answer, not in the logs, not in the run's files.
 */
class RunnerPassphraseTest extends TestCase
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
     * A real run whose stored key is gone, the way a 24 h expiry or a refused hand-over leaves it.
     *
     * @return array{id: string, token: string, keys: list<string>}
     */
    private function waitingRun(): array
    {
        $this->buildRunnablePackage();
        $this->openRunnablePackage();
        $run = $this->startRunFromInstaller(['selection' => 'preset:catalog_only']);
        $this->advanceToConfirmation($run['id'], $run['token']);

        $escrowId = ImportContext::escrowId($run['id']);
        $keys = array_values($this->escrow->require($escrowId)->keys->subkeys());
        $this->escrow->delete($escrowId);

        $this->runStore()->update($run['id'], static function ($state): void {
            $state->status = ImportStatus::Running;
            $state->flags['needs_passphrase'] = true;
        });

        return ['id' => $run['id'], 'token' => $run['token'], 'keys' => $keys];
    }

    private function sendPassphrase(string $id, string $token, string $passphrase): \Illuminate\Testing\TestResponse
    {
        return $this->postJson("/migrate/run/{$id}/passphrase", ['passphrase' => $passphrase], $this->tokenHeader($token));
    }

    #[Test]
    public function it_is_refused_without_the_token_with_a_wrong_token_and_with_the_token_anywhere_but_the_header(): void
    {
        $run = $this->waitingRun();
        $uri = "/migrate/run/{$run['id']}/passphrase";
        $body = ['passphrase' => $this->passphrase];

        $this->postJson($uri, $body)->assertStatus(404)->assertExactJson(['error' => 'token_invalid']);
        $this->postJson($uri, $body, $this->tokenHeader('wrong'))->assertStatus(404)->assertExactJson(['error' => 'token_invalid']);
        $this->postJson('/migrate/run/0123456789abcdef/passphrase', $body, $this->tokenHeader($run['token']))->assertStatus(404)->assertExactJson(['error' => 'token_invalid']);
        $this->postJson($uri.'?token='.$run['token'].'&_token='.$run['token'], $body)->assertStatus(404)->assertExactJson(['error' => 'token_invalid']);
        $this->postJson($uri, $body + ['token' => $run['token'], '_token' => $run['token']])->assertStatus(404)->assertExactJson(['error' => 'token_invalid']);
        $this->call('POST', $uri, [], ['t' => $run['token'], 'token' => $run['token']], [], ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'], (string) json_encode($body))->assertStatus(404);

        $this->assertTrue($this->runStore()->find($run['id'])->flag('needs_passphrase'), 'a refused call changed nothing');
    }

    #[Test]
    public function it_is_a_plain_409_while_the_run_is_not_waiting_for_a_passphrase(): void
    {
        $this->buildRunnablePackage();
        $this->openRunnablePackage();
        $run = $this->startRunFromInstaller(['selection' => 'preset:catalog_only']);
        $this->advanceToConfirmation($run['id'], $run['token']);

        $this->sendPassphrase($run['id'], $run['token'], $this->passphrase)->assertStatus(409)->assertJsonPath('error', 'not_waiting_for_passphrase');
        $this->assertNotNull($this->escrow->get(ImportContext::escrowId($run['id'])), 'the run was not touched');
    }

    #[Test]
    public function a_wrong_passphrase_gives_a_field_error_and_the_run_does_not_advance(): void
    {
        $run = $this->waitingRun();
        $before = $this->runStore()->find($run['id'])->stage;

        $response = $this->sendPassphrase($run['id'], $run['token'], 'definitely-not-it');

        $response->assertStatus(422)->assertJsonPath('error', 'passphrase_wrong');
        $this->assertStringContainsString('does not open this package', (string) $response->json('message'));
        $after = $this->runStore()->find($run['id']);
        $this->assertSame($before, $after->stage);
        $this->assertTrue($after->flag('needs_passphrase'));
        $this->assertNull($this->escrow->get(ImportContext::escrowId($run['id'])), 'no key was stored');
    }

    #[Test]
    public function an_empty_missing_or_oversized_passphrase_is_refused_before_it_is_tried(): void
    {
        $run = $this->waitingRun();
        $uri = "/migrate/run/{$run['id']}/passphrase";

        $this->postJson($uri, ['passphrase' => ''], $this->tokenHeader($run['token']))->assertStatus(422)->assertJsonPath('error', 'passphrase_empty');
        $this->postJson($uri, [], $this->tokenHeader($run['token']))->assertStatus(422)->assertJsonPath('error', 'passphrase_empty');
        $this->postJson($uri, ['passphrase' => ['a']], $this->tokenHeader($run['token']))->assertStatus(422);
        $this->postJson($uri, ['passphrase' => str_repeat('a', 1025)], $this->tokenHeader($run['token']))->assertStatus(422);
        $this->assertTrue($this->runStore()->find($run['id'])->flag('needs_passphrase'));

        // None of those reached the key derivation: five real wrong tries are still allowed afterwards.
        for ($i = 1; $i <= 5; $i++) {
            $this->sendPassphrase($run['id'], $run['token'], "wrong-{$i}")->assertStatus(422);
        }
    }

    #[Test]
    public function the_sixth_wrong_attempt_hits_the_throttle_with_the_seconds_to_wait(): void
    {
        $run = $this->waitingRun();

        for ($i = 1; $i <= 5; $i++) {
            $this->sendPassphrase($run['id'], $run['token'], "wrong-{$i}")->assertStatus(422)->assertJsonPath('error', 'passphrase_wrong');
        }

        $locked = $this->sendPassphrase($run['id'], $run['token'], 'wrong-6');
        $locked->assertStatus(429)->assertJsonPath('error', 'passphrase_locked');
        $this->assertGreaterThan(0, $locked->json('retry_after'));
        $this->assertSame((string) $locked->json('retry_after'), $locked->headers->get('Retry-After'));
        $this->assertStringContainsString((string) $locked->json('retry_after'), (string) $locked->json('message'));

        // Even the right passphrase waits while the lock lasts.
        $this->sendPassphrase($run['id'], $run['token'], $this->passphrase)->assertStatus(429);
        $this->assertTrue($this->runStore()->find($run['id'])->flag('needs_passphrase'));
    }

    #[Test]
    public function the_right_passphrase_restores_the_key_and_the_run_carries_on(): void
    {
        $run = $this->waitingRun();

        $response = $this->sendPassphrase($run['id'], $run['token'], $this->passphrase);

        $response->assertOk()->assertJsonPath('found', true)->assertJsonPath('needs_passphrase', false);
        $this->assertNotNull($this->escrow->get(ImportContext::escrowId($run['id'])), 'the key is back');
        $this->assertFalse($this->runStore()->find($run['id'])->flag('needs_passphrase'));
        $this->assertNotSame('failed', $response->json('status'));

        // And the normal loop goes on from here.
        $this->postJson("/migrate/run/{$run['id']}/advance", [], $this->tokenHeader($run['token']))->assertOk()->assertJsonPath('needs_passphrase', false);
    }

    #[Test]
    public function neither_the_passphrase_nor_its_keys_are_written_or_answered_anywhere(): void
    {
        $run = $this->waitingRun();
        $needles = [$this->passphrase, 'definitely-not-it'];
        foreach ($run['keys'] as $key) {
            $needles[] = $key;
            $needles[] = bin2hex($key);
            $needles[] = base64_encode($key);
        }

        $answers = [
            $this->sendPassphrase($run['id'], $run['token'], 'definitely-not-it')->getContent(),
            $this->sendPassphrase($run['id'], $run['token'], $this->passphrase)->getContent(),
            $this->getJson("/migrate/run/{$run['id']}/status", $this->tokenHeader($run['token']))->getContent(),
            $this->get("/migrate/run/{$run['id']}")->getContent(),
        ];

        foreach ($answers as $answer) {
            foreach ($needles as $needle) {
                $this->assertStringNotContainsString($needle, (string) $answer);
            }
        }

        $files = [];
        foreach ([$this->tmp.DIRECTORY_SEPARATOR.'runs', storage_path('logs'), storage_path('app/migration')] as $dir) {
            if (is_dir($dir)) {
                foreach (File::allFiles($dir) as $file) {
                    $files[] = $file->getPathname();
                }
            }
        }

        $this->assertNotEmpty($files, 'the run state file exists');
        foreach ($files as $path) {
            $text = (string) file_get_contents($path);
            foreach ($needles as $needle) {
                $this->assertStringNotContainsString($needle, $text, "found in {$path}");
            }
        }
    }

    #[Test]
    public function the_page_of_a_run_that_needs_the_passphrase_carries_the_panel_and_never_the_passphrase(): void
    {
        $run = $this->waitingRun();
        $this->sendPassphrase($run['id'], $run['token'], 'definitely-not-it');

        $html = (string) $this->get("/migrate/run/{$run['id']}")->assertOk()->getContent();

        $this->assertStringContainsString('id="oe-passphrase"', $html);
        $this->assertStringContainsString('type="password"', $html);
        $this->assertStringContainsString('autocomplete="off"', $html);
        $this->assertStringContainsString('Open package', $html);
        $this->assertStringContainsString("'/passphrase'", $html);
        $this->assertStringNotContainsString($this->passphrase, $html);
        $this->assertStringNotContainsString('definitely-not-it', $html);
        $this->assertStringNotContainsString($run['token'], $html);
    }
}

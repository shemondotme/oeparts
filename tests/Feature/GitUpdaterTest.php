<?php

namespace Tests\Feature;

use App\Services\Updates\Exceptions\UpdateException;
use App\Services\Updates\GitUpdater;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * GitUpdater (git-managed deployment update path). isGitManaged()/
 * toolingAvailable() are cheap detection; checkout()/rollbackTo() are proven
 * against a REAL local git repo with two tagged commits (not mocked) — this is
 * shell-out plumbing, so the only trustworthy proof is that a real `git
 * checkout` actually moves the working tree between real commits.
 */
class GitUpdaterTest extends TestCase
{
    private string $root;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-gitupdater-'.getmypid();
        @mkdir($this->root, 0775, true);
        config(['updates.root_path' => $this->root]);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->root);
        $this->rrmdir($this->root.'-origin.git');
        parent::tearDown();
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir.DIRECTORY_SEPARATOR.$entry;
            is_dir($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }

    private function git(array $args): void
    {
        $process = new \Symfony\Component\Process\Process(array_merge(['git'], $args), $this->root);
        $process->run();
        if (! $process->isSuccessful()) {
            $this->fail('git '.implode(' ', $args).' failed: '.$process->getErrorOutput());
        }
    }

    /** A real local repo, no remote — checkout()/rollbackTo() fetch from `origin`, so they use a local bare clone as a stand-in "origin". */
    private function initRepoWithTwoTaggedVersions(): void
    {
        $bare = $this->root.'-origin.git';
        @mkdir($bare, 0775, true);
        (new \Symfony\Component\Process\Process(['git', 'init', '--bare'], $bare))->mustRun();

        $this->git(['init']);
        $this->git(['config', 'user.email', 'test@test.local']);
        $this->git(['config', 'user.name', 'Test']);
        $this->git(['remote', 'add', 'origin', $bare]);

        // Minimal but real composer.json (no dependencies) — rollbackTo() runs
        // `composer install` for real, which needs this file to exist and be
        // valid or it fails before ever reaching the git assertions below.
        file_put_contents($this->root.'/composer.json', json_encode(['name' => 'oe/gitupdater-test']));

        file_put_contents($this->root.'/marker.txt', 'v1.0.0');
        $this->git(['add', '.']);
        $this->git(['commit', '-m', 'v1.0.0']);
        $this->git(['tag', 'v1.0.0']);

        file_put_contents($this->root.'/marker.txt', 'v1.1.0');
        $this->git(['add', '.']);
        $this->git(['commit', '-m', 'v1.1.0']);
        $this->git(['tag', 'v1.1.0']);

        $this->git(['push', 'origin', '--all']);
        $this->git(['push', 'origin', '--tags']);

        // Land back on the OLD tag, as if this install were still running it —
        // checkout() below proves it moves forward to the new one.
        $this->git(['checkout', 'v1.0.0']);
    }

    #[Test]
    public function is_git_managed_reflects_a_dot_git_directory(): void
    {
        $updater = new GitUpdater;
        $this->assertFalse($updater->isGitManaged());

        @mkdir($this->root.'/.git');
        $this->assertTrue($updater->isGitManaged());
    }

    #[Test]
    public function tooling_available_is_true_in_this_dev_environment(): void
    {
        // This repo's own CI/dev container has git + composer on PATH — proves
        // the detection logic itself works, not a specific host's config.
        $this->assertTrue((new GitUpdater)->toolingAvailable());
    }

    #[Test]
    public function checkout_fetches_and_moves_the_working_tree_to_the_target_tag(): void
    {
        $this->initRepoWithTwoTaggedVersions();
        $this->assertSame('v1.0.0', trim(file_get_contents($this->root.'/marker.txt')));

        (new GitUpdater)->checkout('1.1.0');

        $this->assertSame('v1.1.0', trim(file_get_contents($this->root.'/marker.txt')),
            'checkout() should have fetched origin and moved the working tree to the v1.1.0 tag.');
        $this->assertSame('v1.1.0', (new GitUpdater)->currentTag());
    }

    #[Test]
    public function checkout_throws_on_a_nonexistent_tag(): void
    {
        $this->initRepoWithTwoTaggedVersions();

        $this->expectException(UpdateException::class);
        (new GitUpdater)->checkout('9.9.9');
    }

    #[Test]
    public function rollback_to_moves_the_working_tree_back_to_the_previous_tag(): void
    {
        $this->initRepoWithTwoTaggedVersions();
        (new GitUpdater)->checkout('1.1.0');
        $this->assertSame('v1.1.0', trim(file_get_contents($this->root.'/marker.txt')));

        (new GitUpdater)->rollbackTo('1.0.0');

        $this->assertSame('v1.0.0', trim(file_get_contents($this->root.'/marker.txt')),
            'rollbackTo() should move the working tree back to the previous release tag.');
    }
}

<?php

namespace Tests\Unit\Migration;

use App\Security\PackagePathPolicy;
use App\Services\Migration\Export\AllowlistWalker;
use App\Services\Migration\Export\FileEntry;
use App\Services\Migration\Groups\FileRoot;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Phase 3.5: only the folders of the selected groups are ever opened, and a hostile tree inside them
 * (a .env, a PHP shell, key files, links out of the disk) cannot ride into a package.
 */
class AllowlistWalkerTest extends TestCase
{
    private string $base = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->base = sys_get_temp_dir().DIRECTORY_SEPARATOR.'oe-walk-'.getmypid().'-'.uniqid();
        mkdir($this->base.'/public/media/nested/deep', 0775, true);
        mkdir($this->base.'/local/payment-proofs', 0775, true);
        mkdir($this->base.'/local/invoices', 0775, true);
        mkdir($this->base.'/outside/secrets', 0775, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->base);
        parent::tearDown();
    }

    private function rrmdir(string $dir): void
    {
        if (! file_exists($dir) && ! is_link($dir)) {
            return;
        }

        if (is_link($dir) || ! is_dir($dir)) {
            @unlink($dir);

            return;
        }

        foreach (scandir($dir) ?: [] as $name) {
            if ($name !== '.' && $name !== '..') {
                $this->rrmdir($dir.'/'.$name);
            }
        }
        @rmdir($dir);
    }

    private function walker(): AllowlistWalker
    {
        return new AllowlistWalker(new PackagePathPolicy, [
            'public' => ['path' => $this->base.'/public', 'prefix' => 'storage/app/public'],
            'local' => ['path' => $this->base.'/local', 'prefix' => 'storage/app/private'],
        ]);
    }

    private function put(string $relative, string $content = 'x'): void
    {
        @mkdir(dirname($this->base.'/'.$relative), 0775, true);
        file_put_contents($this->base.'/'.$relative, $content);
    }

    /** @return array<int, string> */
    private function paths(array $files): array
    {
        $paths = array_map(fn (FileEntry $f): string => $f->disk.':'.$f->path, $files);
        sort($paths);

        return $paths;
    }

    /** @return array<string, int> */
    private function reasons(array $cursor): array
    {
        $counts = $cursor['skipped_counts'];
        ksort($counts);

        return $counts;
    }

    private function hostileTree(): void
    {
        $this->put('public/media/normal.jpg', 'jpg');
        $this->put('public/media/nested/deep/photo.png', 'png');
        $this->put('public/media/ünï.jpg', 'unicode');

        $this->put('public/media/.env', 'APP_KEY=base64:secret');
        $this->put('public/media/.env.production', 'x');
        $this->put('public/media/shell.php', '<?php system($_GET["c"]);');
        $this->put('public/media/x.php.jpg', 'x');
        $this->put('public/media/y.jpg.php', 'x');
        $this->put('public/media/.htaccess', 'AddHandler php-script .jpg');
        $this->put('public/media/id_rsa.pem', 'key');
        $this->put('public/media/server.key', 'key');
        $this->put('public/media/trailing.jpg ', 'x');
        $this->put('public/media/a:b.jpg', 'x');
        $this->put("public/media/cafe\u{0065}\u{0301}.jpg", 'nfd');
        $this->put('public/media/CON.jpg', 'x');

        $this->put('outside/secrets/real.env', 'APP_KEY=secret');
        $this->put('outside/secrets/photo.jpg', 'x');
        symlink($this->base.'/outside/secrets/real.env', $this->base.'/public/media/link-env.jpg');
        symlink($this->base.'/outside/secrets', $this->base.'/public/media/evil');
        symlink($this->base.'/public/media/normal.jpg', $this->base.'/public/media/alias.jpg');

        $this->put('local/payment-proofs/proof.pdf', 'pdf');
        $this->put('local/invoices/2026-0001.pdf', 'pdf');
    }

    #[Test]
    public function a_clean_tree_yields_every_file_with_its_size_and_a_disk_relative_path(): void
    {
        $this->put('public/media/a.jpg', '12345');
        $this->put('public/media/nested/b.png', '123');

        $result = $this->walker()->all([FileRoot::parse('public:media')]);

        $this->assertSame(['public:media/a.jpg', 'public:media/nested/b.png'], $this->paths($result['files']));
        $this->assertSame(['files' => 2, 'bytes' => 8, 'folders' => 3], $result['cursor']['stats']);
        $this->assertSame([], $result['cursor']['skipped_counts']);
        $this->assertSame(5, $result['files'][0]->size);
    }

    #[Test]
    public function a_hostile_tree_only_yields_the_innocent_files(): void
    {
        $this->hostileTree();

        $result = $this->walker()->all([FileRoot::parse('public:media'), FileRoot::parse('local:payment-proofs')]);

        $this->assertSame([
            'local:payment-proofs/proof.pdf',
            'public:media/nested/deep/photo.png',
            'public:media/normal.jpg',
            'public:media/ünï.jpg',
        ], $this->paths($result['files']));
    }

    #[Test]
    public function every_refusal_is_counted_by_reason_and_listed(): void
    {
        $this->hostileTree();

        $result = $this->walker()->all([FileRoot::parse('public:media')]);
        $reasons = $this->reasons($result['cursor']);

        $this->assertSame(3, $reasons['symlink'], 'a link to a secret, a link out of the disk and a link inside it are all left alone');
        $this->assertSame(5, $reasons['forbidden_name'], '.env, .env.production, .htaccess, id_rsa.pem and server.key');
        $this->assertSame(3, $reasons['executable_extension'], 'shell.php, x.php.jpg and y.jpg.php');
        $this->assertArrayHasKey('trailing_dot_or_space', $reasons);
        $this->assertArrayHasKey('stream_or_colon', $reasons);
        $this->assertArrayHasKey('not_nfc', $reasons);
        $this->assertArrayHasKey('reserved_name', $reasons);

        $samplePaths = array_column($result['cursor']['skipped_sample'], 'path');
        $this->assertContains('public:media/link-env.jpg', $samplePaths);

        $link = collect($result['cursor']['skipped_sample'])->firstWhere('path', 'public:media/link-env.jpg');
        $this->assertSame($this->base.'/outside/secrets/real.env', $link['target'], 'the link target is shown to the operator');
    }

    #[Test]
    public function a_linked_folder_is_not_entered_and_nothing_behind_it_is_listed(): void
    {
        $this->hostileTree();

        $result = $this->walker()->all([FileRoot::parse('public:media')]);

        foreach ($this->paths($result['files']) as $path) {
            $this->assertStringNotContainsString('evil', $path);
            $this->assertStringNotContainsString('real.env', $path);
            $this->assertStringNotContainsString('secrets', $path);
        }
    }

    #[Test]
    public function a_root_that_is_a_forbidden_folder_is_refused_whole(): void
    {
        $this->hostileTree();

        $result = $this->walker()->all([FileRoot::parse('local:invoices')]);

        $this->assertSame([], $result['files']);
        $this->assertSame(['forbidden_directory' => 1], $this->reasons($result['cursor']));
    }

    #[Test]
    public function a_root_that_is_itself_a_link_is_refused(): void
    {
        symlink($this->base.'/outside/secrets', $this->base.'/public/branding');

        $result = $this->walker()->all([FileRoot::parse('public:branding')]);

        $this->assertSame([], $result['files']);
        $this->assertSame(['symlink' => 1], $this->reasons($result['cursor']));
    }

    #[Test]
    public function a_root_that_does_not_exist_yet_is_not_a_problem(): void
    {
        $result = $this->walker()->all([FileRoot::parse('public:product-images')]);

        $this->assertSame([], $result['files']);
        $this->assertTrue($result['cursor']['done']);
    }

    #[Test]
    public function an_unknown_disk_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new AllowlistWalker(new PackagePathPolicy, []))->start([FileRoot::parse('public:media')]);
    }

    #[Test]
    public function a_walk_in_tiny_steps_through_a_json_cursor_finds_exactly_the_same_files(): void
    {
        $this->hostileTree();
        $roots = [FileRoot::parse('public:media'), FileRoot::parse('local:payment-proofs')];
        $whole = $this->paths($this->walker()->all($roots)['files']);

        $files = [];
        $cursor = $this->walker()->start($roots);
        $steps = 0;

        while (! $cursor['done']) {
            $cursor = $this->walker()->step($cursor, 1, function (FileEntry $f) use (&$files): void {
                $files[] = $f;
            });
            $cursor = json_decode((string) json_encode($cursor), true);
            $this->assertLessThan(500, ++$steps);
        }

        $this->assertSame($whole, $this->paths($files));
        $this->assertGreaterThan(15, $steps, 'one entry per step really takes many steps');
    }

    #[Test]
    public function a_file_added_after_the_cursor_is_found_and_one_added_before_it_is_not(): void
    {
        $this->put('public/media/b.jpg');
        $this->put('public/media/d.jpg');
        $roots = [FileRoot::parse('public:media')];

        $files = [];
        $emit = function (FileEntry $f) use (&$files): void {
            $files[] = $f->path;
        };

        $cursor = $this->walker()->start($roots);
        $cursor = $this->walker()->step($cursor, 1, $emit); // opens the root, handles b.jpg only
        $this->assertSame(['media/b.jpg'], $files);

        $this->put('public/media/a.jpg'); // sorts before the cursor
        $this->put('public/media/c.jpg'); // sorts after it

        while (! $cursor['done']) {
            $cursor = $this->walker()->step($cursor, 10, $emit);
        }

        $this->assertSame(['media/b.jpg', 'media/c.jpg', 'media/d.jpg'], $files);
    }

    #[Test]
    public function the_skipped_sample_is_capped_but_every_skip_is_counted(): void
    {
        for ($i = 0; $i < 250; $i++) {
            $this->put("public/media/f{$i}.php");
        }
        $this->put('public/media/ok.jpg');

        $result = $this->walker()->all([FileRoot::parse('public:media')]);

        $this->assertSame(250, $result['cursor']['skipped_counts']['executable_extension']);
        $this->assertCount(AllowlistWalker::SKIPPED_SAMPLE, $result['cursor']['skipped_sample']);
        $this->assertSame(['public:media/ok.jpg'], $this->paths($result['files']));
    }

    #[Test]
    public function a_named_pipe_is_not_a_file(): void
    {
        if (! function_exists('posix_mkfifo')) {
            $this->markTestSkipped('posix_mkfifo is not available.');
        }

        posix_mkfifo($this->base.'/public/media/pipe.jpg', 0644);
        $this->put('public/media/ok.jpg');

        $result = $this->walker()->all([FileRoot::parse('public:media')]);

        $this->assertSame(['public:media/ok.jpg'], $this->paths($result['files']));
        $this->assertSame(['not_regular_file' => 1], $this->reasons($result['cursor']));
    }
}

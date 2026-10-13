<?php

namespace Tests\Unit\Migration\Package;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Compressed data from a package must only ever be inflated by BoundedInflater: gzdecode() keeps
 * just the first gzip member of a multi-member stream and has no output cap, and every other
 * one-shot decompressor has no cap either. This scans the migration code for them, so a later
 * change cannot quietly bring one back.
 */
class NoRawGzdecodeTest extends TestCase
{
    private const FORBIDDEN = [
        'gzdecode', 'gzinflate', 'gzuncompress', 'zlib_decode', 'gzopen', 'gzfile', 'readgzfile', 'gzread',
        'compress.zlib://', 'inflate_init', 'inflate_add', 'bzdecompress', 'ZipArchive',
    ];

    /** @return array<int, string> */
    private function migrationSources(): array
    {
        $root = dirname(__DIR__, 4);
        $files = [];

        foreach (['app/Services/Migration', 'app/Security'] as $directory) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$directory, RecursiveDirectoryIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $files[] = $file->getPathname();
                }
            }
        }

        return $files;
    }

    #[Test]
    public function only_bounded_inflater_may_inflate(): void
    {
        $offenders = [];

        foreach ($this->migrationSources() as $path) {
            if (basename($path) === 'BoundedInflater.php') {
                continue;
            }

            $code = $this->withoutComments((string) file_get_contents($path));

            foreach (self::FORBIDDEN as $needle) {
                if (stripos($code, $needle) !== false) {
                    $offenders[] = basename($path).' uses '.$needle;
                }
            }
        }

        $this->assertSame([], $offenders, 'inflate through App\Services\Migration\Package\BoundedInflater only');
    }

    #[Test]
    public function the_scan_really_looks_at_the_migration_sources(): void
    {
        $names = array_map('basename', $this->migrationSources());

        $this->assertContains('BoundedInflater.php', $names);
        $this->assertContains('PackageReader.php', $names);
        $this->assertContains('PackagePathPolicy.php', $names);
    }

    #[Test]
    public function the_detector_itself_would_catch_a_gzdecode_call(): void
    {
        $sample = '<?php $x = gzdecode($data);';

        $this->assertNotFalse(stripos($this->withoutComments($sample), 'gzdecode'));
        $this->assertFalse(stripos($this->withoutComments('<?php // gzdecode is banned'), 'gzdecode'));
    }

    private function withoutComments(string $code): string
    {
        $out = '';

        foreach (token_get_all($code) as $token) {
            if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            $out .= is_array($token) ? $token[1] : $token;
        }

        return $out;
    }
}

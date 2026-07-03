<?php

namespace App\Console\Commands;

use App\Services\Updates\ReleaseBuilder;
use Illuminate\Console\Command;

/**
 * oeparts:build (Module 21, Chunk 5.1) — the PHP-side of the release build. Runs
 * against an EXPORT directory (a clean copy produced by build/build.sh), never the
 * live repo: it strips dev/secret/internal files, bundles third-party licenses, and
 * writes the per-file sha256 manifest. The shell script then zips + checksums.
 *
 * Safety: this DELETES files under --path, so it refuses to run without an explicit
 * --path and refuses to operate on the live project root.
 */
class BuildRelease extends Command
{
    protected $signature = 'oeparts:build
        {--path= : The export directory to package (required; never the live repo)}
        {--no-strip : Skip removing dev files (manifest + licenses only)}
        {--json : Emit a machine-readable summary}';

    protected $description = 'Prepare a release export: strip dev files, bundle third-party licenses, write the per-file sha256 manifest.';

    public function handle(ReleaseBuilder $builder): int
    {
        $path = (string) ($this->option('path') ?: '');

        if ($path === '') {
            $this->error('Provide --path to an export directory (never the live repo). See build/build.sh.');

            return self::FAILURE;
        }

        if (! is_dir($path)) {
            $this->error('Export directory does not exist: '.$path);

            return self::FAILURE;
        }

        // Never strip the working tree the app is running from.
        if (@realpath($path) === @realpath(base_path())) {
            $this->error('Refusing to build against the live project root. Export to a separate directory first.');

            return self::FAILURE;
        }

        $removed = $this->option('no-strip') ? [] : $builder->stripDevFiles($path);
        $licenses = $builder->bundleLicenses($path);
        $manifest = $builder->buildFileManifest($path);

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'success'     => true,
                'path'        => $path,
                'version'     => $manifest['version'],
                'stripped'    => $removed,
                'licenses'    => $licenses,
                'file_count'  => $manifest['file_count'],
                'manifest'    => $builder->manifestFile(),
            ], JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info('Release export prepared: '.$path);
        $this->line('  Version:         '.($manifest['version'] ?? 'unknown'));
        $this->line('  Stripped paths:  '.count($removed).($removed ? ' ('.implode(', ', $removed).')' : ''));
        $this->line('  Licenses bundled: '.$licenses.' → '.$builder->licensesFile());
        $this->line('  Files in manifest: '.$manifest['file_count'].' → '.$builder->manifestFile());

        return self::SUCCESS;
    }
}

<?php

namespace App\Services\Updates;

/**
 * ReleaseBuilder (Module 21, Chunk 5.1) — the testable core of the release build
 * pipeline. It operates on an EXPORT DIRECTORY (a clean copy of the tree, never the
 * live repo): it strips dev/secret/internal files, bundles third-party licenses for
 * open-source compliance, and writes the per-file sha256 manifest that enables
 * modified-core detection (rule #44) and future delta updates (decision #14).
 *
 * The shell orchestration (git export → composer --no-dev → npm build → zip) lives in
 * build/build.sh; the filesystem-shaping steps that benefit from tests live here and
 * are driven by the `oeparts:build` command. Nothing here boots or mutates the running
 * app — it only shapes files under the given directory.
 */
class ReleaseBuilder
{
    /** @var array<int,string> */
    private array $exclude;

    private string $manifestFile;

    private string $licensesFile;

    /** @param array<string,mixed>|null $config defaults to config('updates.build') */
    public function __construct(?array $config = null)
    {
        $config ??= (array) config('updates.build', []);
        $this->exclude      = array_values((array) ($config['exclude'] ?? []));
        $this->manifestFile = (string) ($config['manifest_file'] ?? 'file-manifest.json');
        $this->licensesFile = (string) ($config['licenses_file'] ?? 'THIRD-PARTY-LICENSES.md');
    }

    public function manifestFile(): string
    {
        return $this->manifestFile;
    }

    public function licensesFile(): string
    {
        return $this->licensesFile;
    }

    /**
     * Remove every excluded path from the export dir.
     *
     * @return array<int,string> the relative paths actually removed
     */
    public function stripDevFiles(string $dir): array
    {
        $dir = rtrim($dir, "/\\");
        $removed = [];

        foreach ($this->exclude as $rel) {
            $abs = $dir.'/'.$rel;
            if (is_dir($abs)) {
                $this->rrmdir($abs);
                $removed[] = $rel;
            } elseif (is_file($abs)) {
                @unlink($abs);
                $removed[] = $rel;
            }
        }

        return $removed;
    }

    /**
     * Build the per-file sha256 manifest for the export dir and write it to
     * manifest_file. Excluded paths and the manifest/licenses artefacts themselves are
     * never listed, so the manifest reflects exactly what ships.
     *
     * @return array{schema:int,version:?string,file_count:int,files:array<string,array{sha256:string,bytes:int}>}
     */
    public function buildFileManifest(string $dir): array
    {
        $dir = rtrim($dir, "/\\");
        $files = [];

        foreach ($this->walk($dir) as $abs) {
            $rel = $this->rel($dir, $abs);
            if ($rel === $this->manifestFile || $rel === $this->licensesFile || $this->isExcluded($rel)) {
                continue;
            }
            $files[$rel] = [
                'sha256' => hash_file('sha256', $abs),
                'bytes'  => (int) filesize($abs),
            ];
        }

        ksort($files);

        $manifest = [
            'schema'     => 1,
            'version'    => $this->versionOf($dir),
            'file_count' => count($files),
            'files'      => $files,
        ];

        file_put_contents(
            $dir.'/'.$this->manifestFile,
            json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        return $manifest;
    }

    /**
     * Compare an installed tree against a manifest (modified-core detection, rule #44).
     *
     * @return array{changed:array<int,string>,missing:array<int,string>}
     */
    public function verifyAgainstManifest(string $dir, ?array $manifest = null): array
    {
        $dir = rtrim($dir, "/\\");
        $manifest ??= json_decode((string) @file_get_contents($dir.'/'.$this->manifestFile), true);

        $expected = (array) (($manifest['files'] ?? []) ?: []);
        $changed = [];
        $missing = [];

        foreach ($expected as $rel => $info) {
            $abs = $dir.'/'.$rel;
            if (! is_file($abs)) {
                $missing[] = $rel;

                continue;
            }
            if (hash_file('sha256', $abs) !== ($info['sha256'] ?? null)) {
                $changed[] = $rel;
            }
        }

        return ['changed' => $changed, 'missing' => $missing];
    }

    /**
     * Bundle every vendor package's license file into one artefact (open-source
     * compliance). Returns the number of packages whose license was collected.
     */
    public function bundleLicenses(string $dir): int
    {
        $dir = rtrim($dir, "/\\");
        $vendor = $dir.'/vendor';
        $sections = [];

        foreach ($this->findLicenses($vendor) as $file) {
            $pkg = str_replace('\\', '/', trim(str_replace($vendor, '', dirname($file)), "/\\"));
            $sections[$pkg] = (string) @file_get_contents($file);
        }

        ksort($sections);

        $out = "# Third-Party Licenses\n\n"
            ."This distribution bundles the following open-source packages, each under its own license.\n";
        foreach ($sections as $pkg => $text) {
            $out .= "\n\n---\n\n## ".$pkg."\n\n```\n".rtrim($text)."\n```\n";
        }

        file_put_contents($dir.'/'.$this->licensesFile, $out);

        return count($sections);
    }

    /* ---- Helpers ------------------------------------------------------- */

    /** @return \Generator<int,string> absolute file paths under $dir */
    private function walk(string $dir): \Generator
    {
        if (! is_dir($dir)) {
            return;
        }

        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($it as $file) {
            if ($file->isFile()) {
                yield $file->getPathname();
            }
        }
    }

    private function rel(string $dir, string $abs): string
    {
        return ltrim(str_replace('\\', '/', substr($abs, strlen($dir))), '/');
    }

    /** Excluded if the path equals an entry or lives under one (directory-prefix). */
    private function isExcluded(string $rel): bool
    {
        $rel = str_replace('\\', '/', $rel);

        foreach ($this->exclude as $e) {
            $e = str_replace('\\', '/', $e);
            if ($rel === $e || str_starts_with($rel, $e.'/')) {
                return true;
            }
        }

        return false;
    }

    private function versionOf(string $dir): ?string
    {
        $file = $dir.'/version.json';
        if (! is_file($file)) {
            return null;
        }
        $data = json_decode((string) @file_get_contents($file), true);

        return is_array($data) ? ($data['version'] ?? null) : null;
    }

    /** @return array<int,string> one license file per vendor package (vendor/<v>/<p>). */
    private function findLicenses(string $vendor): array
    {
        if (! is_dir($vendor)) {
            return [];
        }

        $out = [];
        foreach (glob($vendor.'/*', GLOB_ONLYDIR) ?: [] as $vendorDir) {
            foreach (glob($vendorDir.'/*', GLOB_ONLYDIR) ?: [] as $pkgDir) {
                foreach (scandir($pkgDir) ?: [] as $entry) {
                    if (preg_match('/^(LICEN[SC]E|COPYING)/i', $entry) && is_file($pkgDir.'/'.$entry)) {
                        $out[] = $pkgDir.'/'.$entry;
                        break; // one license per package
                    }
                }
            }
        }

        return $out;
    }

    private function rrmdir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir.'/'.$entry;
            is_dir($path) && ! is_link($path) ? $this->rrmdir($path) : @unlink($path);
        }
        @rmdir($dir);
    }
}

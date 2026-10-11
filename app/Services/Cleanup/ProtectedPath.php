<?php

namespace App\Services\Cleanup;

/**
 * The filesystem safety gate every file-deleting cleanup action goes through.
 *
 * The engine never accepts a path from a request. File targets enumerate their OWN registered
 * root and ask this class "may I delete this one?" for each candidate. It answers with a reason
 * code when the answer is no, and null when it is safe:
 *
 *   invalid_path        NUL byte or otherwise malformed
 *   root_missing        the registered root does not exist
 *   root_outside_project  the registered root is not strictly inside the project directory
 *   symlink             a link is never followed and never deleted through
 *   missing             nothing there (already gone)
 *   not_inside_root     the real parent directory is not the registered root (traversal, sibling prefix)
 *   denied              on the never-touch list (.env, vendor, release keys, recovery state, ...)
 *   not_regular_file    a directory/device where a file was expected
 *   too_new             modified within the minimum age: something may still be writing it
 *
 * Containment is compared on realpath() with a trailing separator, so `/logs-evil` can never pass
 * for `/logs`; the final path component is deliberately NOT resolved, so `unlink()` acts on the
 * entry itself and can never follow a symlink out of the root.
 */
final class ProtectedPath
{
    /** No cleanup deletes anything modified more recently than this, whatever the target asks. */
    public const MIN_AGE_SECONDS = 3600;

    /**
     * Never touched, never deleted as part of a directory sweep. Relative to the project root.
     * A candidate is refused when it IS one of these, lies inside one, or CONTAINS one.
     */
    private const DENIED = [
        '.env', '.git', 'vendor', 'node_modules/.bin',
        'app', 'config', 'bootstrap', 'database', 'routes', 'resources', 'deploy',
        'artisan', 'composer.json', 'composer.lock',
        'public/build', 'public/index.php', 'public/.htaccess', 'public/oe-recovery.php', 'public/storage',
        'storage/installed.lock', 'storage/release-signing-private.key', 'storage/app/install',
    ];

    /** Files inside the update/recovery state dir that are the recovery net itself. */
    private const DENIED_STATE_FILES = [
        'lock', 'arm.flag', 'last-swap.json', 'recovery.log',
        'recovery-session.json', 'recovery-throttle.json',
    ];

    public function __construct(private readonly ?string $baseOverride = null) {}

    /** True for a name that is a plain, visible, single path component. */
    public function isSafeBasename(string $name): bool
    {
        return $name !== ''
            && strlen($name) <= 255
            && preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/', $name) === 1
            && ! str_contains($name, '..');
    }

    /**
     * Absolute path of a file/dir DIRECTLY inside the registered root, from a bare name — or null
     * when the name is not a safe basename or the root is unusable.
     */
    public function resolve(string $root, string $basename): ?string
    {
        if (! $this->isSafeBasename($basename)) {
            return null;
        }

        $rootReal = realpath($root);

        return $rootReal === false ? null : $rootReal.DIRECTORY_SEPARATOR.$basename;
    }

    /**
     * @param  bool  $recursive  accept entries anywhere below the root, not only directly inside it
     * @param  bool  $allowDirs  accept a directory (swept recursively by the caller)
     * @return string|null reason code, or null when deleting is safe
     */
    public function evaluate(
        string $root,
        string $absPath,
        int $minAgeSeconds = self::MIN_AGE_SECONDS,
        bool $recursive = false,
        bool $allowDirs = false,
    ): ?string {
        if (str_contains($absPath, "\0") || str_contains($root, "\0")) {
            return 'invalid_path';
        }

        $rootReal = realpath($root);
        if ($rootReal === false) {
            return 'root_missing';
        }

        $baseReal = realpath($this->base());
        if ($baseReal === false || ! $this->isStrictlyInside($rootReal, $baseReal)) {
            return 'root_outside_project';
        }

        if (is_link($absPath)) {
            return 'symlink';
        }

        if (! file_exists($absPath)) {
            return 'missing';
        }

        $parentReal = realpath(dirname($absPath));
        if ($parentReal === false) {
            return 'missing';
        }

        $inside = $this->norm($parentReal) === $this->norm($rootReal)
            || ($recursive && $this->isStrictlyInside($parentReal, $rootReal));
        if (! $inside) {
            return 'not_inside_root';
        }

        $real = $parentReal.DIRECTORY_SEPARATOR.basename($absPath);
        if ($this->denies($real)) {
            return 'denied';
        }

        if (is_dir($absPath)) {
            if (! $allowDirs) {
                return 'not_regular_file';
            }
        } elseif (! is_file($absPath)) {
            return 'not_regular_file';
        }

        $age = time() - $this->lastActivity($absPath);
        if ($age < max($minAgeSeconds, self::MIN_AGE_SECONDS)) {
            return 'too_new';
        }

        return null;
    }

    /**
     * True when $absPath is on the never-touch list, lies inside an entry on it, or is an ancestor
     * of one (deleting `storage` would delete the signing key).
     */
    public function denies(string $absPath): bool
    {
        $path = $this->norm($absPath);

        foreach ($this->deniedAbsolute() as $denied) {
            $d = $this->norm($denied);
            if ($path === $d || str_starts_with($path, $d.'/') || str_starts_with($d, $path.'/')) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    private function deniedAbsolute(): array
    {
        $base = rtrim($this->base(), '/\\');
        $out = [];

        // Note: the project root itself is not listed. It is protected as an ANCESTOR of
        // every entry below (denies() refuses any path that contains a denied one), and
        // listing it would wrongly make everything inside the project "inside a denied path".
        foreach (self::DENIED as $relative) {
            $out[] = $base.'/'.$relative;
        }

        $state = rtrim((string) config('updates.state_path', $base.'/storage/app/updates'), '/\\');
        foreach (self::DENIED_STATE_FILES as $file) {
            $out[] = $state.'/'.$file;
        }

        return $out;
    }

    /** Newest modification time of the entry (for a directory: of the directory and its direct children). */
    private function lastActivity(string $path): int
    {
        $newest = (int) @filemtime($path);

        if (is_dir($path)) {
            foreach (@scandir($path) ?: [] as $child) {
                if ($child !== '.' && $child !== '..') {
                    $newest = max($newest, (int) @filemtime($path.DIRECTORY_SEPARATOR.$child));
                }
            }
        }

        return $newest;
    }

    private function isStrictlyInside(string $path, string $parent): bool
    {
        $p = $this->norm($path);
        $d = $this->norm($parent);

        return $p !== $d && str_starts_with($p, $d.'/');
    }

    /** Forward slashes, no trailing slash, and case-folded on Windows where paths are case-insensitive. */
    private function norm(string $path): string
    {
        $path = rtrim(str_replace('\\', '/', $path), '/');

        return DIRECTORY_SEPARATOR === '\\' ? strtolower($path) : $path;
    }

    private function base(): string
    {
        return $this->baseOverride ?? base_path();
    }
}

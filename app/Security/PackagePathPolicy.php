<?php

namespace App\Security;

/**
 * The one decision "may a file with this path be written from a package into this site?".
 *
 * A package is untrusted input: an attacker who can hand an operator a .oeparts file controls
 * every path inside it. The disk the files land on (storage/app/public) is served by the web
 * server through public/storage, so a single written .php there is remote code execution. This
 * policy is the allow/deny gate every package path must pass, at export (what is walked), at
 * verification (before anything changes) and at apply (before each write).
 *
 * It is strict on purpose. The exporter only ever emits canonical, root-relative, forward-slash
 * paths, so anything that is not exactly that is refused rather than "fixed": a path that needs
 * normalising is a path that was not produced by our exporter.
 *
 * The earlier RestoreManager::sanitizeManifestPath() normalised "../" and stripped leading
 * slashes. That kept a path inside the target root but still let any file in, including .php.
 * Here a traversal or an absolute path is refused outright, and so is anything executable.
 */
final class PackagePathPolicy
{
    public const MAX_PATH_BYTES = 4096;

    public const MAX_SEGMENT_BYTES = 255;

    /** Bytes of a file that are searched for a PHP open tag. */
    public const SNIFF_BYTES = 4096;

    /** Extensions that a web server or an interpreter would execute, on ANY dot-separated part of ANY segment. */
    private const EXECUTABLE_EXTENSIONS = [
        'php', 'phtml', 'pht', 'phps', 'phar', 'shtml', 'shtm', 'cgi', 'pl', 'py', 'sh', 'bash',
        'asp', 'aspx', 'jsp', 'jspx', 'cfm', 'exe', 'dll', 'bat', 'cmd', 'com', 'scr', 'vbs', 'ps1',
    ];

    /** Whole file names (lower case) that change how a server treats a directory or hold secrets. */
    private const FORBIDDEN_NAMES = [
        '.htaccess', '.htpasswd', '.user.ini', 'web.config', 'installed.lock', 'composer.json', 'composer.lock',
    ];

    /** File name extensions that are keys or certificates. */
    private const FORBIDDEN_EXTENSIONS = ['key', 'pem', 'p12', 'pfx', 'jks', 'crt'];

    /** Windows device names, reserved with or without an extension. */
    private const RESERVED_NAMES = [
        'con', 'prn', 'aux', 'nul',
        'com1', 'com2', 'com3', 'com4', 'com5', 'com6', 'com7', 'com8', 'com9',
        'lpt1', 'lpt2', 'lpt3', 'lpt4', 'lpt5', 'lpt6', 'lpt7', 'lpt8', 'lpt9',
    ];

    /**
     * Root-relative directories that are never written from a package: framework caches, logs,
     * code, other tools' working state and the per-instance state of this very feature.
     */
    private const FORBIDDEN_DIRECTORIES = [
        'bootstrap/cache', 'vendor', 'node_modules', '.git',
        'storage/framework', 'storage/logs',
        'storage/app/backups', 'storage/app/private/backups', 'storage/app/restore', 'storage/app/updates',
        'storage/app/install', 'storage/app/import', 'storage/app/migration', 'storage/app/instance',
        'storage/app/exports', 'storage/app/private/exports', 'storage/app/private/imports',
        'storage/app/private/livewire-tmp', 'storage/app/private/invoices',
        'storage/app/purifier', 'storage/app/_hooks',
    ];

    /**
     * @param  array<int, string>|null  $allowedRoots  root-relative directories a path must be under
     *                                                 (the file roots of the groups being imported); null = no positive allowlist
     */
    public function decide(string $path, ?array $allowedRoots = null): PathDecision
    {
        $reason = $this->reject($path, $allowedRoots);

        return $reason === null
            ? PathDecision::allow($path)
            : PathDecision::reject($reason);
    }

    /**
     * @param  array<int, string>|null  $allowedRoots
     */
    public function reject(string $path, ?array $allowedRoots = null): ?PathRejection
    {
        if ($path === '') {
            return PathRejection::Empty;
        }

        if (strlen($path) > self::MAX_PATH_BYTES) {
            return PathRejection::TooLong;
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $path)) {
            return PathRejection::ControlCharacter;
        }

        if (str_contains($path, '\\')) {
            return PathRejection::Backslash;
        }

        if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path)) {
            return PathRejection::Absolute;
        }

        $segments = explode('/', $path);

        foreach ($segments as $segment) {
            if ($segment === '..') {
                return PathRejection::Traversal;
            }
            if ($segment === '' || $segment === '.') {
                return PathRejection::NotCanonical; // './a', 'a//b', 'a/'
            }
            if (strlen($segment) > self::MAX_SEGMENT_BYTES) {
                return PathRejection::TooLong;
            }
        }

        // One composed form per name, or two different byte strings could name the same file.
        if (preg_match('/[^\x00-\x7F]/', $path)
            && class_exists(\Normalizer::class)
            && ! \Normalizer::isNormalized($path, \Normalizer::FORM_C)) {
            return PathRejection::NotNfc;
        }

        $lower = array_map(mb_strtolower(...), $segments);

        foreach ($lower as $segment) {
            // Windows ignores a trailing dot or space ("x.php " IS "x.php") and "x.php::$DATA" is the
            // same file's data stream; a colon anywhere is never part of a name we export.
            if (str_ends_with($segment, '.') || str_ends_with($segment, ' ')) {
                return PathRejection::TrailingDotOrSpace;
            }
            if (str_contains($segment, ':')) {
                return PathRejection::StreamOrColon;
            }
            if (in_array(explode('.', $segment)[0], self::RESERVED_NAMES, true)) {
                return PathRejection::ReservedName;
            }
        }

        foreach ($lower as $segment) {
            $parts = explode('.', $segment);

            // Every dot-separated part after the first, so "x.php.jpg" and "x.jpg.php" are both caught.
            foreach (array_slice($parts, 1) as $part) {
                if (in_array($part, self::EXECUTABLE_EXTENSIONS, true) || preg_match('/^php\d+$/', $part)) {
                    return PathRejection::ExecutableExtension;
                }
            }
            // A directory called "php" or "x.php" is just as dangerous as a file of that name.
            if (in_array($parts[0], ['php', 'phtml', 'phar'], true) && count($parts) === 1) {
                return PathRejection::ExecutableExtension;
            }
        }

        foreach ($lower as $segment) {
            if (in_array($segment, self::FORBIDDEN_NAMES, true)
                || str_starts_with($segment, '.env')
                || str_starts_with($segment, '.git')
                || in_array(pathinfo($segment, PATHINFO_EXTENSION), self::FORBIDDEN_EXTENSIONS, true)) {
                return PathRejection::ForbiddenName;
            }
        }

        $normalised = implode('/', $lower);
        foreach (self::FORBIDDEN_DIRECTORIES as $directory) {
            if ($normalised === $directory || str_starts_with($normalised, $directory.'/')) {
                return PathRejection::ForbiddenDirectory;
            }
        }

        if ($allowedRoots !== null && ! $this->isUnderAny($normalised, $allowedRoots)) {
            return PathRejection::OutsideAllowedRoots;
        }

        return null;
    }

    /**
     * Does the start of a file look like code? A file with a harmless name can still be a script
     * (a PHP file renamed to .jpg is the classic upload attack, and an image can carry a PHP tag
     * in its metadata), so the first bytes are searched for a PHP open tag. "<?xml" is not one.
     */
    public function looksLikeCode(string $firstBytes): bool
    {
        $head = substr($firstBytes, 0, self::SNIFF_BYTES);

        return (bool) preg_match('/<\?php|<\?=|<script\s+language\s*=\s*["\']?php/i', $head)
            || str_starts_with(ltrim($head, "\xEF\xBB\xBF \t\r\n"), '#!');
    }

    /** @param  array<int, string>  $roots */
    private function isUnderAny(string $normalisedPath, array $roots): bool
    {
        foreach ($roots as $root) {
            $root = mb_strtolower(trim($root, '/'));

            if ($root !== '' && ($normalisedPath === $root || str_starts_with($normalisedPath, $root.'/'))) {
                return true;
            }
        }

        return false;
    }
}

<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Bootstrap a fresh .env (with a random APP_KEY) on a truly virgin deployment —
// one that has never had .env created, e.g. an extracted release zip on shared
// hosting with no SSH/CLI access. Without this, EVERY request (including the
// web installer's own first screen at /install) throws MissingAppKeyException:
// Laravel's default 'web' middleware group includes EncryptCookies, which
// resolves the Encrypter service in its constructor on every request — before
// any installer route or middleware runs — and that resolution throws the
// instant config('app.key') is empty. Pure filesystem, no framework loaded yet,
// same "must work when nothing else does" spirit as the framework-free Recovery
// Console (public/oe-recovery.php). No-op once .env exists.
$envPath = __DIR__.'/../.env';
if (! file_exists($envPath)) {
    $examplePath = __DIR__.'/../.env.example';
    if (file_exists($examplePath)) {
        $env = file_get_contents($examplePath);
        $key = 'base64:'.base64_encode(random_bytes(32));
        $env = preg_match('/^APP_KEY=.*$/m', $env)
            ? preg_replace('/^APP_KEY=.*$/m', 'APP_KEY='.$key, $env)
            : rtrim($env)."\nAPP_KEY=".$key."\n";

        // Atomic write: a concurrent request reading .env mid-write must never
        // see a partial/empty file (same pattern as InstallerController's own
        // updateEnvFile()). A race between two first-ever requests is harmless —
        // both keys are independently random; the file just settles on whichever
        // wrote last.
        $tmpPath = $envPath.'.boot-'.getmypid().'.tmp';
        file_put_contents($tmpPath, $env, LOCK_EX);
        @rename($tmpPath, $envPath);
    }
}

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
$autoloadPath = __DIR__.'/../vendor/autoload.php';
if (! file_exists($autoloadPath)) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "OeParts cannot start: vendor/autoload.php is missing.\n\n"
        ."This usually means the files were downloaded via GitHub's \"Code > Download ZIP\"\n"
        ."button, which ships source only (no dependencies). Download the packaged release\n"
        ."instead — the oeparts-<version>.zip asset attached to a GitHub Release — which\n"
        ."bundles vendor/ and is ready to extract and run without SSH or Composer:\n"
        ."https://github.com/shemondotme/oeparts/releases\n\n"
        ."If you did use that asset, re-extract it — the vendor/ directory may have been\n"
        ."dropped or excluded during upload.\n";
    exit(1);
}
require $autoloadPath;

// Bootstrap Laravel and handle the request...
(require_once __DIR__.'/../bootstrap/app.php')
    ->handleRequest(Request::capture());

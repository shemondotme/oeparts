<?php

// Router for `php -S`, used by the laravel.test/hostingsim services in
// compose.yaml in place of `php artisan serve` (which needs proc_open — see
// the SUPERVISOR_PHP_COMMAND comments in compose.yaml).
//
// This is a copy of Laravel's own
// vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php,
// with one fix: that script derives the public path from getcwd(), which
// assumes the PHP process is started with its cwd already inside public/
// (true for `artisan serve`, which chdir()s there). Supervisor here starts
// PHP with cwd = /var/www/html (the container WORKDIR), so getcwd() resolved
// to the wrong directory and every request — including existing static
// files under public/build/* — fell through to `require getcwd().'/index.php'`,
// which doesn't exist there, throwing a fatal error. Hardcoding the real
// public path fixes both the static-file passthrough and the require.
$publicPath = '/var/www/html/public';

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

// Emulates Apache's "mod_rewrite" functionality from the built-in PHP web
// server: serve existing static files (e.g. public/build/assets/*) as-is
// instead of bootstrapping the framework for them.
if ($uri !== '/' && file_exists($publicPath.$uri)) {
    return false;
}

require_once $publicPath.'/index.php';

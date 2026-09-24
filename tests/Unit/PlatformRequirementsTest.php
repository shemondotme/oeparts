<?php

namespace Tests\Unit;

use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * The PHP version this product promises must be the PHP version its lock file
 * can actually run on.
 *
 * Found in Phase 22: composer.json (and the README badge, the installer's
 * check, the update preflight's `min_php`, and the CI matrix) all promised
 * PHP 8.3+, but the dependency updates done in Phase 1 had been resolved on a
 * PHP 8.4 machine, so composer.lock silently pinned three Symfony packages
 * that require PHP >= 8.4.1 — and Composer's generated platform_check.php
 * enforced 8.4.1. A self-hoster on 8.3 would have passed the update preflight
 * and then hit a fatal error on the first request after updating. Only a
 * compose profile that happened to run PHP 8.3 (nginx + PHP-FPM) exposed it.
 *
 * `config.platform.php` in composer.json makes Composer resolve for the
 * declared minimum no matter which PHP the developer runs `composer update`
 * on; this test is what keeps that (and the lock) honest.
 */
class PlatformRequirementsTest extends TestCase
{
    private function json(string $file): array
    {
        return json_decode(file_get_contents(dirname(__DIR__, 2).'/'.$file), true, flags: JSON_THROW_ON_ERROR);
    }

    /** "8.3.0" — the lowest PHP composer.json's own `require.php` allows. */
    private function declaredMinimum(): string
    {
        $constraint = $this->json('composer.json')['require']['php'];
        $lower = (new VersionParser)->parseConstraints($constraint)->getLowerBound()->getVersion();

        return implode('.', array_slice(explode('.', $lower), 0, 3));
    }

    #[Test]
    public function the_declared_minimum_is_what_the_project_documents(): void
    {
        $this->assertSame('8.3.0', $this->declaredMinimum(), 'if the minimum is raised on purpose, update README, the installer check, PreflightService/version.json min_php, the CI matrix and the docker profiles together — then this constant');
    }

    #[Test]
    public function composer_resolves_for_the_declared_minimum_not_the_developers_own_php(): void
    {
        $platform = $this->json('composer.json')['config']['platform']['php'] ?? null;

        $this->assertSame(
            $this->declaredMinimum(),
            $platform,
            'without config.platform.php, `composer update` on a newer PHP quietly locks packages that need that newer PHP'
        );
    }

    #[Test]
    public function no_locked_package_needs_more_php_than_the_declared_minimum(): void
    {
        $lock = $this->json('composer.lock');
        $minimum = $this->declaredMinimum();

        $offenders = [];
        foreach (array_merge($lock['packages'], $lock['packages-dev']) as $package) {
            $php = $package['require']['php'] ?? null;

            if ($php !== null && ! Semver::satisfies($minimum, $php)) {
                $offenders[] = "{$package['name']} {$package['version']} requires PHP {$php}";
            }
        }

        $this->assertSame(
            [],
            $offenders,
            "composer.lock contains packages that cannot run on PHP {$minimum}, which this project promises to support (dev packages included: CI runs the suite on the minimum).\n"
            .'Fix: keep config.platform.php in composer.json and re-run `composer update <package> --with-dependencies`.'
        );
    }

    #[Test]
    public function the_release_metadata_agrees_with_the_declared_minimum(): void
    {
        $minPhp = $this->json('version.json')['min_php'];

        $this->assertStringStartsWith(
            $minPhp.'.',
            $this->declaredMinimum(),
            'version.json min_php feeds the self-update preflight; it must match composer.json or an update can pass preflight and then fatal'
        );
    }
}

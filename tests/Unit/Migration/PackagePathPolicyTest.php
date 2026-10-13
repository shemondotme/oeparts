<?php

namespace Tests\Unit\Migration;

use App\Security\PackagePathPolicy;
use App\Security\PathRejection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Every path inside a .oeparts package is attacker-controlled. The disk the files land on is
 * served by the web server, so one written .php is remote code execution. These datasets are the
 * threat table's T14 (traversal), T15 (names the file system treats specially) and T16 (code
 * execution), plus the positives that must stay allowed so the policy is not simply "refuse all".
 * (Plain PHPUnit: the policy is framework-free.)
 */
class PackagePathPolicyTest extends TestCase
{
    private function policy(): PackagePathPolicy
    {
        return new PackagePathPolicy;
    }

    /** @return array<string, array{0: string, 1: PathRejection}> */
    public static function refused(): array
    {
        return [
            // T14 - traversal and absolute paths
            'parent directory' => ['../etc/passwd', PathRejection::Traversal],
            'parent in the middle' => ['storage/app/public/../../../.env', PathRejection::Traversal],
            'parent that stays inside is still refused' => ['storage/app/public/a/../b.jpg', PathRejection::Traversal],
            'unix absolute' => ['/etc/passwd', PathRejection::Absolute],
            'windows drive' => ['C:/Windows/system32/x.dll', PathRejection::Absolute],
            'windows drive relative' => ['C:x.txt', PathRejection::Absolute],
            'backslash separator' => ['storage\\app\\public\\x.jpg', PathRejection::Backslash],
            'unc' => ['\\\\server\\share\\x', PathRejection::Backslash],
            'nul byte' => ["storage/app/public/a\0.jpg", PathRejection::ControlCharacter],
            'newline' => ["storage/app/public/a\nb.jpg", PathRejection::ControlCharacter],
            'tab' => ["storage/app/public/a\tb.jpg", PathRejection::ControlCharacter],
            'empty' => ['', PathRejection::Empty],
            'too long' => [str_repeat('a/', 2100).'x.jpg', PathRejection::TooLong],
            'segment too long' => ['storage/app/public/'.str_repeat('a', 300).'.jpg', PathRejection::TooLong],
            'dot segment' => ['storage/./app/public/x.jpg', PathRejection::NotCanonical],
            'double slash' => ['storage/app//public/x.jpg', PathRejection::NotCanonical],
            'trailing slash' => ['storage/app/public/', PathRejection::NotCanonical],

            // T15 - names the file system treats specially
            'trailing dot' => ['storage/app/public/x.jpg.', PathRejection::TrailingDotOrSpace],
            'trailing space' => ['storage/app/public/x.jpg ', PathRejection::TrailingDotOrSpace],
            'alternate data stream' => ['storage/app/public/x.jpg:evil', PathRejection::StreamOrColon],
            'device name' => ['storage/app/public/con', PathRejection::ReservedName],
            'device name with extension' => ['storage/app/public/NUL.txt', PathRejection::ReservedName],
            'com port' => ['storage/app/public/com1.png', PathRejection::ReservedName],
            'decomposed unicode' => ["storage/app/public/cafe\u{0301}.jpg", PathRejection::NotNfc],

            // T16 - code execution
            'php' => ['storage/app/public/media/shell.php', PathRejection::ExecutableExtension],
            'php upper case' => ['storage/app/public/media/SHELL.PHP', PathRejection::ExecutableExtension],
            'php mixed case' => ['storage/app/public/media/x.PhP', PathRejection::ExecutableExtension],
            'php double extension' => ['storage/app/public/media/x.php.jpg', PathRejection::ExecutableExtension],
            'jpg then php' => ['storage/app/public/media/x.jpg.php', PathRejection::ExecutableExtension],
            'phtml' => ['storage/app/public/a/b.phtml', PathRejection::ExecutableExtension],
            'php5' => ['storage/app/public/a/b.php5', PathRejection::ExecutableExtension],
            'php 8' => ['storage/app/public/a/b.php8', PathRejection::ExecutableExtension],
            'pht' => ['storage/app/public/a/b.pht', PathRejection::ExecutableExtension],
            'phps' => ['storage/app/public/a/b.phps', PathRejection::ExecutableExtension],
            'phar' => ['storage/app/public/a/b.phar', PathRejection::ExecutableExtension],
            'shtml' => ['storage/app/public/a/b.shtml', PathRejection::ExecutableExtension],
            'cgi' => ['storage/app/public/a/b.cgi', PathRejection::ExecutableExtension],
            'shell script' => ['storage/app/public/a/b.sh', PathRejection::ExecutableExtension],
            'python' => ['storage/app/public/a/b.PY', PathRejection::ExecutableExtension],
            'perl' => ['storage/app/public/a/b.pl', PathRejection::ExecutableExtension],
            'asp' => ['storage/app/public/a/b.aspx', PathRejection::ExecutableExtension],
            'exe' => ['storage/app/public/a/b.exe', PathRejection::ExecutableExtension],
            'a directory named x.php' => ['storage/app/public/x.php/photo.jpg', PathRejection::ExecutableExtension],
            'htaccess' => ['storage/app/public/media/.htaccess', PathRejection::ForbiddenName],
            'user ini' => ['storage/app/public/media/.user.ini', PathRejection::ForbiddenName],
            'web config' => ['storage/app/public/media/web.config', PathRejection::ForbiddenName],
            'htpasswd' => ['storage/app/public/media/.htpasswd', PathRejection::ForbiddenName],

            // Secrets and state
            'env' => ['.env', PathRejection::ForbiddenName],
            'env variant' => ['storage/app/public/.env.production', PathRejection::ForbiddenName],
            'env upper' => ['storage/app/public/.ENV', PathRejection::ForbiddenName],
            'git config' => ['storage/app/public/.git/config', PathRejection::ForbiddenName],
            'private key' => ['storage/app/public/id.key', PathRejection::ForbiddenName],
            'pem' => ['storage/app/public/cert.PEM', PathRejection::ForbiddenName],
            'p12' => ['storage/app/public/cert.p12', PathRejection::ForbiddenName],
            'install lock' => ['storage/installed.lock', PathRejection::ForbiddenName],
            'composer' => ['composer.json', PathRejection::ForbiddenName],

            // Directories that are never written from a package
            'bootstrap cache' => ['bootstrap/cache/config.php', PathRejection::ExecutableExtension],
            'bootstrap cache data' => ['bootstrap/cache/data.bin', PathRejection::ForbiddenDirectory],
            'vendor' => ['vendor/autoload.txt', PathRejection::ForbiddenDirectory],
            'node modules' => ['node_modules/x/readme.md', PathRejection::ForbiddenDirectory],
            'framework cache' => ['storage/framework/cache/data/a', PathRejection::ForbiddenDirectory],
            'logs' => ['storage/logs/laravel.log', PathRejection::ForbiddenDirectory],
            'other backups' => ['storage/app/private/backups/1/manifest.json', PathRejection::ForbiddenDirectory],
            'install state' => ['storage/app/install/state.json', PathRejection::ForbiddenDirectory],
            'import inbox' => ['storage/app/import/other.oeparts', PathRejection::ForbiddenDirectory],
            'migration state' => ['storage/app/migration/runs/1/state.json', PathRejection::ForbiddenDirectory],
            'instance state' => ['storage/app/instance/instance.json', PathRejection::ForbiddenDirectory],
            'directory prefix in other case' => ['Storage/App/Install/state.json', PathRejection::ForbiddenDirectory],
            'private invoices cache' => ['storage/app/private/invoices/2026/1.pdf', PathRejection::ForbiddenDirectory],
        ];
    }

    #[Test]
    #[DataProvider('refused')]
    public function a_hostile_or_forbidden_path_is_refused_with_the_right_reason(string $path, PathRejection $reason): void
    {
        $decision = $this->policy()->decide($path);

        $this->assertFalse($decision->allowed, "'{$path}' must be refused");
        $this->assertSame($reason, $decision->reason, "'{$path}'");
        $this->assertNull($decision->path);
    }

    /** @return array<string, array{0: string}> */
    public static function allowed(): array
    {
        return [
            'product image' => ['storage/app/public/product-images/ab/12345.jpg'],
            'nested media' => ['storage/app/public/media/2026/10/photo.final.JPG'],
            'composed unicode name' => ["storage/app/public/media/caf\u{00E9}.jpg"],
            'cyrillic name' => ['storage/app/public/media/фото.png'],
            'payment proof' => ['storage/app/private/payment-proofs/proof_1.pdf'],
            'refund image' => ['storage/app/private/refund-images/1/a.webp'],
            'svg logo' => ['storage/app/public/logos/logo.svg'],
            'dots in a name' => ['storage/app/public/docs/my.report.2026-10.pdf'],
            'php only in the stem, not an extension' => ['storage/app/public/docs/phpinfo-notes.txt'],
            'a name that merely contains php' => ['storage/app/public/media/xphpx.jpg'],
            'env-like but not .env' => ['storage/app/public/docs/environment.md'],
            'public storage root file' => ['storage/app/public/robots-copy.txt'],
        ];
    }

    #[Test]
    #[DataProvider('allowed')]
    public function ordinary_user_files_are_allowed_unchanged(string $path): void
    {
        $decision = $this->policy()->decide($path);

        $this->assertTrue($decision->allowed, "'{$path}' must be allowed (refused as: ".($decision->reason?->value ?? '-').')');
        $this->assertSame($path, $decision->path);
        $this->assertNull($decision->reason);
    }

    #[Test]
    public function a_positive_allowlist_confines_paths_to_the_roots_of_the_selected_groups(): void
    {
        $roots = ['storage/app/public/product-images', 'storage/app/private/payment-proofs'];
        $policy = $this->policy();

        $this->assertTrue($policy->decide('storage/app/public/product-images/a.jpg', $roots)->allowed);
        $this->assertTrue($policy->decide('storage/app/private/payment-proofs/p.pdf', $roots)->allowed);
        $this->assertTrue($policy->decide('Storage/App/Public/Product-Images/a.jpg', $roots)->allowed, 'case-insensitive');

        $outside = $policy->decide('storage/app/public/media/other.jpg', $roots);
        $this->assertFalse($outside->allowed);
        $this->assertSame(PathRejection::OutsideAllowedRoots, $outside->reason);

        // A sibling whose name merely starts with the root's name is not under it.
        $this->assertSame(
            PathRejection::OutsideAllowedRoots,
            $policy->decide('storage/app/public/product-images-evil/a.jpg', $roots)->reason
        );

        // The deny rules still win inside an allowed root.
        $this->assertSame(
            PathRejection::ExecutableExtension,
            $policy->decide('storage/app/public/product-images/shell.php', $roots)->reason
        );
    }

    /** @return array<string, array{0: string, 1: bool}> */
    public static function contents(): array
    {
        return [
            'php tag' => ["<?php echo 'x';", true],
            'php tag upper' => ['<?PHP system($_GET[1]);', true],
            'short echo tag' => ['<?= $x ?>', true],
            'php tag after an image header' => ["\xFF\xD8\xFF\xE0\x00\x10JFIF\x00<?php eval(\$_POST[1]); ?>", true],
            'html script language php' => ['<script language="php">echo 1;</script>', true],
            'shebang' => ["#!/usr/bin/env php\nprint 1;", true],
            'shebang after a bom and blank lines' => ["\xEF\xBB\xBF\n#!/bin/sh\nid", true],
            'xml declaration is not php' => ['<?xml version="1.0"?><svg xmlns="http://www.w3.org/2000/svg"/>', false],
            'plain text' => ['Hello, this is a note.', false],
            'binary image' => ["\x89PNG\r\n\x1A\n\x00\x00\x00\rIHDR", false],
            'empty' => ['', false],
        ];
    }

    #[Test]
    #[DataProvider('contents')]
    public function the_start_of_a_file_is_searched_for_code(string $bytes, bool $isCode): void
    {
        $this->assertSame($isCode, $this->policy()->looksLikeCode($bytes));
    }

    #[Test]
    public function only_the_first_four_kilobytes_are_searched(): void
    {
        $late = str_repeat('x', PackagePathPolicy::SNIFF_BYTES + 10).'<?php echo 1;';

        $this->assertFalse($this->policy()->looksLikeCode($late), 'a tag beyond the sniffed window is out of scope by design (the extension rule is the main defence)');
    }
}

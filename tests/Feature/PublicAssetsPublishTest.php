<?php

namespace Tests\Feature;

use App\Providers\AppServiceProvider;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Process\Process;
use Tests\TestCase;

/**
 * A zip self-update only replaces the paths that the INSTALLED release's
 * config('updates.core_paths') lists, and public/ is (almost entirely) not one
 * of them — so a brand-new static file under public/ (e.g. the product
 * placeholder image, added after 1.0.16) is simply never delivered to an
 * install updating from an older release. Found by rehearsing the real
 * 1.0.16 -> 2.0.0 update: every product without an image pointed at a
 * placeholder URL that 404'd.
 *
 * The fix is a vendor-publish tag the finalize step runs on every update
 * (updates.post_swap.vendor_publish_tags) whose source lives under resources/
 * (a core path). These tests keep that mechanism wired and stop the next
 * static file from being forgotten.
 */
class PublicAssetsPublishTest extends TestCase
{
    private const TAG = 'oeparts-public-assets';

    /**
     * Files under public/ that are deliberately NOT re-delivered on update.
     * Either they are created once at install time and owned by the site
     * (index.php, .htaccess, favicons, manifest, flags, recovery console), or
     * another post-swap step already refreshes them (Filament's own published
     * assets via filament:upgrade; the compiled public/build via core_paths).
     * A NEW file must be classified — either added to resources/public-assets/
     * or consciously listed here.
     */
    private const NOT_REDELIVERED_EXACT = [
        'public/.htaccess',
        'public/apple-touch-icon.svg',
        'public/favicon.ico',
        'public/favicon.svg',
        'public/index.php',
        'public/logo.svg',
        'public/oe-recovery.php',
        'public/site.webmanifest',
    ];

    private const NOT_REDELIVERED_PREFIX = [
        'public/build/',          // core_paths
        'public/css/filament/',   // filament:upgrade
        'public/js/filament/',    // filament:upgrade
        'public/js/pxlrbt/',      // Filament plugin assets, republished by filament:upgrade
        'public/fonts/filament/', // filament:upgrade
        'public/flags/',          // shipped once; unchanged across releases
    ];

    #[Test]
    public function the_publish_tag_maps_the_resources_copy_onto_public(): void
    {
        $paths = ServiceProvider::pathsToPublish(AppServiceProvider::class, self::TAG);

        $this->assertSame(
            [resource_path('public-assets') => public_path()],
            $paths,
            'The public-assets tag must publish resources/public-assets/ onto public/.'
        );
    }

    #[Test]
    public function every_update_publishes_the_tag(): void
    {
        $this->assertContains(
            self::TAG,
            (array) config('updates.post_swap.vendor_publish_tags'),
            'Without this the finalize step never re-delivers public/ static files to an older install.'
        );
    }

    #[Test]
    public function the_published_copy_is_identical_to_the_file_served_from_public(): void
    {
        $sourceRoot = resource_path('public-assets');
        $this->assertDirectoryExists($sourceRoot);

        $checked = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($sourceRoot, \FilesystemIterator::SKIP_DOTS)) as $file) {
            $relative = ltrim(str_replace('\\', '/', substr($file->getPathname(), strlen($sourceRoot))), '/');
            $served = public_path($relative);

            $this->assertFileExists($served, "resources/public-assets/{$relative} has no counterpart in public/ (fresh installs and git checkouts serve public/ directly).");
            $this->assertSame(
                hash_file('sha256', $file->getPathname()),
                hash_file('sha256', $served),
                "resources/public-assets/{$relative} and public/{$relative} have drifted apart — update both."
            );
            $checked++;
        }

        $this->assertGreaterThan(0, $checked);
    }

    #[Test]
    public function every_tracked_public_file_is_either_republished_or_consciously_exempt(): void
    {
        $git = new Process(['git', 'ls-files', '-z', '--', 'public'], base_path());
        $git->run();

        if (! $git->isSuccessful() || $git->getOutput() === '') {
            $this->markTestSkipped('git metadata is not available in this environment.');
        }

        $unclassified = [];
        foreach (array_filter(explode("\0", $git->getOutput())) as $path) {
            if (in_array($path, self::NOT_REDELIVERED_EXACT, true)) {
                continue;
            }
            foreach (self::NOT_REDELIVERED_PREFIX as $prefix) {
                if (str_starts_with($path, $prefix)) {
                    continue 2;
                }
            }

            // Anything else must be republished from resources/public-assets/.
            $relative = substr($path, strlen('public/'));
            if (! is_file(resource_path('public-assets/'.$relative))) {
                $unclassified[] = $path;
            }
        }

        $this->assertSame(
            [],
            $unclassified,
            'These public/ files would never reach an install updating from an older release. '
            .'Add them to resources/public-assets/ (same relative path) or list them as consciously exempt in this test.'
        );
    }
}

<?php

namespace App\Observers\Concerns;

use App\Enums\RedirectType;
use App\Models\Redirect;
use App\Support\LocaleRegistry;
use Illuminate\Support\Facades\Log;

/**
 * When an admin changes the slug of a public page (CMS page, blog post,
 * brand, car model), the OLD URL is already indexed and linked to — without
 * a 301 it starts returning 404, the page's accumulated ranking is lost and
 * the NotFoundLog fills up. Products already get the same protection
 * (ProductObserver); this gives the slug-addressed content types the same.
 *
 * One permanent redirect per active locale, to a RELATIVE target (never an
 * absolute URL: that would bake a host into the database — the very thing
 * that put localhost into a sitemap and would break on a domain change).
 */
trait RedirectsOnSlugChange
{
    /**
     * @param  \Closure(string, string): string  $pathFor  (locale, slug) => path with no leading slash, e.g. "en/blog/my-post"
     */
    protected function redirectOldSlug(string $oldSlug, string $newSlug, \Closure $pathFor): void
    {
        if ($oldSlug === '' || $newSlug === '' || $oldSlug === $newSlug) {
            return;
        }

        try {
            foreach (LocaleRegistry::codes() as $locale) {
                $from = strtolower(trim($pathFor($locale, $oldSlug), '/'));
                $newPath = trim($pathFor($locale, $newSlug), '/');

                // Reusing a slug that was once redirected away from (renaming
                // back) must not leave a redirect on the now-live URL: it
                // would send visitors in a circle.
                Redirect::where('from_url', strtolower($newPath))->delete();

                Redirect::updateOrCreate(
                    ['from_url' => $from],
                    ['to_url' => '/'.$newPath, 'type' => RedirectType::Permanent, 'is_active' => true]
                );
            }
        } catch (\Throwable $e) {
            // Must never break the save that triggered this.
            Log::warning('Could not create the redirect for a changed slug', [
                'old' => $oldSlug,
                'new' => $newSlug,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

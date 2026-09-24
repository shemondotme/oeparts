/**
 * Runs once before any project. Fails the whole run immediately, with one
 * actionable message, if the storefront itself isn't serving — instead of
 * letting hundreds of tests each fail (or worse, "pass" by scanning error
 * pages) for a reason that has nothing to do with the code under test.
 *
 * Confirmed live 2026-09-24: the default "en" row was deleted from the
 * `languages` table (an interrupted crud-edit run left it renamed, then a
 * cleanup sweep deleted it), LocaleRegistry stopped registering the /en/
 * routes, and every storefront URL 404'd. ~430 of 515 tests failed with
 * unrelated-looking timeouts, and the first 17 reported green because axe
 * scans of a 404 page find no violations.
 */
const PROBES = ['/en', '/en/parts'];

async function probe(baseURL, path) {
    // The dev server is slow on a cold start (several seconds per page), and
    // Docker may still be warming up — retry before declaring it broken.
    let last = 'no response';
    for (let attempt = 1; attempt <= 6; attempt++) {
        try {
            const res = await fetch(`${baseURL}${path}`, { redirect: 'follow', signal: AbortSignal.timeout(60_000) });
            if (res.status === 200) {
                return null;
            }
            last = `HTTP ${res.status}`;
            // A definite 404 is not a warm-up problem — don't burn minutes retrying it.
            if (res.status === 404) {
                break;
            }
        } catch (e) {
            last = e.message;
        }
        await new Promise((r) => setTimeout(r, 5000));
    }

    return last;
}

export default async function globalSetup(config) {
    const baseURL = config.projects[0]?.use?.baseURL ?? 'http://oeparts.test';

    for (const path of PROBES) {
        const failure = await probe(baseURL, path);
        if (failure) {
            throw new Error(
                `Storefront preflight failed: GET ${baseURL}${path} -> ${failure}.\n` +
                    'Every result from this run would be meaningless, so it was aborted before any test started.\n' +
                    'Likely causes, most to least likely:\n' +
                    '  1. The default "en" row is missing from the `languages` table — LocaleRegistry builds every /{locale}/ route from it.\n' +
                    '     Restore with: docker compose exec -T -u sail laravel.test php artisan db:seed --class=LanguagesSeeder --force\n' +
                    '     (then `php artisan cache:clear`). Check for renamed "E2E Edited ..." rows left by an interrupted crud-edit run.\n' +
                    '  2. The Docker stack is down or still starting (docker compose ps).\n' +
                    '  3. The app is in maintenance mode or throwing on every request (storage/logs).',
            );
        }
    }
}

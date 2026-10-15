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

/**
 * The opt-in `migration` project (OE_E2E_MIGRATION=1, tests/e2e/migration/*) installs a site through the real installer. It is
 * the one thing in this suite that must NEVER run against the developer's own stack: it would try to install over it. So when it
 * is enabled this file does a different job: it refuses the dev host (oeparts.test and the dev compose project) and checks that
 * the target really is a fresh, uninstalled copy, and it skips the storefront probes below (a fresh install has no storefront).
 */
const DEV_HOSTS = ['oeparts.test'];

async function migrationPreflight() {
    const baseURL = (process.env.OE_E2E_MIGRATION_URL ?? '').replace(/\/+$/, '');
    if (!baseURL) {
        throw new Error('OE_E2E_MIGRATION=1 needs OE_E2E_MIGRATION_URL: the address of a FRESH, uninstalled rehearsal copy of the app.');
    }

    const host = new URL(baseURL).hostname.toLowerCase();
    if (DEV_HOSTS.includes(host)) {
        throw new Error(
            `Refusing to run the migration project against ${host}: it walks the installer and would try to install over the developer's site.\n` +
                'Point OE_E2E_MIGRATION_URL at the rehearsal environment (docker/rehearsal/migration/, e.g. mig-b).',
        );
    }

    if ((process.env.OE_E2E_MIGRATION_PROJECT ?? '') === '' || process.env.OE_E2E_MIGRATION_PROJECT === 'oeparts') {
        throw new Error('OE_E2E_MIGRATION_PROJECT must name the rehearsal compose project (not empty, and never the dev stack "oeparts").');
    }

    // A fresh copy answers /install with the installer. An installed copy redirects away: the spec would then test nothing, or worse.
    let res;
    try {
        res = await fetch(`${baseURL}/install`, { redirect: 'follow', signal: AbortSignal.timeout(60_000) });
    } catch (e) {
        throw new Error(`The rehearsal copy at ${baseURL} did not answer: ${e.message}`);
    }
    const final = new URL(res.url);
    if (res.status !== 200 || !final.pathname.startsWith('/install')) {
        throw new Error(
            `${baseURL}/install answered HTTP ${res.status} at ${final.pathname}: this copy is already installed (or down).\n` +
                'The migration project needs a fresh, uninstalled copy. Reset the rehearsal environment first.',
        );
    }
}

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
    if (process.env.OE_E2E_MIGRATION === '1') {
        await migrationPreflight();

        return;
    }

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

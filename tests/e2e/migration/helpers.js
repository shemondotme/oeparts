import { execSync } from 'node:child_process';

/**
 * Helpers for the installer-import rehearsal spec (tests/e2e/migration/*).
 *
 * This project is opt-in (OE_E2E_MIGRATION=1) and runs against a SEPARATE, FRESH, UNINSTALLED copy of the app in its own compose
 * project (docker/rehearsal/migration/, "mig-b"): it walks the real installer, which would wreck a developer's installed site.
 * Everything here therefore talks to that one named compose project, never to the dev stack: the existing `artisan()` helper
 * (tests/e2e/helpers.js) hardcodes the dev stack, which is exactly what must not be used here.
 */

const PROJECT_NAME = /^[a-z0-9][a-z0-9_-]{0,62}$/;

/** The compose project of the dev stack. A rehearsal must never be pointed at it. */
const DEV_PROJECT = 'oeparts';

export function requiredEnv(name) {
    const value = process.env[name];
    if (value === undefined || value === '') {
        throw new Error(`${name} is not set. The migration project needs it (see the header of tests/e2e/migration/installer-import.spec.js).`);
    }
    return value;
}

/** Everything the spec needs, read once and validated, so a missing value fails the run before the first browser opens. */
export function settings() {
    const project = requiredEnv('OE_E2E_MIGRATION_PROJECT');
    if (!PROJECT_NAME.test(project)) {
        throw new Error(`OE_E2E_MIGRATION_PROJECT "${project}" is not a plain compose project name.`);
    }
    if (project === DEV_PROJECT) {
        throw new Error(`OE_E2E_MIGRATION_PROJECT must not be the dev stack ("${DEV_PROJECT}"): this spec installs a site.`);
    }

    const url = requiredEnv('OE_E2E_MIGRATION_URL').replace(/\/+$/, '');

    return {
        url,
        project,
        package: requiredEnv('OE_E2E_MIGRATION_PACKAGE'),
        passphrase: requiredEnv('OE_E2E_MIGRATION_PASSPHRASE'),
        db: {
            host: requiredEnv('OE_E2E_MIGRATION_DB_HOST'),
            port: process.env.OE_E2E_MIGRATION_DB_PORT || '3306',
            name: requiredEnv('OE_E2E_MIGRATION_DB_NAME'),
            user: requiredEnv('OE_E2E_MIGRATION_DB_USER'),
            password: process.env.OE_E2E_MIGRATION_DB_PASSWORD || '',
            create: process.env.OE_E2E_MIGRATION_DB_CREATE === '1',
        },
        // 'staging' (a test copy) is the default: it needs no https and switches real mail, payments and tracking off.
        launch: process.env.OE_E2E_MIGRATION_LAUNCH === 'live' ? 'live' : 'staging',
        // e.g. "preset:catalog_only"; empty = the first preset the package offers.
        selection: process.env.OE_E2E_MIGRATION_SELECTION || '',
        // This run only proves that a passphrase is REFUSED over plain http (the rehearsal's .env has no override) and stops there.
        expectHttpRefusal: process.env.OE_E2E_MIGRATION_EXPECT_HTTP_REFUSAL === '1',
        // Kill the PHP service during the data copy and prove the run resumes (needs the service name below).
        kill: process.env.OE_E2E_MIGRATION_KILL === '1',
        phpService: process.env.OE_E2E_MIGRATION_PHP_SERVICE || 'php-fpm',
    };
}

/** A request over plain http to a host that is not the machine itself: the installer refuses a passphrase there. */
export function isInsecureRemote(url) {
    return url.startsWith('http://') && !/^http:\/\/(localhost|127\.\d+\.\d+\.\d+|\[::1\])(:|\/|$)/i.test(url);
}

function assertRehearsalProject(project) {
    if (!PROJECT_NAME.test(project) || project === DEV_PROJECT) {
        throw new Error(`"${project}" is not a rehearsal compose project (the dev stack "${DEV_PROJECT}" is never touched by this spec).`);
    }
}

function compose(project, args, options = {}) {
    assertRehearsalProject(project);
    const file = process.env.OE_E2E_MIGRATION_COMPOSE_FILE ? `-f "${process.env.OE_E2E_MIGRATION_COMPOSE_FILE}" ` : '';

    return execSync(`docker compose ${file}-p ${project} ${args}`, { encoding: 'utf8', stdio: ['ignore', 'pipe', 'inherit'], ...options });
}

/** `php artisan <command>` inside the rehearsal project's app container (the dev stack's is never touched). */
export function artisanIn(project, command) {
    return compose(project, `exec -T laravel.test php artisan ${command}`);
}

/** Any other docker compose action on the rehearsal project (kill / up the PHP service for the resume proof). */
export function composeIn(project, args) {
    return compose(project, args);
}

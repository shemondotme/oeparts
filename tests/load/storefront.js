import http from 'k6/http';
import { check, sleep } from 'k6';

/**
 * Genuine concurrent HTTP load test against the storefront's read-heavy
 * public pages (homepage, search console, search results, PDP) — the exact
 * kind of test the backlog's earlier attempts (backgrounded curl + wait in
 * Git Bash on Windows) couldn't produce a trustworthy signal for, and
 * abandoned rather than trust. Run via k6's own Docker image, joined to the
 * same compose network as the app, so it reaches laravel.test directly by
 * service name — no host-level DNS/hosts-file entry needed:
 *
 *   docker run --rm --network oeparts_sail -v "$(pwd)/tests/load:/scripts" \
 *     -e BASE_URL=http://laravel.test grafana/k6 run /scripts/storefront.js
 *
 * OE_LOAD_OEM below (ALF-000001) is a real product confirmed present in the
 * 100k-scale seeded dev DB (see [[project_100k_scale_performance_pass]] /
 * [[project_production_catalog_scale]]) — swap it if that seed changes.
 *
 * Thresholds are deliberately lenient/informational (catch a genuine outage
 * — 5xx errors, requests that never complete — not fine-grained performance
 * regressions). This dev environment's own Docker performance varies 50%+
 * run-to-run (see [[feedback_no_absolute_ms_assertions]]), so a strict p95
 * ceiling would be noise, not signal, exactly the reasoning that document
 * already established for the PHPUnit side of this same problem.
 */

const BASE_URL = __ENV.BASE_URL || 'http://laravel.test';
const OEM = __ENV.OE_LOAD_OEM || 'ALF-000001';

export const options = {
    scenarios: {
        storefront_browsing: {
            executor: 'ramping-vus',
            startVUs: 0,
            stages: [
                { duration: '15s', target: 10 },
                { duration: '30s', target: 20 },
                { duration: '30s', target: 20 },
                { duration: '15s', target: 0 },
            ],
        },
    },
    thresholds: {
        // Informational floor, not a performance gate: a real outage (500s,
        // connection failures) should fail the run; slow-but-successful
        // responses on this dev hardware should not.
        http_req_failed: ['rate<0.05'],
    },
};

export default function () {
    const home = http.get(`${BASE_URL}/en/`);
    check(home, { 'home: status is 200': (r) => r.status === 200 });

    const console_ = http.get(`${BASE_URL}/en/parts`);
    check(console_, { 'search console: status is 200': (r) => r.status === 200 });

    const results = http.get(`${BASE_URL}/en/parts/${OEM}`);
    check(results, { 'search results: status is 200 or a redirect to the PDP': (r) => r.status === 200 || r.status === 302 });

    sleep(1);
}

import http from 'k6/http';
import { check, sleep } from 'k6';

/**
 * Does the shop keep answering visitors while a LIVE-COPY export of it is running?
 *
 * A live copy (php artisan oeparts:migrate:export, without --freeze) is meant to leave the shop running: the export reads the
 * database and the uploaded files in small steps and never puts the shop in maintenance. This script puts the same read-heavy
 * storefront traffic as tests/load/storefront.js on the site twice, so the two can be compared:
 *
 *   baseline   about a minute of visitors with NOTHING else going on
 *   export     the same visitors while an export is running (start it when this script says so)
 *
 * It asserts nothing about speed. This dev environment's own timing varies 50% or more from run to run, so a fixed millisecond
 * ceiling would be noise. What it fails on is an outage (errors, a 503 maintenance page, requests that never complete) in either
 * phase. The slow-down is RECORDED, not judged: the end of the run prints the 95th-percentile response time of each phase and
 * their ratio, and writes the same numbers as JSON when OE_LOAD_SUMMARY_FILE is set. A ratio is comparable between runs on the
 * same machine; an absolute number is not.
 *
 * Run it against a site that has a large catalog (the point of the test is an export that takes minutes), through k6's own Docker
 * image joined to the compose network, like storefront.js. The export itself is started by hand, in a second terminal, when this
 * script prints "START THE EXPORT NOW":
 *
 *   docker run --rm --network oeparts_sail -v "$(pwd)/tests/load:/scripts" \
 *     -e BASE_URL=http://laravel.test grafana/k6 run /scripts/migration-export-under-load.js
 *
 *   # in a second terminal, at the moment the script says so (the passphrase is shown once; the package lands in
 *   # storage/app/migration/exports/ and is removed by the janitor after OE_MIGRATION_EXPORT_RETENTION_DAYS days):
 *   docker compose exec laravel.test php artisan oeparts:migrate:export --preset=full_live --generate-passphrase
 *
 * Settings (environment variables, all optional):
 *
 *   BASE_URL                  the site to visit (default http://laravel.test)
 *   OE_LOAD_OEM               a part number that exists in the catalog (default ALF-000001, as in storefront.js)
 *   OE_LOAD_PHASE             both (default), baseline, or export. "export" skips the baseline and starts the visitors at once:
 *                             start the export first, then this script, when you already have a baseline from an earlier run.
 *   OE_LOAD_BASELINE_SECONDS  how long the baseline phase lasts (default 60)
 *   OE_LOAD_EXPORT_SECONDS    how long the visitors keep coming while the export runs (default 300); make it at least as long as
 *                             the export, or the last part of the export runs without a witness
 *   OE_LOAD_VUS               visitors at the same time at the top of each phase (default 20)
 *   OE_LOAD_SUMMARY_FILE      write the recorded numbers to this JSON file (inside the container, for example
 *                             /scripts/summary.json)
 */

const BASE_URL = __ENV.BASE_URL || 'http://laravel.test';
const OEM = __ENV.OE_LOAD_OEM || 'ALF-000001';
const PHASE = ['both', 'baseline', 'export'].includes(__ENV.OE_LOAD_PHASE) ? __ENV.OE_LOAD_PHASE : 'both';
const BASELINE_SECONDS = Math.max(15, parseInt(__ENV.OE_LOAD_BASELINE_SECONDS || '60', 10));
const EXPORT_SECONDS = Math.max(30, parseInt(__ENV.OE_LOAD_EXPORT_SECONDS || '300', 10));
const VUS = Math.max(1, parseInt(__ENV.OE_LOAD_VUS || '20', 10));
const RAMP_SECONDS = 10;

// The time between the end of the baseline and the start of the export window: enough to read the message and press Enter.
const PAUSE_SECONDS = 20;

const runsBaseline = PHASE === 'both' || PHASE === 'baseline';
const runsExport = PHASE === 'both' || PHASE === 'export';
const exportStart = runsBaseline ? BASELINE_SECONDS + RAMP_SECONDS * 2 + PAUSE_SECONDS : 0;

/** The same ramp for both phases, so the only difference between them is the export. */
function visitors(phase, startTime) {
    return {
        executor: 'ramping-vus',
        exec: 'browse',
        startTime: `${startTime}s`,
        startVUs: 0,
        gracefulRampDown: '5s',
        stages: [
            { duration: `${RAMP_SECONDS}s`, target: VUS },
            { duration: `${phase === 'baseline' ? BASELINE_SECONDS : EXPORT_SECONDS}s`, target: VUS },
            { duration: `${RAMP_SECONDS}s`, target: 0 },
        ],
        tags: { phase },
        env: { PHASE: phase },
    };
}

const scenarios = {};

if (runsBaseline) {
    scenarios.baseline = visitors('baseline', 0);
}

if (runsExport) {
    scenarios.export_running = visitors('export', exportStart);

    // One iteration, one visitor, right when the export window opens: it only tells the person at the keyboard what to do.
    scenarios.announce = {
        executor: 'per-vu-iterations',
        exec: 'announce',
        vus: 1,
        iterations: 1,
        startTime: `${exportStart}s`,
        maxDuration: '30s',
    };
}

const thresholds = {};

// Lenient on purpose: an outage fails the run, a slow answer does not. The empty lists make k6 keep the per-phase response times
// so the summary below can compare them (a sub-metric exists only when a threshold names it).
for (const [name, active] of [['baseline', runsBaseline], ['export', runsExport]]) {
    if (!active) {
        continue;
    }

    thresholds[`http_req_failed{phase:${name}}`] = ['rate<0.05'];
    thresholds[`checks{phase:${name}}`] = ['rate>0.95'];
    thresholds[`http_req_duration{phase:${name}}`] = [];
}

export const options = { scenarios, thresholds };

export function announce() {
    console.log('');
    console.log('==================================================================');
    console.log('  START THE EXPORT NOW (second terminal):');
    console.log('    docker compose exec laravel.test php artisan oeparts:migrate:export --preset=full_live --generate-passphrase');
    console.log(`  The visitors keep coming for ${EXPORT_SECONDS} seconds.`);
    console.log('==================================================================');
    console.log('');
}

export function browse() {
    // A live copy must never put the shop in maintenance: a 503 here is a failed check, not a slow answer.
    const home = http.get(`${BASE_URL}/en/`);
    check(home, { 'home: status is 200': (r) => r.status === 200 });

    const console_ = http.get(`${BASE_URL}/en/parts`);
    check(console_, { 'search console: status is 200': (r) => r.status === 200 });

    const results = http.get(`${BASE_URL}/en/parts/${OEM}`);
    check(results, { 'search results: status is 200 or a redirect to the PDP': (r) => r.status === 200 || r.status === 302 });

    sleep(1);
}

function p95(data, phase) {
    const metric = data.metrics[`http_req_duration{phase:${phase}}`];

    return metric && metric.values && typeof metric.values['p(95)'] === 'number' ? metric.values['p(95)'] : null;
}

function count(data, phase) {
    const metric = data.metrics[`http_req_failed{phase:${phase}}`];

    return metric && metric.values ? metric.values.passes : null;
}

export function handleSummary(data) {
    const baseline = runsBaseline ? p95(data, 'baseline') : null;
    const during = runsExport ? p95(data, 'export') : null;
    const ratio = baseline !== null && during !== null && baseline > 0 ? during / baseline : null;

    const recorded = {
        phase: PHASE,
        baseline_p95_ms: baseline,
        export_p95_ms: during,
        export_over_baseline_p95_ratio: ratio,
        failed_requests: { baseline: count(data, 'baseline'), export: count(data, 'export') },
        note: 'Recorded, not judged. Compare the ratio between runs on the same machine; do not compare milliseconds.',
    };

    const fmt = (value) => (value === null ? 'not measured' : `${value.toFixed(0)} ms`);
    const lines = [
        '',
        'Storefront while an export runs',
        `  baseline p95:        ${fmt(baseline)}`,
        `  during export p95:   ${fmt(during)}`,
        `  ratio (export / baseline): ${ratio === null ? 'not measured (needs both phases)' : ratio.toFixed(2)}`,
        '  Recorded, not judged: the run fails only on errors or a failed check, never on speed.',
        '',
    ];

    const output = { stdout: lines.join('\n') };

    if (__ENV.OE_LOAD_SUMMARY_FILE) {
        output[__ENV.OE_LOAD_SUMMARY_FILE] = JSON.stringify(recorded, null, 2);
    }

    return output;
}

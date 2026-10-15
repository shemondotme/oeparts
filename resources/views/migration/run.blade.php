<!DOCTYPE html>
{{--
  The runner page of a site import: watch it work and keep it moving. This is the one page of the migration feature that
  is not built from stock Filament parts, because it has to keep working while the database, the settings and the admin
  panel are being replaced (see MigrationRunController). It reads nothing from the database. The run's token arrives in
  the URL fragment (which the server never receives), is moved to sessionStorage and removed from the address bar, and is
  sent in a header with every call.
--}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Moving your site</title>
    <style>
        :root { color-scheme: light dark; }
        body { margin: 0; min-height: 100vh; font: 16px/1.6 system-ui, sans-serif; background: #f6f7f9; color: #1f2933; }
        main { max-width: 40rem; margin: 2rem auto; padding: 0 1rem; }
        .card { background: #fff; border-radius: .75rem; box-shadow: 0 1px 3px rgba(0,0,0,.12); padding: 1.5rem; }
        h1 { font-size: 1.35rem; margin: 0 0 1rem; }
        .bar { height: .6rem; background: #e4e7eb; border-radius: 1rem; overflow: hidden; margin: 1rem 0; }
        .bar > div { height: 100%; width: 3%; background: #2563eb; transition: width .4s; }
        .state { font-weight: 600; }
        .note { color: #52606d; font-size: .9rem; }
        .error { color: #b91c1c; }
        .ok { color: #047857; }
        code { background: rgba(127,127,127,.15); padding: .1rem .35rem; border-radius: .25rem; }
        @media (prefers-color-scheme: dark) { body { background: #12161b; color: #e4e7eb; } .card { background: #1b2128; } .bar { background: #2b333b; } .note { color: #9aa5b1; } .error { color: #f87171; } .ok { color: #34d399; } }
    </style>
</head>
<body>
<main>
    <div class="card">
        <h1>Moving your site</h1>
        <p class="state" id="state">Starting...</p>
        <div class="bar" aria-hidden="true"><div id="bar"></div></div>
        <p class="note" id="detail">Keep this page open. It keeps the move going. If you close it, the move carries on in the background on most servers, and you can come back to this address.</p>
        <p class="error" id="error" hidden></p>
    </div>
    @include('migration.partials.confirm-panel')
</main>
<script>
(function () {
    var runId = @json($runId);
    var key = 'oe_migrate_token_' + runId;
    var base = '/migrate/run/' + runId;
    var token = null;
    var failures = 0;
    var panels = window.oeRunnerPanels || { confirm: function () {}, report: function () {} };

    var fromHash = (location.hash.match(/[#&]t=([A-Za-z0-9]+)/) || [])[1];
    try {
        if (fromHash) { sessionStorage.setItem(key, fromHash); }
        token = sessionStorage.getItem(key);
    } catch (e) { token = fromHash || null; }
    if (fromHash && history.replaceState) { history.replaceState(null, '', location.pathname); }

    var el = function (id) { return document.getElementById(id); };
    var say = function (text, kind) { el('state').textContent = text; el('state').className = 'state ' + (kind || ''); };
    var fail = function (text) { el('error').hidden = false; el('error').textContent = text; };

    if (!token) {
        say('This page needs the address you were given when the move started.', 'error');
        fail('The key for this move is missing. Open the link from the screen or the command that started it, or run: php artisan oeparts:migrate:import --resume');
        return;
    }

    var STAGES = { locate: 'Looking at the package', verify: 'Checking every part of the package', ensure_schema: 'Preparing the database', preflight: 'Checking this server', recovery_point: 'Saving a way back', arm: 'Closing the site', db_clear: 'Clearing the old data', db_data: 'Copying the data', db_index_drop: 'Preparing the search index', db_index_restore: 'Building the search index. This can take several minutes. Nothing is wrong.', db_audit: 'Checking the copied data', db_seed_defaults: 'Filling in defaults', files: 'Copying the files', complete: 'Finishing' };

    function render(p) {
        if (!p || p.found === false) { say('This move has finished or does not exist.', ''); return false; }
        var label = STAGES[p.stage] || p.stage || '';
        panels.report(p);
        if (p.status === 'complete') { say('Done. Your site has been moved.', 'ok'); el('bar').style.width = '100%'; return false; }
        if (p.status === 'failed') { say('The move stopped before anything was changed.', 'error'); fail(describe(p)); return false; }
        if (p.status === 'rolled_back') { say('The move failed and your old site was put back exactly as it was.', 'error'); fail(describe(p)); return false; }
        if (p.status === 'rollback_failed') { say('The move failed and putting the old site back also failed.', 'error'); fail('The site is closed on purpose. Run: php artisan oeparts:migrate:import --rollback'); return false; }
        if (p.status === 'awaiting_confirmation') { say('Ready. Waiting for you to confirm.', ''); panels.confirm(p, { base: base, token: token, resume: loop }); return false; }
        if (p.stalled) { fail('The move has stopped and needs attention. Run: php artisan oeparts:migrate:import --resume'); }
        say(label || 'Working...', '');
        if (typeof p.percent === 'number') { el('bar').style.width = Math.max(3, Math.min(100, p.percent)) + '%'; }
        if (p.detail) { el('detail').textContent = p.detail; }
        return true;
    }

    function describe(p) { return p.error && p.error.code ? 'Reason: ' + p.error.code : 'See the log on the server for the reason.'; }

    function call(method, path) {
        return fetch(base + path, { method: method, headers: { 'X-Migrate-Token': token, 'Accept': 'application/json' }, cache: 'no-store' })
            .then(function (r) { if (r.status === 404) { throw new Error('gone'); } return r.json(); });
    }

    function loop() {
        call('POST', '/advance').then(function (p) {
            failures = 0;
            if (render(p)) { setTimeout(loop, 400); }
        }).catch(function (e) {
            if (e.message === 'gone') { say('This move has finished or the key is no longer valid.', ''); return; }
            failures++;
            say('The server did not answer. Trying again...', '');
            setTimeout(loop, Math.min(15000, 1000 * failures));
        });
    }

    call('GET', '/status').then(function (p) { if (render(p)) { loop(); } }).catch(function () { loop(); });
})();
</script>
</body>
</html>

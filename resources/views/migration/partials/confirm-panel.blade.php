{{--
  The confirm panel of the runner page: shown when the checks are done and the run waits for a person to say "go", and the
  link to the report once the run has ended. Plain markup and a small script, like the page around it; it reads nothing from
  the database. Everything it shows arrives in the runner's own answers (`confirm`, `report_url`) and is written into the page
  with textContent only, never as HTML.

  The page's script calls window.oeRunnerPanels.confirm(answer, { base, token, resume }) and .report(answer). The panel decides
  nothing: the server refuses what may not go ahead (POST {base}/confirm), and Start is disabled only so that nobody presses it
  before every warning has its own tick.
--}}
<style>
    .oe-panel { margin-top: 1rem; }
    .oe-panel h2 { font-size: 1.15rem; margin: 0 0 .75rem; }
    .oe-panel h3 { font-size: .8rem; letter-spacing: .08em; text-transform: uppercase; margin: 1.25rem 0 .4rem; color: #52606d; }
    .oe-panel ul { list-style: none; margin: 0; padding: 0; }
    .oe-panel li { margin: .35rem 0; }
    .oe-panel label.oe-row { display: flex; gap: .6rem; align-items: flex-start; margin: .5rem 0; }
    .oe-panel label.oe-row input { margin-top: .35rem; }
    .oe-pill { display: inline-block; min-width: 4.4rem; text-align: center; font-size: .75rem; font-weight: 600; border-radius: 1rem; padding: .05rem .5rem; margin-right: .4rem; }
    .oe-pass { background: #d1fae5; color: #065f46; }
    .oe-warn { background: #fef3c7; color: #92400e; }
    .oe-fail { background: #fee2e2; color: #991b1b; }
    .oe-added { background: #fef3c7; color: #92400e; border-radius: .5rem; padding: .6rem .8rem; margin: .5rem 0; }
    .oe-panel button { font: inherit; font-weight: 600; border: 0; border-radius: .6rem; padding: .7rem 1.4rem; background: #2563eb; color: #fff; cursor: pointer; }
    .oe-panel button:disabled { background: #9aa5b1; cursor: not-allowed; }
    .oe-panel input[type=text] { font: inherit; padding: .4rem .6rem; border-radius: .4rem; border: 1px solid #9aa5b1; }
    @media (prefers-color-scheme: dark) { .oe-panel h3 { color: #9aa5b1; } .oe-pass { background: #064e3b; color: #a7f3d0; } .oe-warn, .oe-added { background: #78350f; color: #fde68a; } .oe-fail { background: #7f1d1d; color: #fecaca; } }
</style>

<section class="card oe-panel" id="oe-confirm" hidden aria-live="polite">
    <h2>Check, then start</h2>
    <p class="note" id="oe-confirm-intro">The package is intact and this server was checked. Nothing on this site is replaced until you press Start.</p>

    <p class="error" id="oe-problem" hidden></p>

    <h3>What we checked</h3>
    <ul id="oe-checks"></ul>
    <details id="oe-passed" hidden>
        <summary class="note" id="oe-passed-summary"></summary>
        <ul id="oe-passed-list"></ul>
    </details>

    <h3>What will be brought over</h3>
    <p id="oe-parts"></p>
    <div class="oe-added" id="oe-added" hidden>
        <ul id="oe-added-list"></ul>
        <label class="oe-row"><input type="checkbox" id="oe-ack-groups"><span>I have read this. Bring these parts too.</span></label>
    </div>

    <h3>Where it will live</h3>
    <p id="oe-site"></p>

    <div id="oe-acks"></div>

    <div id="oe-populated" hidden>
        <p class="error">This site already has data. All of it will be deleted and replaced.</p>
        <label class="oe-row"><input type="checkbox" id="oe-replace-populated"><span>I understand that this replaces a site that is in use.</span></label>
    </div>

    <div id="oe-typed-box" hidden>
        <label>Type REPLACE to confirm <input type="text" id="oe-typed" autocomplete="off" spellcheck="false"></label>
    </div>

    <p class="note">We make a recovery point first, so you can go back.</p>
    <p class="error" id="oe-start-error" hidden></p>
    <p><button type="button" id="oe-start" disabled>Start import</button></p>
    <p class="note" id="oe-back" hidden><a href="/install/import/package">Go back and choose differently</a></p>
</section>

<p class="note" id="oe-report" hidden><a id="oe-report-link" href="#" rel="noopener">Open the report of this import</a> (the link works for two hours).</p>

<script>
window.oeRunnerPanels = (function () {
    var facts = null;
    var ctx = null;
    var LABEL = { pass: 'Passed', warn: 'Warning', fail: 'Problem' };

    function el(id) { return document.getElementById(id); }
    function node(tag, content, cls) { var n = document.createElement(tag); n.textContent = content; if (cls) { n.className = cls; } return n; }
    function empty(n) { while (n.firstChild) { n.removeChild(n.firstChild); } }
    function line(check) {
        var li = document.createElement('li');
        li.appendChild(node('span', LABEL[check.status] || check.status, 'oe-pill oe-' + (LABEL[check.status] ? check.status : 'pass')));
        li.appendChild(node('strong', check.label));
        if (check.message) { li.appendChild(document.createTextNode(' ' + check.message)); }
        return li;
    }
    function say(id, text) { var n = el(id); n.textContent = text || ''; n.hidden = !text; }

    function ready() {
        if (!facts || !facts.can_start) { return false; }
        if (facts.selection.added.length > 0 && !el('oe-ack-groups').checked) { return false; }
        var boxes = document.querySelectorAll('#oe-acks input[type=checkbox]');
        for (var i = 0; i < boxes.length; i++) { if (!boxes[i].checked) { return false; } }
        if (facts.populated && facts.typed_required && !el('oe-replace-populated').checked) { return false; }
        if (facts.typed_required && el('oe-typed').value !== 'REPLACE') { return false; }
        return true;
    }
    function refresh() { el('oe-start').disabled = !ready(); }

    function render() {
        var problems = [], passed = [], list = el('oe-checks');
        empty(list); empty(el('oe-passed-list')); empty(el('oe-acks')); empty(el('oe-added-list'));

        facts.checks.forEach(function (c) { (c.status === 'pass' ? passed : problems).push(c); });
        problems.forEach(function (c) { list.appendChild(line(c)); });
        passed.forEach(function (c) { el('oe-passed-list').appendChild(line(c)); });
        el('oe-passed').hidden = passed.length === 0;
        el('oe-passed-summary').textContent = passed.length + (passed.length === 1 ? ' check passed' : ' checks passed');
        if (problems.length === 0) { list.appendChild(node('li', 'Everything passed.', 'note')); }

        if (facts.blocked_by_checks) { say('oe-problem', 'Something blocks this import. Nothing was changed. Fix what is marked Problem, then start again from the beginning.'); }
        else if (facts.selection.error) { say('oe-problem', facts.selection.error); }
        else { say('oe-problem', ''); }

        var s = facts.selection;
        el('oe-parts').textContent = s.error ? 'Nothing: the choice cannot go ahead.' : s.labels.join(', ') + '.';
        s.added.forEach(function (a) {
            var because = a.because.length ? a.because.join(' and ') + (a.because.length === 1 ? ' needs' : ' need') + ' it' : 'what you chose cannot work without it';
            el('oe-added-list').appendChild(node('li', 'We added ' + a.label + ' because ' + because + '. Please review and confirm.'));
        });
        el('oe-added').hidden = s.added.length === 0;
        el('oe-ack-groups').checked = false;

        if (facts.launch === 'staging') {
            el('oe-site').textContent = 'A test copy at ' + (facts.url || 'this address') + '. Real emails, scheduled jobs, payments, search engine visibility and tracking are switched off, so nothing reaches your customers.';
        } else if (facts.launch === 'live') {
            el('oe-site').textContent = 'Your live site at ' + (facts.url || 'this address') + '.';
        } else {
            el('oe-site').textContent = facts.url ? 'At ' + facts.url + '.' : 'At the address of this server.';
        }

        facts.acks.forEach(function (a) {
            var label = document.createElement('label');
            label.className = 'oe-row';
            var box = document.createElement('input');
            box.type = 'checkbox';
            box.value = a.key;
            box.addEventListener('change', refresh);
            var text = document.createElement('span');
            text.appendChild(node('strong', a.label));
            text.appendChild(document.createTextNode(' ' + a.message));
            label.appendChild(box);
            label.appendChild(text);
            el('oe-acks').appendChild(label);
        });

        el('oe-populated').hidden = !(facts.populated && facts.typed_required);
        el('oe-replace-populated').checked = false;
        el('oe-typed-box').hidden = !facts.typed_required;
        el('oe-typed').value = '';
        el('oe-back').hidden = facts.door !== 'installer';
        say('oe-start-error', '');
        refresh();
    }

    function start() {
        el('oe-start').disabled = true;
        say('oe-start-error', '');
        var acks = [];
        var boxes = document.querySelectorAll('#oe-acks input[type=checkbox]');
        for (var i = 0; i < boxes.length; i++) { if (boxes[i].checked) { acks.push(boxes[i].value); } }

        fetch(ctx.base + '/confirm', {
            method: 'POST',
            headers: { 'X-Migrate-Token': ctx.token, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            cache: 'no-store',
            body: JSON.stringify({ acks: acks, groups_ack: el('oe-ack-groups').checked, typed: el('oe-typed').value, replace_populated: el('oe-replace-populated').checked })
        }).then(function (r) {
            return r.json().then(function (body) { return { status: r.status, body: body }; });
        }).then(function (res) {
            if (res.status === 200) { el('oe-confirm').hidden = true; ctx.resume(); return; }
            if (res.status === 404) { say('oe-start-error', 'This move has finished or the key is no longer valid.'); return; }
            var b = res.body || {};
            say('oe-start-error', [b.message, b.fix].filter(Boolean).join(' ') || 'The server did not accept this. Nothing was changed.');
            refresh();
        }).catch(function () {
            say('oe-start-error', 'The server did not answer. Press Start import to try again.');
            refresh();
        });
    }

    el('oe-start').addEventListener('click', start);
    el('oe-ack-groups').addEventListener('change', refresh);
    el('oe-replace-populated').addEventListener('change', refresh);
    el('oe-typed').addEventListener('input', refresh);

    return {
        confirm: function (answer, context) {
            ctx = context;
            if (answer.confirm_unavailable) {
                el('oe-confirm').hidden = false;
                say('oe-problem', 'The page could not prepare the confirmation. Reload this page. If it keeps happening, finish the import from the server: php artisan oeparts:migrate:import --resume');
                el('oe-start').disabled = true;
                return;
            }
            if (!answer.confirm) { return; }
            facts = answer.confirm;
            render();
            el('oe-confirm').hidden = false;
        },
        report: function (answer) {
            if (answer && answer.report_url) {
                el('oe-report-link').href = answer.report_url;
                el('oe-report').hidden = false;
            }
        }
    };
})();
</script>

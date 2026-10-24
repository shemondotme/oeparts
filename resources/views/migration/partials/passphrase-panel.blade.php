{{--
  The passphrase panel of the runner page: shown when the run cannot go on without the package passphrase (the stored key expired,
  the installer's hand-over was refused, or the run was resumed after a restart). Plain markup and a small script, like the confirm
  panel. The passphrase is sent once, in the JSON body of POST {base}/passphrase with the run's token in the header, and the input
  is emptied after every submit. Nothing is stored in the page, the address, sessionStorage or a cookie, and the server's answers are
  written into the page with textContent only.
--}}
<section class="card oe-panel" id="oe-passphrase" hidden aria-live="polite">
    <h2>Type the passphrase</h2>
    <p class="note">This move needs the passphrase of the package to go on. It is checked on this server and not saved.</p>
    <form id="oe-pass-form" autocomplete="off">
        <p>
            <label for="oe-pass-input">Package passphrase</label><br>
            <input type="password" id="oe-pass-input" autocomplete="off" spellcheck="false" maxlength="1024" style="font: inherit; padding: .4rem .6rem; border-radius: .4rem; border: 1px solid #9aa5b1; width: 100%; box-sizing: border-box;">
        </p>
        <p class="error" id="oe-pass-error" role="alert" hidden></p>
        <p><button type="submit" id="oe-pass-button">Open package</button></p>
    </form>
</section>

<script>
(function () {
    var ctx = null;
    var wait = null;
    function el(id) { return document.getElementById(id); }
    function say(text) { var n = el('oe-pass-error'); n.textContent = text || ''; n.hidden = !text; }

    el('oe-pass-form').addEventListener('submit', function (event) {
        event.preventDefault();
        if (!ctx || el('oe-pass-button').disabled) { return; }
        var typed = el('oe-pass-input').value;
        el('oe-pass-input').value = '';
        if (typed === '') { say('Type the passphrase of the package.'); return; }
        el('oe-pass-button').disabled = true;
        say('');

        fetch(ctx.base + '/passphrase', {
            method: 'POST',
            headers: { 'X-Migrate-Token': ctx.token, 'Accept': 'application/json', 'Content-Type': 'application/json' },
            cache: 'no-store',
            body: JSON.stringify({ passphrase: typed })
        }).then(function (r) {
            return r.json().then(function (body) { return { status: r.status, body: body || {} }; });
        }).then(function (res) {
            typed = null;
            el('oe-pass-button').disabled = false;
            if (res.status === 200) { el('oe-passphrase').hidden = true; ctx.resume(); return; }
            if (res.status === 404) { say('This move has finished or the key is no longer valid.'); return; }
            if (res.status === 409) { el('oe-passphrase').hidden = true; ctx.resume(); return; }
            if (res.status === 429) {
                var seconds = Number(res.body.retry_after) || 60;
                say('Too many wrong tries. Try again in ' + seconds + ' seconds.');
                el('oe-pass-button').disabled = true;
                clearTimeout(wait);
                wait = setTimeout(function () { el('oe-pass-button').disabled = false; say(''); }, seconds * 1000);
                return;
            }
            say(res.body.message || 'The server did not accept this. Try again.');
        }).catch(function () {
            typed = null;
            el('oe-pass-button').disabled = false;
            say('The server did not answer. Try again.');
        });
    });

    window.oeRunnerPanels = window.oeRunnerPanels || {};
    window.oeRunnerPanels.passphrase = function (answer, context) {
        ctx = context;
        el('oe-passphrase').hidden = false;
    };
})();
</script>

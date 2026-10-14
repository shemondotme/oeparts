<!DOCTYPE html>
{{--
  Shown while a site import is replacing this installation. Reads NOTHING from the database, the settings or the
  cache: the database is the thing being replaced. Plain HTML with inline styles so it needs no build and no layout.
--}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $stalled ? 'Moving this site needs attention' : 'This site is being moved' }}</title>
    <style>
        :root { color-scheme: light dark; }
        body { margin: 0; min-height: 100vh; display: grid; place-items: center; font: 16px/1.6 system-ui, sans-serif; background: #f6f7f9; color: #1f2933; }
        main { max-width: 32rem; margin: 1.5rem; padding: 2rem; background: #fff; border-radius: .75rem; box-shadow: 0 1px 3px rgba(0,0,0,.12); }
        h1 { font-size: 1.35rem; margin: 0 0 .75rem; }
        p { margin: 0 0 .75rem; }
        .note { color: #52606d; font-size: .9rem; }
        @media (prefers-color-scheme: dark) { body { background: #12161b; color: #e4e7eb; } main { background: #1b2128; } .note { color: #9aa5b1; } }
    </style>
</head>
<body>
<main role="main">
    @if ($stalled)
        <h1>Moving this site needs attention</h1>
        <p>The move of this site to a new server has stopped and is waiting for the person who started it.</p>
        <p class="note">If that is you: open the page you used to start the move, or run <strong>php artisan oeparts:migrate:import --resume</strong> on the server.</p>
    @else
        <h1>This site is being moved</h1>
        <p>We are moving this site to a new server. It will be back in a few minutes. Nothing is lost.</p>
        <p class="note">Please try again shortly.</p>
    @endif
</main>
</body>
</html>

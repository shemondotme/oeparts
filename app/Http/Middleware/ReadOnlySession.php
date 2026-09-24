<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Session\NullSessionHandler;
use Illuminate\Session\Store;
use Symfony\Component\HttpFoundation\Response;

/**
 * For background, purely read-only requests (mini-cart summary/preview, OEM
 * autocomplete, the build-freshness poll): read the session (so
 * Auth::user() still resolves) but never write it back.
 *
 * Laravel's StartSession middleware re-saves the ENTIRE session blob at the
 * end of every request, even one that changed nothing. Two overlapping
 * requests on the same session are therefore last-writer-wins: a slow
 * GET /cart/summary that loaded the session before the customer's checkout
 * POST, and finished after it, silently overwrote the step the POST had just
 * saved. The customer landed back on the previous step with an empty form.
 * Confirmed live 2026-09-24 (Phase 22): ~40% of guest checkouts under a
 * robot-speed click on the dev stack, 0 of 8 once the summary call was
 * blocked — and the same window exists for any real customer who submits a
 * step while the page's own background XHR is still in flight, which widens
 * with server latency (i.e. exactly under production load).
 *
 * Swapping in a no-op handler for the save is enough: StartSession calls
 * driver()->save() on the same Store object the request holds. As a side
 * effect these requests also no longer overwrite the session's remembered
 * "previous URL" with a JSON endpoint, and no longer count as customer
 * activity for the idle timeout (a background poll isn't the customer being
 * active — before this, an open tab polling /build-version kept a logged-in
 * session alive forever, defeating the merchant's inactivity setting).
 *
 * Two safeguards: a request that legitimately REPLACED its session (an idle-
 * timeout invalidate() regenerates the id and flashes "session expired") is
 * still persisted normally, and the original handler is restored in
 * terminate() so the swap can't leak past this request in a long-lived
 * process.
 *
 * Only apply this to routes that NEVER intentionally write the session (no
 * flash data, no login, no Session::put) — anything written there is
 * discarded by design.
 */
class ReadOnlySession
{
    private const ORIGINAL_HANDLER = '_readonly_session_original_handler';

    public function handle(Request $request, Closure $next): Response
    {
        $originalId = $this->store($request)?->getId();

        $response = $next($request);

        $store = $this->store($request);

        if ($store !== null && $store->getId() === $originalId) {
            $request->attributes->set(self::ORIGINAL_HANDLER, $store->getHandler());
            $store->setHandler(new NullSessionHandler);
        }

        return $response;
    }

    public function terminate(Request $request, Response $response): void
    {
        $original = $request->attributes->get(self::ORIGINAL_HANDLER);

        if ($original !== null) {
            $this->store($request)?->setHandler($original);
        }
    }

    /** The concrete Store — the Session contract has no handler accessors. */
    private function store(Request $request): ?Store
    {
        if (! $request->hasSession()) {
            return null;
        }

        $session = $request->session();

        return $session instanceof Store ? $session : null;
    }
}

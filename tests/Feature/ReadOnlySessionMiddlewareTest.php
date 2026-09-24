<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Session\Store;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use SessionHandlerInterface;
use Tests\TestCase;

/**
 * Phase 22 (2026-09-24). Checkout progress lives in the Laravel session, and
 * StartSession re-saves the WHOLE session blob at the end of every request.
 * A slow background GET (the navbar's /cart/summary) that loaded the session
 * before a checkout POST, and finished after it, overwrote the step the POST
 * had just saved — the customer landed on the previous step with an empty
 * form. ~40% of robot-speed guest checkouts hit it; 0/8 once the call was
 * blocked. These tests reproduce the overlap deterministically: a route
 * writes "as a concurrent request" straight to the session store while it is
 * itself mid-flight, then we check whether its own end-of-request save
 * clobbered that write.
 */
class ReadOnlySessionMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private ?SessionHandlerInterface $handler = null;

    private ?string $sessionId = null;

    /** Registers a test route that, mid-request, plays the part of a faster concurrent request. */
    private function registerRoute(string $uri, array $middleware, ?callable $extra = null): void
    {
        Route::middleware($middleware)->get($uri, function (Request $request) use ($extra) {
            /** @var Store $session */
            $session = $request->session();
            $this->handler = $session->getHandler();
            $this->sessionId = $session->getId();

            // The concurrent POST finished and persisted checkout progress
            // while this request was still running on its stale copy.
            $this->handler->write($this->sessionId, serialize(['checkout_step' => 4]));

            if ($extra !== null) {
                $extra($session);
            }

            return response()->json(['ok' => true]);
        });
    }

    /** Raw stored payload — format-agnostic (the app's session store may serialize as JSON or PHP). */
    private function storedPayload(): string
    {
        return (string) $this->handler->read($this->sessionId);
    }

    #[Test]
    public function control_an_ordinary_web_route_clobbers_a_concurrent_sessions_write(): void
    {
        $this->registerRoute('/_test/rw', ['web']);

        $this->get('/_test/rw')->assertOk();

        $this->assertStringNotContainsString(
            'checkout_step',
            $this->storedPayload(),
            'control: proves this harness detects the bug — StartSession\'s end-of-request save overwrote the concurrent write'
        );
    }

    #[Test]
    public function a_read_only_route_never_overwrites_a_concurrent_sessions_write(): void
    {
        $this->registerRoute('/_test/ro', ['web', 'session.readonly']);

        $this->get('/_test/ro')->assertOk();

        $this->assertStringContainsString('checkout_step', $this->storedPayload(), 'the concurrent request\'s checkout progress must survive');
    }

    #[Test]
    public function the_original_handler_is_restored_after_the_request(): void
    {
        $this->registerRoute('/_test/ro', ['web', 'session.readonly']);

        $this->get('/_test/ro')->assertOk();

        $store = $this->app['session']->driver();
        $this->assertSame($this->handler, $store->getHandler(), 'the no-op handler must not leak past this request (long-lived processes, later requests in a test)');
    }

    #[Test]
    public function a_request_that_legitimately_replaced_its_session_is_still_persisted(): void
    {
        // e.g. EnforceCustomerSessionLifetime's idle-timeout invalidate(): new
        // session id + a "session expired" flash that the next page must see.
        $this->registerRoute('/_test/replace', ['web', 'session.readonly'], function (Store $session) {
            $session->invalidate();
            $session->flash('error', 'session expired');
        });

        $this->get('/_test/replace')->assertOk();

        $newId = $this->app['session']->driver()->getId();
        $this->assertNotSame($this->sessionId, $newId, 'test premise: the session id was regenerated');
        $this->assertNotSame('', (string) $this->handler->read($newId), 'the regenerated session must actually be saved, flash included');
    }

    #[Test]
    public function the_real_background_endpoints_are_flagged_read_only(): void
    {
        foreach (['frontend.cart.summary', 'frontend.cart.preview', 'frontend.search.autocomplete', 'build-version'] as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "route {$name} exists");
            $this->assertContains(
                'session.readonly',
                $route->gatherMiddleware(),
                "{$name} is fired automatically by page JS and must not write the session back (it can overwrite checkout progress)"
            );
        }
    }

    #[Test]
    public function the_checkout_and_cart_mutating_routes_are_not_read_only(): void
    {
        // Guard against over-application: these legitimately write the session.
        foreach (['frontend.checkout', 'frontend.checkout.store', 'frontend.cart.add', 'frontend.cart.index'] as $name) {
            $route = Route::getRoutes()->getByName($name);

            $this->assertNotNull($route, "route {$name} exists");
            $this->assertNotContains('session.readonly', $route->gatherMiddleware(), "{$name} writes session state — it must persist it");
        }
    }
}

<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\TestCase;

/**
 * Phase 17 (Error Page/Degraded-Mode UX). Zero test coverage existed anywhere
 * for the 401/403/404/419/429/500 error views before this — the same class
 * of gap that let ContactReply's HTML view silently render broken for years
 * (Phase 15): isset()/?? never throw on an undefined variable, so a wrong
 * variable name in one of these views would render "successfully" with
 * blank/wrong content and no exception, in every existing test that only
 * ever checks a status code, never the rendered body.
 */
class ErrorPagesRenderTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function an_unmatched_storefront_route_renders_the_custom_404_view(): void
    {
        // APP_DEBUG=true in .env.testing renders Laravel's debug page instead
        // of a custom error view (same as any real Laravel app) — force it
        // off here to exercise the exact path a real production visitor hits.
        config(['app.debug' => false]);

        $response = $this->get('/en/this-route-genuinely-does-not-exist-xyz');

        // Rendered through the exception handler, not a normal controller
        // return, so the response carries no ->original view instance for
        // assertViewIs() to inspect — the rendered body is the real proof.
        $response->assertStatus(404);
        $response->assertSee(__('errors.404.heading'));
        $response->assertDontSee('ErrorException');
    }

    #[Test]
    public function the_401_view_renders_with_real_content_not_a_blank_page(): void
    {
        $html = view('errors.401')->render();

        $this->assertStringContainsString('401', $html);
        $this->assertStringNotContainsString('ErrorException', $html);
    }

    #[Test]
    public function the_403_view_renders_with_real_content_not_a_blank_page(): void
    {
        $html = view('errors.403')->render();

        $this->assertStringContainsString('403', $html);
    }

    #[Test]
    public function the_419_view_renders_with_real_content_not_a_blank_page(): void
    {
        $html = view('errors.419')->render();

        $this->assertStringContainsString('419', $html);
    }

    #[Test]
    public function the_429_view_renders_with_real_content_including_the_message_variable(): void
    {
        $html = view('errors.429', ['message' => 'Slow down, tiger.'])->render();

        $this->assertStringContainsString('429', $html);
        $this->assertStringContainsString('Slow down, tiger.', $html);
    }

    #[Test]
    public function the_500_view_renders_with_real_content_not_a_blank_page(): void
    {
        $html = view('errors.500')->render();

        $this->assertStringContainsString('500', $html);
    }

    /**
     * A real, unhandled 429 (not just the isolated view render above) goes
     * through bootstrap/app.php's renderable(TooManyRequestsHttpException)
     * handler — proves the two are actually wired together end to end.
     */
    #[Test]
    public function a_real_429_exception_renders_the_custom_view_with_its_own_message(): void
    {
        Route::get('/_test-throttle-trigger', function () {
            throw new TooManyRequestsHttpException(30, 'Too many requests, custom message.');
        });

        $response = $this->get('/_test-throttle-trigger');

        $response->assertStatus(429);
        $response->assertViewIs('errors.429');
        $response->assertSee('Too many requests, custom message.');
    }
}

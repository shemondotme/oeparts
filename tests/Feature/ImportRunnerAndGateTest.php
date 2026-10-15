<?php

namespace Tests\Feature;

use App\Http\Controllers\MigrationRunController;
use App\Http\Middleware\RedirectIfNotInstalled;
use App\Services\Instance\SystemGate;
use App\Services\Migration\Import\Contracts\RunDriver;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\ImportStatus;
use App\Services\Migration\Import\StatusOnlyRunDriver;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route as RouteFacade;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * Phase 6.2: while an import runs the site answers 503 from a page that reads nothing, the health check and the
 * runner still answer, and the runner is reachable only with the run's own token. Proven BEFORE any destructive code
 * exists, with a state file written by hand.
 */
class ImportRunnerAndGateTest extends TestCase
{
    use RefreshDatabase;

    private ImportMarker $marker;

    private ImportStateStore $store;

    /** @var array<int, string> */
    private array $runs = [];

    private string $token = '';

    protected function setUp(): void
    {
        parent::setUp();

        $this->marker = new ImportMarker;
        $this->store = new ImportStateStore;
        $this->marker->remove();
        $this->token = bin2hex(random_bytes(32));
        // These tests are about the door, not the engine: the status-only driver reports the state file and does no work.
        $this->app->bind(RunDriver::class, StatusOnlyRunDriver::class);
    }

    protected function tearDown(): void
    {
        $this->marker->remove();

        foreach ($this->runs as $id) {
            exec('rm -rf '.escapeshellarg($this->store->directory($id)));
        }

        parent::tearDown();
    }

    private function run_(array $fields = []): ImportState
    {
        $state = $this->store->create('import', ['token_hash' => hash('sha256', $this->token)] + $fields);
        $this->runs[] = $state->id;

        return $state;
    }

    private function authHeaders(?string $token = null): array
    {
        return ['X-Migrate-Token' => $token ?? $this->token, 'Accept' => 'application/json'];
    }

    // ---- the gate ----------------------------------------------------------------------------------------------

    #[Test]
    public function with_no_marker_the_site_answers_as_it_always_did(): void
    {
        $this->get('/up')->assertOk();
        $this->get('/en/')->assertOk();
        $this->assertFalse($this->marker->isActive());
    }

    #[Test]
    public function with_the_marker_every_page_is_a_503_from_a_page_that_reads_nothing(): void
    {
        $this->marker->write($this->run_()->id);
        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            $queries[] = $query->sql;
        });

        foreach (['/en/', '/en/parts', '/admin', '/admin/login', '/en/account', '/this-does-not-exist'] as $path) {
            $response = $this->get($path);

            $response->assertStatus(503);
            $response->assertSee('This site is being moved', false);
            $this->assertSame('120', $response->headers->get('Retry-After'), $path);
            $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'), $path);
        }

        $this->assertSame([], $queries, 'the gate answers before anything reads the database');
    }

    #[Test]
    public function the_health_check_still_passes_so_a_load_balancer_does_not_panic(): void
    {
        $this->marker->write($this->run_()->id);

        $this->get('/up')->assertOk();
    }

    #[Test]
    public function an_api_or_json_caller_gets_json_in_the_maintenance_shape(): void
    {
        $this->marker->write($this->run_()->id);

        foreach ([$this->getJson('/api/v1/products'), $this->getJson('/en/'), $this->get('/api/v1/anything')] as $response) {
            $response->assertStatus(503);
            $response->assertJson(['success' => false, 'errors' => null]);
            $this->assertSame('120', $response->headers->get('Retry-After'));
            $this->assertStringContainsString('being moved', (string) $response->json('message'));
        }
    }

    #[Test]
    public function webhooks_and_posts_are_refused_too_so_no_order_is_taken_mid_move(): void
    {
        $this->marker->write($this->run_()->id);

        $this->post('/webhooks/airwallex', [])->assertStatus(503);
        $this->post('/webhooks/paysera', [])->assertStatus(503);
        $this->post('/en/checkout', [])->assertStatus(503);
    }

    #[Test]
    public function a_stalled_run_says_so_on_the_page(): void
    {
        $run = $this->run_();
        $this->marker->write($run->id);
        file_put_contents($this->store->directory($run->id).'/stalled.flag', '1');

        $this->get('/en/')->assertStatus(503)->assertSee('needs attention', false)->assertSee('--resume', false);
        $this->getJson('/en/')->assertStatus(503)->assertJsonPath('message', 'This site is being moved and the move needs attention. Please try again later.');
    }

    #[Test]
    public function removing_the_marker_opens_the_site_again_at_once(): void
    {
        $this->marker->write($this->run_()->id);
        $this->get('/en/')->assertStatus(503);

        $this->marker->remove();

        $this->get('/en/')->assertOk();
    }

    #[Test]
    public function while_the_marker_exists_only_the_commands_that_keep_the_site_healthy_run(): void
    {
        $gate = app(SystemGate::class);
        $this->assertTrue($gate->allowsCommand('oeparts:newsletter:send-due'));

        $this->marker->write($this->run_()->id);

        $this->assertFalse($gate->allowsCommand('oeparts:newsletter:send-due'));
        $this->assertFalse($gate->allowsCommand('sitemap:generate'));
        $this->assertTrue($gate->allowsCommand('scheduler:heartbeat'));
        $this->assertTrue($gate->allowsCommand('oeparts:backup:cleanup-stale'));
        $this->assertContains('a site import is replacing this installation', $gate->reasons());
    }

    // ---- the routes are what the plan says they are -----------------------------------------------------------------

    /** @return array<int, Route> */
    private function runnerRoutes(): array
    {
        return array_values(array_filter(
            iterator_to_array(RouteFacade::getRoutes()),
            fn (Route $route): bool => str_starts_with($route->uri(), 'migrate/run'),
        ));
    }

    #[Test]
    public function the_runner_routes_carry_no_web_group_no_csrf_and_no_installed_site_redirect(): void
    {
        $routes = $this->runnerRoutes();

        // The exact surface of the runner. A new route here is a new way in: it must be added to this list on purpose.
        $surface = array_map(static fn (Route $route): string => implode('|', array_diff($route->methods(), ['HEAD'])).' '.$route->getName(), $routes);
        sort($surface);
        $this->assertSame(['GET migrate.run.page', 'GET migrate.run.status', 'POST migrate.run.advance', 'POST migrate.run.confirm'], $surface);

        foreach ($routes as $route) {
            $resolved = app('router')->gatherRouteMiddleware($route);

            $this->assertContains('web', $route->excludedMiddleware(), $route->uri().' must have the web group stripped');
            foreach ($resolved as $middleware) {
                $this->assertFalse(is_string($middleware) && is_a($middleware, ValidateCsrfToken::class, true), $route->uri().' must not check a CSRF token (the run\'s token is its credential)');
            }
            $this->assertNotContains(VerifyCsrfToken::class, $resolved, $route->uri());
            $this->assertNotContains(RedirectIfNotInstalled::class, $resolved, $route->uri());
            $this->assertNotContains(StartSession::class, $resolved, $route->uri());
            $this->assertNotContains(EncryptCookies::class, $resolved, $route->uri());
        }
    }

    #[Test]
    public function every_runner_route_is_a_controller_action_so_route_cache_can_cache_it(): void
    {
        foreach ($this->runnerRoutes() as $route) {
            $this->assertIsString($route->getAction('uses'), $route->uri().' must not be a closure');
            $this->assertStringStartsWith(MigrationRunController::class.'@', $route->getAction('uses'));
        }
    }

    #[Test]
    public function the_runner_is_throttled(): void
    {
        foreach ($this->runnerRoutes() as $route) {
            $this->assertContains('throttle:240,1', $route->gatherMiddleware(), $route->uri());
        }
    }

    // ---- the runner's own door ---------------------------------------------------------------------------------------

    #[Test]
    public function every_failure_is_the_same_404_so_nothing_can_be_learned_by_guessing(): void
    {
        $run = $this->run_();
        $this->marker->write($run->id);
        $answers = [];

        $answers[] = $this->getJson("/migrate/run/{$run->id}/status");                                       // no token
        $answers[] = $this->getJson("/migrate/run/{$run->id}/status", $this->authHeaders('wrong'));            // wrong token
        $answers[] = $this->getJson("/migrate/run/{$run->id}/status", $this->authHeaders(str_repeat('a', 257))); // absurd token
        $answers[] = $this->getJson('/migrate/run/0123456789abcdef/status', $this->authHeaders());              // unknown run
        $answers[] = $this->postJson("/migrate/run/{$run->id}/advance", [], $this->authHeaders('wrong'));
        $answers[] = $this->postJson('/migrate/run/0123456789abcdef/advance', [], $this->authHeaders());
        $answers[] = $this->postJson("/migrate/run/{$run->id}/confirm");                                       // no token
        $answers[] = $this->postJson("/migrate/run/{$run->id}/confirm", [], $this->authHeaders('wrong'));
        $answers[] = $this->postJson('/migrate/run/0123456789abcdef/confirm', [], $this->authHeaders());

        foreach ($answers as $answer) {
            $answer->assertStatus(404);
            $answer->assertExactJson(['error' => 'token_invalid']);
        }
    }

    #[Test]
    public function the_right_token_reads_the_status_and_advances_without_a_session_or_a_csrf_token(): void
    {
        $run = $this->run_(['stage' => 'verify']);
        $this->marker->write($run->id);

        $status = $this->getJson("/migrate/run/{$run->id}/status", $this->authHeaders());
        $status->assertOk()->assertJson(['found' => true, 'status' => 'running', 'stage' => 'verify', 'terminal' => false]);

        $advance = $this->postJson("/migrate/run/{$run->id}/advance", [], $this->authHeaders());
        $advance->assertOk()->assertJson(['found' => true, 'advanced' => false]); // the status-only driver does no work

        foreach ([$status, $advance] as $response) {
            $this->assertSame([], $response->headers->getCookies(), 'no session, no cookie');
            $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        }
    }

    #[Test]
    public function the_token_is_read_from_the_header_only_never_from_the_address_the_body_or_a_cookie(): void
    {
        $run = $this->run_(['stage' => 'verify']);
        $this->marker->write($run->id);
        $everywhereElse = ['token' => $this->token, '_token' => $this->token, 't' => $this->token, 'X-Migrate-Token' => $this->token, 'x_migrate_token' => $this->token];

        // With the header each call is answered (the run is not waiting for "go", so the confirm call says 409, not 404).
        $with = [
            ['GET', 'status', 200],
            ['POST', 'advance', 200],
            ['POST', 'confirm', 409],
        ];

        foreach ($with as [$method, $action, $expected]) {
            $uri = "/migrate/run/{$run->id}/{$action}";

            $this->call($method, $uri, [], [], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_MIGRATE_TOKEN' => $this->token])->assertStatus($expected);

            // The same token anywhere else is no credential.
            $this->call($method, $uri.'?'.http_build_query($everywhereElse), [], [], [], ['HTTP_ACCEPT' => 'application/json'])->assertStatus(404)->assertExactJson(['error' => 'token_invalid']);
            $this->call($method, $uri, $everywhereElse, [], [], ['HTTP_ACCEPT' => 'application/json'])->assertStatus(404)->assertExactJson(['error' => 'token_invalid']);
            $this->call($method, $uri, [], $everywhereElse, [], ['HTTP_ACCEPT' => 'application/json'])->assertStatus(404)->assertExactJson(['error' => 'token_invalid']);
            $this->call($method, $uri, [], [], [], ['HTTP_ACCEPT' => 'application/json', 'CONTENT_TYPE' => 'application/json'], (string) json_encode($everywhereElse))->assertStatus(404)->assertExactJson(['error' => 'token_invalid']);
            $this->call($method, $uri, [], [], [], ['HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$this->token])->assertStatus(404)->assertExactJson(['error' => 'token_invalid']);
        }
    }

    #[Test]
    public function the_token_dies_with_the_run(): void
    {
        $run = $this->run_();
        $this->marker->write($run->id);
        $this->getJson("/migrate/run/{$run->id}/status", $this->authHeaders())->assertOk();

        foreach ([ImportStatus::Complete, ImportStatus::Failed, ImportStatus::RolledBack] as $terminal) {
            $this->store->update($run->id, function (ImportState $s) use ($terminal): void {
                $s->status = $terminal;
            });

            $this->getJson("/migrate/run/{$run->id}/status", $this->authHeaders())->assertStatus(404);
            $this->postJson("/migrate/run/{$run->id}/advance", [], $this->authHeaders())->assertStatus(404);
        }
    }

    #[Test]
    public function a_run_without_a_stored_token_hash_cannot_be_driven_at_all(): void
    {
        $state = $this->store->create('import');
        $this->runs[] = $state->id;

        $this->getJson("/migrate/run/{$state->id}/status", $this->authHeaders())->assertStatus(404);
    }

    #[Test]
    public function the_advance_budget_is_a_few_seconds_and_never_more_than_half_the_web_limit(): void
    {
        $recorder = new class
        {
            /** @var array<int, float> */
            public array $seen = [];
        };
        $this->app->bind(RunDriver::class, fn () => new class($recorder) implements RunDriver
        {
            public function __construct(private object $recorder) {}

            public function advance(string $runId, float $budgetSeconds): array
            {
                $this->recorder->seen[] = $budgetSeconds;

                return ['found' => true, 'status' => 'running'];
            }

            public function status(string $runId): array
            {
                return ['found' => true];
            }
        });
        $run = $this->run_();
        $this->marker->write($run->id);

        config(['migration.import.advance_budget_seconds' => 10]);
        $previous = ini_get('max_execution_time');

        try {
            ini_set('max_execution_time', '8');
            $this->postJson("/migrate/run/{$run->id}/advance", [], $this->authHeaders())->assertOk();
            ini_set('max_execution_time', '0');
            $this->postJson("/migrate/run/{$run->id}/advance", [], $this->authHeaders())->assertOk();
        } finally {
            ini_set('max_execution_time', (string) $previous);
        }

        $this->assertSame([4.0, 10.0], $recorder->seen);
    }

    // ---- the page --------------------------------------------------------------------------------------------------

    #[Test]
    public function the_page_is_static_html_that_carries_no_data_about_the_run_and_sets_no_cookie(): void
    {
        $run = $this->run_(['stage' => 'db_data', 'error' => ['code' => 'secret_internal_detail']]);
        $this->marker->write($run->id);

        $response = $this->get("/migrate/run/{$run->id}");

        $response->assertOk()->assertSee('Moving your site', false)->assertSee('X-Migrate-Token', false);
        $body = $response->getContent();
        $this->assertStringNotContainsString($this->token, $body);
        $this->assertStringNotContainsString(hash('sha256', $this->token), $body);
        $this->assertStringNotContainsString('secret_internal_detail', $body);
        $this->assertStringContainsString($run->id, $body);
        $this->assertSame([], $response->headers->getCookies());
        $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
    }

    #[Test]
    public function the_page_of_a_finished_or_unknown_run_is_a_404(): void
    {
        $run = $this->run_();
        $this->store->update($run->id, function (ImportState $s): void {
            $s->status = ImportStatus::Complete;
        });

        $this->get("/migrate/run/{$run->id}")->assertStatus(404);
        $this->get('/migrate/run/0123456789abcdef')->assertStatus(404);
    }

    #[Test]
    public function a_path_that_is_not_a_run_id_never_reaches_the_controller(): void
    {
        foreach (['/migrate/run/..', '/migrate/run/UPPERCASE123456', '/migrate/run/short', '/migrate/run/a%2Fb'] as $path) {
            $this->get($path)->assertStatus(404);
        }
    }

    // ---- an empty, uninstalled site -------------------------------------------------------------------------------

    #[Test]
    public function on_a_site_with_no_settings_table_the_runner_still_answers_with_no_redirect_and_no_error(): void
    {
        Schema::drop('settings');
        @unlink(storage_path('installed.lock'));

        $response = $this->getJson('/migrate/run/0123456789abcdef/status', $this->authHeaders());

        $response->assertStatus(404)->assertExactJson(['error' => 'token_invalid']);
        $this->assertNull($response->headers->get('Location'), 'no redirect to /install and none to a canonical host');
    }
}

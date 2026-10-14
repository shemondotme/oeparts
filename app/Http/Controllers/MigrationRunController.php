<?php

namespace App\Http\Controllers;

use App\Services\Migration\Import\Contracts\RunDriver;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStateStore;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The runner of a site import: the page a person watches and the two calls it makes.
 *
 * It is served with NO session, NO CSRF check, NO settings read and NO installed-site redirect: the database may be
 * empty, half restored or being replaced while this runs, and the page must keep working through all of it (see
 * routes/migration.php and ImportInProgressGate). It is not open to the public either. Every call needs the run's own
 * 256-bit token, which exists only in the header of the request (delivered once to the person's browser in the URL
 * fragment, which a server never sees, and kept in sessionStorage); the state file holds only its hash. Every failure,
 * whether the run is unknown, finished or the token wrong, answers the same 404, so nothing can be learned by guessing.
 */
class MigrationRunController extends Controller
{
    public function __construct(
        private readonly RunDriver $driver,
        private readonly ImportStateStore $store,
    ) {}

    /** The page. Static HTML: it carries no data about the run; its script asks the two calls below. */
    public function page(string $id): JsonResponse|Response
    {
        $state = $this->liveRun($id);

        if ($state === null) {
            return $this->notFound();
        }

        return response()->view('migration.run', ['runId' => $id])
            ->header('Cache-Control', 'no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }

    public function status(Request $request, string $id): JsonResponse|Response
    {
        if (! $this->authorised($request, $id)) {
            return $this->notFound();
        }

        return $this->json($this->driver->status($id));
    }

    public function advance(Request $request, string $id): JsonResponse|Response
    {
        if (! $this->authorised($request, $id)) {
            return $this->notFound();
        }

        return $this->json($this->driver->advance($id, $this->budget()));
    }

    /** About how long one request may work: a few seconds, and never more than half of the web server's own limit. */
    private function budget(): float
    {
        $budget = max(1.0, (float) config('migration.import.advance_budget_seconds', 10));
        $limit = (int) ini_get('max_execution_time');

        return $limit > 0 ? min($budget, max(1.0, $limit / 2)) : $budget;
    }

    private function authorised(Request $request, string $id): bool
    {
        $token = (string) $request->header('X-Migrate-Token', '');

        if ($token === '' || strlen($token) > 256 || ! ImportStateStore::validId($id)) {
            return false;
        }

        $state = $this->liveRun($id);

        return $state !== null && $state->tokenHash !== null && hash_equals($state->tokenHash, hash('sha256', $token));
    }

    /** The run, only while it is not finished: the token dies with the run. */
    private function liveRun(string $id): ?ImportState
    {
        if (! ImportStateStore::validId($id)) {
            return null;
        }

        try {
            $state = $this->store->find($id);
        } catch (\RuntimeException) {
            return null;
        }

        return $state !== null && ! $state->status->isTerminal() ? $state : null;
    }

    /** @param  array<string, mixed>  $document */
    private function json(array $document): JsonResponse
    {
        return response()->json($document)->header('Cache-Control', 'no-store');
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['error' => 'token_invalid'], 404)->header('Cache-Control', 'no-store');
    }
}

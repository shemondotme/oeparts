<?php

namespace App\Http\Controllers;

use App\Services\Migration\Import\Contracts\RunDriver;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStateStore;
use App\Services\Migration\Import\RunConfirmation;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Ux\MigrationText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;

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
        private readonly RunConfirmation $confirmation,
        private readonly MigrationText $text,
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

        return $this->json($this->withExtras($this->driver->status($id), $id));
    }

    public function advance(Request $request, string $id): JsonResponse|Response
    {
        if (! $this->authorised($request, $id)) {
            return $this->notFound();
        }

        return $this->json($this->withExtras($this->driver->advance($id, $this->budget()), $id));
    }

    /**
     * "Go": the one human act between the checks and the changes. The run's token (in the header, never the address) is the only
     * credential. What the request carries is the person's answers (their ticks, the typed word); nothing in it can name a group,
     * a door or any other option of the run. ImportManager::confirm() is the judge of whether the import may proceed.
     */
    public function confirm(Request $request, string $id): JsonResponse|Response
    {
        if (! $this->authorised($request, $id)) {
            return $this->notFound();
        }

        try {
            $refusal = $this->confirmation->confirm($id, [
                'acks' => $request->input('acks'),
                'groups_ack' => $request->input('groups_ack') === true || $request->input('groups_ack') === 'true',
                'typed' => $request->input('typed'),
                'replace_populated' => $request->input('replace_populated') === true || $request->input('replace_populated') === 'true',
            ]);
        } catch (MigrationException $e) {
            return $this->json(['error' => $e->errorCode->value] + $this->text->forException($e, true), $e->errorCode === MigrationErrorCode::NotReadyToConfirm ? 409 : 422);
        }

        if ($refusal !== null) {
            return $this->json($refusal, 422);
        }

        return $this->json($this->withExtras($this->driver->status($id), $id));
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

    /**
     * What the page needs besides the engine's progress: the facts of the confirm panel while the run waits for "go", and, once the
     * run has ended on the installer's door, the signed link (two hours) to its report. Counts and names only (see RunConfirmation
     * and the installer's report page); never a value, a key or a path.
     *
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private function withExtras(array $document, string $id): array
    {
        try {
            $state = $this->store->find($id);

            if ($state === null) {
                return $document;
            }

            $facts = $this->confirmation->facts($state);

            if ($facts !== null) {
                $document['confirm'] = $facts;
            }

            if ($state->status->isTerminal() && ($state->options['door'] ?? '') === 'installer') {
                $document['report_url'] = URL::temporarySignedRoute('installer.import.report', now()->addHours(2), ['id' => $id]);
            }
        } catch (\Throwable $e) {
            // The panel is a convenience around a run that works without it; say that it could not be built, never hide it.
            Log::channel('migration')->warning("The runner's extras for import {$id} could not be built: ".$e::class);
            $document['confirm_unavailable'] = true;
        }

        return $document;
    }

    /** @param  array<string, mixed>  $document */
    private function json(array $document, int $status = 200): JsonResponse
    {
        return response()->json($document, $status)->header('Cache-Control', 'no-store');
    }

    private function notFound(): JsonResponse
    {
        return response()->json(['error' => 'token_invalid'], 404)->header('Cache-Control', 'no-store');
    }
}

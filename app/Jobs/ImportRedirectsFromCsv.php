<?php

namespace App\Jobs;

use App\Enums\RedirectType;
use App\Models\Redirect;
use App\Services\RedirectPattern;
use App\Services\RedirectLoopDetector;
use App\Support\AdminNotifier;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Queued the same way RegenerateSitemap/PushIndexNow are — CSV parsing +
 * per-row loop/duplicate validation for what could be a few thousand rows
 * is enough to risk a web request's timeout, and this app's other "admin
 * clicks a button, slow work happens off-request" actions already
 * established this exact pattern (bell notification on completion, never
 * email — this is a routine, re-runnable action).
 *
 * The redirects table is nowhere near catalog scale (unlike ProductImport's
 * resumable chunked-FSM machinery, built for ~1M-row product imports) — a
 * single pass with no resumability is the right amount of infrastructure
 * here.
 */
class ImportRedirectsFromCsv implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 300;

    public function __construct(
        public string $storedPath,
        public string $triggeredBy,
        public bool $overwriteExisting = false,
    ) {
        $this->onQueue('default');
    }

    public function handle(RedirectLoopDetector $loopDetector): void
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $skipReasons = [];

        $stream = Storage::disk('local')->readStream($this->storedPath);

        if ($stream === null || $stream === false) {
            $this->notifyResult(0, 0, 0, ['Could not read the uploaded file.']);

            return;
        }

        try {
            $header = fgetcsv($stream);
            if ($header === false) {
                $this->notifyResult(0, 0, 0, ['The file is empty.']);

                return;
            }

            // Strip a UTF-8 BOM from the first header cell. fgetcsv() does
            // not strip it, and Excel's "CSV UTF-8" export — a completely
            // normal way for an admin to have edited a round-tripped export
            // before re-uploading it — always prepends one. Left in place,
            // it silently breaks matching the very first column (from_url)
            // even though the file is otherwise entirely valid, producing a
            // confusing "Missing required columns" error for a file that
            // opens and looks correct in every spreadsheet program.
            if (isset($header[0])) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
            }

            // Case/whitespace-tolerant header matching — the exact column
            // names AdminUi::exportCsvBulkAction() produces for Redirects
            // ("From URL", "To URL", "Type", "Active"), so a round-tripped
            // export imports back in without the admin renaming columns.
            $columnIndex = [];
            foreach ($header as $i => $name) {
                $columnIndex[strtolower(trim((string) $name))] = $i;
            }
            $fromIdx = $columnIndex['from_url'] ?? $columnIndex['from url'] ?? null;
            $toIdx = $columnIndex['to_url'] ?? $columnIndex['to url'] ?? null;
            $typeIdx = $columnIndex['type'] ?? null;
            $matchIdx = $columnIndex['match_type'] ?? $columnIndex['match'] ?? null;
            $activeIdx = $columnIndex['is_active'] ?? $columnIndex['active'] ?? null;
            $groupIdx = $columnIndex['group'] ?? $columnIndex['group_name'] ?? null;
            $expiresIdx = $columnIndex['expires_at'] ?? $columnIndex['expires'] ?? null;

            if ($fromIdx === null || $toIdx === null) {
                $this->notifyResult(0, 0, 0, ['Missing required columns: from_url and to_url.']);

                return;
            }

            $rowNumber = 1;
            while (($row = fgetcsv($stream)) !== false) {
                $rowNumber++;

                $fromRaw = trim((string) ($row[$fromIdx] ?? ''));
                $toRaw = trim((string) ($row[$toIdx] ?? ''));

                $typeRaw = $typeIdx !== null ? trim((string) ($row[$typeIdx] ?? '')) : RedirectType::Permanent->value;
                $type = RedirectType::tryFrom($typeRaw) ?? RedirectType::Permanent;

                // A 410 (Gone) rule has no destination; every other type needs one.
                if ($fromRaw === '' || ($toRaw === '' && $type !== RedirectType::Gone)) {
                    $skipped++;
                    $skipReasons[] = $type === RedirectType::Gone
                        ? "row {$rowNumber}: missing from_url"
                        : "row {$rowNumber}: missing from_url or to_url";

                    continue;
                }

                $matchRaw = $matchIdx !== null ? strtolower(trim((string) ($row[$matchIdx] ?? ''))) : '';
                $matchType = $matchRaw === '' ? Redirect::MATCH_EXACT : $matchRaw;
                if (! in_array($matchType, [Redirect::MATCH_EXACT, Redirect::MATCH_WILDCARD, Redirect::MATCH_REGEX], true)) {
                    $skipped++;
                    $skipReasons[] = "row {$rowNumber} ({$fromRaw}): unknown match type \"{$matchRaw}\" (use exact, wildcard or regex)";

                    continue;
                }

                $isActive = $activeIdx === null || in_array(strtolower(trim((string) ($row[$activeIdx] ?? '1'))), ['1', 'true', 'yes'], true);

                $group = $groupIdx !== null ? Str::limit(trim((string) ($row[$groupIdx] ?? '')), 100, '') : '';
                $expiresRaw = $expiresIdx !== null ? trim((string) ($row[$expiresIdx] ?? '')) : '';
                $expiresAt = null;
                if ($expiresRaw !== '') {
                    try {
                        $expiresAt = Carbon::parse($expiresRaw);
                    } catch (Throwable) {
                        $skipped++;
                        $skipReasons[] = "row {$rowNumber} ({$fromRaw}): \"{$expiresRaw}\" is not a valid expiry date (use e.g. 2027-01-31 or 2027-01-31 18:00)";

                        continue;
                    }
                }

                $result = $this->tryImportRow($fromRaw, $toRaw, $type, $isActive, $loopDetector, $matchType, $group !== '' ? $group : null, $expiresAt);

                if ($result['action'] === 'skipped') {
                    $skipped++;
                    $skipReasons[] = "row {$rowNumber} ({$fromRaw}): {$result['reason']}";

                    continue;
                }

                $result['action'] === 'updated' ? $updated++ : $created++;
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
            Storage::disk('local')->delete($this->storedPath);
        }

        $this->notifyResult($created, $updated, $skipped, $skipReasons);
    }

    /**
     * Same self-redirect / reverse-pair / full-chain-loop checks
     * RedirectResource's own form and NotFoundLogResource's quick-fix
     * action already enforce — kept as its own copy here (not extracted
     * into a shared service) rather than risk refactoring two already-
     * shipped, individually-tested call sites under this change.
     *
     * When a row's from_url already exists: overwriteExisting off (the
     * default) skips it, same as before this option existed — untouched
     * data is the safe default. On, it updates that existing redirect's
     * to_url/type/is_active in place instead of leaving it alone, the
     * same "create vs update on a matching row" choice the bulk Product
     * importer already offers. Either way, the existing row itself is
     * excluded from its own loop/reverse-pair check (editing a redirect
     * without changing it must never flag itself as looping into itself).
     *
     * @return array{action: 'created'|'updated'|'skipped', reason?: string}
     */
    private function tryImportRow(string $fromRaw, string $toRaw, RedirectType $type, bool $isActive, RedirectLoopDetector $loopDetector, string $matchType = Redirect::MATCH_EXACT, ?string $group = null, ?Carbon $expiresAt = null): array
    {
        $isPattern = $matchType !== Redirect::MATCH_EXACT;
        $isGone = $type === RedirectType::Gone;

        // The stored form of from_url — exactly what the model's own normalisation produces
        // (a regex is kept as written; exact and wildcard are lower-cased and trimmed of slashes).
        $from = $matchType === Redirect::MATCH_REGEX ? trim($fromRaw) : strtolower(trim($fromRaw, '/'));
        $to = strtolower(trim($toRaw, '/'));

        // A pattern must be one the engine can actually compile, and must not redirect to a URL it matches again.
        if ($isPattern) {
            if (RedirectPattern::compile($matchType, $fromRaw) === null) {
                return ['action' => 'skipped', 'reason' => "not a valid {$matchType} pattern"];
            }

            if (! $isGone && RedirectPattern::redirectsToItself($matchType, $fromRaw, $toRaw)) {
                return ['action' => 'skipped', 'reason' => 'the destination is matched by this same rule (an endless loop)'];
            }
        }

        $existing = Redirect::where('from_url', $from)->first();

        if ($existing && ! $this->overwriteExisting) {
            return ['action' => 'skipped', 'reason' => 'a redirect for this path already exists'];
        }

        // Single-URL loop checks compare two specific addresses, so they only apply to an exact rule that has a destination.
        if (! $isPattern && ! $isGone) {
            if ($from !== '' && $from === $to) {
                return ['action' => 'skipped', 'reason' => 'destination is the same as the source'];
            }

            $reverseExists = Redirect::query()
                ->where('is_active', true)
                ->where('from_url', $to)
                ->where('to_url', $from)
                ->when($existing, fn ($q) => $q->whereKeyNot($existing->id))
                ->exists();

            if ($reverseExists) {
                return ['action' => 'skipped', 'reason' => 'an active redirect already sends this destination back to the source'];
            }

            $loopNode = $loopDetector->findLoop($from, $to, $existing?->id);

            if ($loopNode !== null) {
                return ['action' => 'skipped', 'reason' => "the chain eventually comes back to \"{$loopNode}\""];
            }
        }

        $values = [
            'to_url' => $isGone ? '' : $toRaw,
            'match_type' => $matchType,
            'type' => $type,
            'is_active' => $isActive,
            'group_name' => $group,
            'expires_at' => $expiresAt,
        ];

        if ($existing) {
            $existing->update($values);

            return ['action' => 'updated'];
        }

        Redirect::create(['from_url' => $fromRaw] + $values);

        return ['action' => 'created'];
    }

    private function notifyResult(int $created, int $updated, int $skipped, array $skipReasons): void
    {
        try {
            $body = "Created {$created}, updated {$updated}, skipped {$skipped} redirect(s), requested by {$this->triggeredBy}.";
            if ($skipReasons !== []) {
                // Capped so a huge bad file doesn't produce an unreadable wall of text.
                $body .= "\n".implode("\n", array_slice($skipReasons, 0, 10));
                if (count($skipReasons) > 10) {
                    $body .= "\n…and ".(count($skipReasons) - 10).' more.';
                }
            }

            AdminNotifier::toRoles(
                ['super_admin', 'admin'],
                Notification::make()
                    ->title('Redirect CSV import finished')
                    ->viewData(['push_topic' => 'task_completed'])
                    ->body($body)
                    ->icon('heroicon-o-arrow-up-tray')
                    ->iconColor($skipped > 0 && $created === 0 && $updated === 0 ? 'danger' : 'success')
            );
        } catch (Throwable $e) {
            // A bell notification must never fail a job that otherwise succeeded.
            Log::warning('Failed to send redirect-import completion notification', ['error' => $e->getMessage()]);
        }
    }

    public function failed(Throwable $e): void
    {
        try {
            Storage::disk('local')->delete($this->storedPath);
        } catch (Throwable) {
            // Cleanup must never mask the original failure.
        }

        try {
            AdminNotifier::toRoles(
                ['super_admin', 'admin'],
                Notification::make()
                    ->title('Redirect CSV import failed')
                    ->viewData(['push_topic' => 'task_failed'])
                    ->body($e->getMessage())
                    ->icon('heroicon-o-arrow-up-tray')
                    ->iconColor('danger')
            );
        } catch (Throwable $inner) {
            // A bell notification must never mask the original failure.
        }
    }
}

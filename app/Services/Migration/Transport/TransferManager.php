<?php

namespace App\Services\Migration\Transport;

use App\Services\Migration\Export\MigrationWorkerProbe;
use App\Services\Migration\Import\ImportInbox;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\MigrationPaths;
use App\Services\Migration\Package\PackageFormat;
use App\Support\EncryptedSettingValue;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Number;

/**
 * Brings a package from a link into the inbox, in steps that can stop anywhere and carry on:
 *
 *   queued -> resolving -> downloading -> verifying -> ready      (+ paused after a failure that can pass; failed; cancelled)
 *
 * A step is ONE budgeted advance: the runner page, the command line and the queue job each call advance() and it works until
 * the budget is spent, saving as it goes. How much has arrived is the real size of the .part file, never a stored counter, so
 * a killed process loses nothing. Every request goes through SafeHttpFetcher (the only outbound path); the file lands in the
 * inbox by an atomic rename under a name reserved first, and only after its checksum (when one was given) matched.
 *
 * A transfer is driven by a queue worker only when one is PROVEN to serve the migration queue; otherwise whoever asks
 * (a poll, the command line) drives it, one budgeted advance per call.
 */
class TransferManager
{
    public function __construct(
        private readonly TransferStore $store,
        private readonly UrlPolicy $policy,
        private readonly SafeHttpFetcher $fetcher,
        private readonly TransferLimits $limits,
        private readonly ImportInbox $inbox,
    ) {}

    public function store(): TransferStore
    {
        return $this->store;
    }

    // ---- starting ------------------------------------------------------------------------------------------------

    /**
     * Register a download. The link may be a share string (`https://host/_pkg/<token>#sha256=..&size=..&name=..`): the part
     * after # never goes to the server, it only tells this side what to expect.
     *
     * @throws MigrationException url_not_https, url_blocked, package_hash_mismatch (a checksum that is not 64 hex characters)
     */
    public function start(string $link, ?string $sha256 = null, ?string $name = null, string $initiator = 'cli'): TransferState
    {
        $parsed = ShareString::parse($link);
        $url = $parsed['url'];

        $target = $this->policy->validate($url); // fail now, before anything is saved

        $expected = $sha256 ?? $parsed['sha256'];

        if ($expected !== null && preg_match('/^[a-f0-9]{64}$/i', $expected) !== 1) {
            throw new MigrationException(MigrationErrorCode::PackageHashMismatch, ['reason' => 'the checksum must be 64 hexadecimal characters']);
        }

        return $this->store->create([
            'url' => Crypt::encryptString($target->url),
            'host' => $target->host,
            'name' => $this->fileName($name ?? $parsed['name'], $target->pathAndQuery),
            'expected_sha256' => $expected === null ? null : strtolower($expected),
            'expected_size' => $parsed['size'],
            'initiator' => $initiator,
            'message' => 'Waiting to start.',
        ]);
    }

    /** A usable inbox name: the one asked for, else the one in the address, else a made-up one. */
    private function fileName(?string $wanted, string $pathAndQuery): string
    {
        foreach ([$wanted, basename((string) parse_url($pathAndQuery, PHP_URL_PATH))] as $candidate) {
            if (is_string($candidate) && ImportInbox::isPackageName($candidate)) {
                return $candidate;
            }
        }

        return 'fetched-'.Carbon::now()->format('Ymd-His').'-'.bin2hex(random_bytes(3)).'.oeparts';
    }

    // ---- driving -------------------------------------------------------------------------------------------------

    /** Start a queue job for this transfer when a worker is proven to serve the migration queue; false = drive it by calls. */
    public function kick(string $id): bool
    {
        if (config('queue.default') === 'sync' || ! app(MigrationWorkerProbe::class)->isFresh()) {
            return false;
        }

        RunPackageTransferJob::dispatch($id);

        return true;
    }

    /**
     * Work on the transfer until the budget is spent, it pauses, or it ends. Returns at once when another process is
     * already driving it.
     */
    public function advance(string $id, float $budgetSeconds): TransferState
    {
        $handle = $this->store->lock($id);

        if ($handle === null) {
            return $this->require($id);
        }

        try {
            return $this->advanceLocked($id, $budgetSeconds);
        } finally {
            $this->store->unlock($handle);
        }
    }

    private function advanceLocked(string $id, float $budgetSeconds): TransferState
    {
        $deadline = microtime(true) + max(0.0, $budgetSeconds);

        do {
            $state = $this->require($id);

            if ($state->status->isTerminal()) {
                return $state;
            }

            if ($state->status === TransferStatus::Paused) {
                if ($state->nextAttemptAt !== null && Carbon::now()->lt(Carbon::parse($state->nextAttemptAt))) {
                    return $state; // still waiting out the pause
                }

                $state = $this->store->update($id, static function (TransferState $s): void {
                    $s->status = TransferStatus::Downloading;
                });
            }

            try {
                $state = match ($state->status) {
                    TransferStatus::Queued, TransferStatus::Resolving => $this->resolve($state),
                    TransferStatus::Downloading => $this->download($state, $deadline),
                    TransferStatus::Verifying => $this->verify($state, $deadline),
                    default => $state,
                };
            } catch (MigrationException $e) {
                $state = $this->failedStep($state, $e);
            } catch (\Throwable $e) {
                Log::error('migration: a transfer step crashed', ['transfer' => $id, 'error' => $e->getMessage()]);
                $state = $this->fail($state, new MigrationException(MigrationErrorCode::UrlHttpError, ['status' => 0, 'reason' => 'internal'], $e));
            }
        } while (! $state->status->isTerminal() && $state->status !== TransferStatus::Paused && microtime(true) < $deadline);

        return $state;
    }

    private function resolve(TransferState $state): TransferState
    {
        // the address is judged again right before the first request (a link can sit queued for a while)
        $this->policy->validate($this->url($state));

        return $this->store->update($state->id, static function (TransferState $s): void {
            $s->status = TransferStatus::Downloading;
            $s->message = 'Starting the download.';
        });
    }

    private function download(TransferState $state, float $deadline): TransferState
    {
        $part = $this->store->partPath($state->id);
        $offset = is_file($part) ? (int) filesize($part) : 0;

        if ($state->total !== null && $offset > $state->total) {
            // more on disk than the file holds: the tail is junk from an interrupted write
            $this->truncate($part, $state->total);
            $offset = $state->total;
        }

        if ($state->total !== null && $offset === $state->total) {
            return $this->toVerifying($state);
        }

        $remaining = max(0.0, $deadline - microtime(true));
        $length = $this->chunkLength($state, $remaining);

        if ($state->total !== null) {
            $length = min($length, $state->total - $offset);
        }

        MigrationPaths::ensureDirectory(dirname($part));
        $sink = fopen($part, 'ab');

        if ($sink === false) {
            throw new MigrationException(MigrationErrorCode::PreflightNotWritable, ['path' => 'the transfer folder']);
        }

        $started = microtime(true);
        // An unreadable claim (APP_KEY changed) is "no claim": the request goes out without the header, never with ciphertext.
        $revealed = $state->claimEncrypted === null ? null : EncryptedSettingValue::reveal($state->claimEncrypted, 'transfer claim', true);
        $claim = $revealed === null || ! $revealed->ok || $revealed->value === '' ? null : $revealed->value;

        try {
            $response = $this->fetcher->fetchRange($this->url($state), $sink, $offset, $length, [
                'headers' => $claim === null ? [] : ['X-OeParts-Claim' => $claim],
                'timeout' => (int) min(300, max(15, $remaining + 5)),
                'on_headers' => function (FetchHeaders $h) use ($state, $offset): void {
                    $this->acceptHeaders($state, $h, $offset);
                },
            ]);
        } finally {
            fflush($sink);
            fclose($sink);
        }

        $elapsed = max(0.001, microtime(true) - $started);

        if ($response->isRangeNotSatisfiable()) {
            return $this->afterNotSatisfiable($state, $offset, $response->total);
        }

        $size = (int) filesize($part);

        if ($offset === 0 && $response->bytesWritten > 0) {
            $this->assertMagic($part);
        }

        $total = $state->total ?? $response->total;
        $finished = ($total !== null && $size >= $total) || ($total === null && $response->bytesWritten < $length);
        $rate = $response->bytesWritten > 0 ? (int) ($response->bytesWritten / $elapsed) : $state->bytesPerSecond;

        $state = $this->store->update($state->id, function (TransferState $s) use ($total, $rate, $size): void {
            $s->total ??= $total;
            $s->bytesPerSecond = $rate;
            $s->attempts = 0;
            $s->nextAttemptAt = null;
            $s->error = null;
            $s->message = $s->total !== null && $s->total > 0
                ? sprintf('Downloading: %d%% (%s of %s).', min(100, intdiv($size * 100, $s->total)), Number::fileSize($size), Number::fileSize($s->total))
                : 'Downloading: '.Number::fileSize($size).'.';
        });

        return $finished ? $this->toVerifying($state) : $state;
    }

    /** Checked on the answer's headers, before the first body byte: the claim is saved, the size and the disk are judged. */
    private function acceptHeaders(TransferState $state, FetchHeaders $h, int $offset): void
    {
        $claim = $h->header('x-oeparts-claim');
        $total = $h->total;

        if ($total !== null) {
            if ($total > $this->limits->maxBytes()) {
                throw new MigrationException(MigrationErrorCode::UrlTooLarge, ['reason' => 'larger than the allowed '.Number::fileSize($this->limits->maxBytes())]);
            }

            if ($state->expectedSize !== null && $state->expectedSize !== $total) {
                throw new MigrationException(MigrationErrorCode::PackageCorrupt, ['reason' => 'the size differs from the one the link announced']);
            }

            $free = $this->limits->freeBytes();
            $needed = $this->limits->neededBytes($total - $offset);

            if ($free !== null && $free < $needed) {
                throw new MigrationException(MigrationErrorCode::PreflightDisk, ['needed' => Number::fileSize($needed), 'free' => Number::fileSize($free)]);
            }
        }

        if ($claim !== null || $total !== null) {
            $this->store->update($state->id, static function (TransferState $s) use ($claim, $total): void {
                if ($claim !== null && $s->claimEncrypted === null) {
                    $s->claimEncrypted = Crypt::encryptString($claim);
                }

                $s->total ??= $total;
            });
        }
    }

    private function afterNotSatisfiable(TransferState $state, int $offset, ?int $total): TransferState
    {
        if ($total !== null && $offset === $total) {
            return $this->toVerifying($this->store->update($state->id, static function (TransferState $s) use ($total): void {
                $s->total = $total;
            }));
        }

        if ($total !== null && $offset > $total) {
            $this->truncate($this->store->partPath($state->id), $total);

            return $this->store->update($state->id, static function (TransferState $s) use ($total): void {
                $s->total = $total;
            });
        }

        throw new MigrationException(MigrationErrorCode::UrlHttpError, ['status' => 416, 'reason' => 'range_not_satisfiable']);
    }

    /** How many bytes the next request asks for: the configured size, less when the link is slow and the budget short. */
    private function chunkLength(TransferState $state, float $remainingSeconds): int
    {
        $max = $this->limits->rangeBytes();

        if ($remainingSeconds >= 120.0) {
            return $max;
        }

        $rate = max(256 * 1024, (int) ($state->bytesPerSecond ?? 1024 * 1024));

        return (int) max(min(256 * 1024, $max), min($max, $rate * max(1.0, $remainingSeconds - 2.0)));
    }

    private function assertMagic(string $part): void
    {
        $handle = fopen($part, 'rb');
        $head = $handle === false ? '' : (string) fread($handle, strlen(PackageFormat::MAGIC));

        if ($handle !== false) {
            fclose($handle);
        }

        if ($head !== PackageFormat::MAGIC) {
            throw new MigrationException(MigrationErrorCode::PackageNotOeparts); // a login page behind a redirect, for one
        }
    }

    private function truncate(string $path, int $size): void
    {
        $handle = fopen($path, 'r+b');

        if ($handle !== false) {
            ftruncate($handle, $size);
            fclose($handle);
        }
    }

    private function toVerifying(TransferState $state): TransferState
    {
        return $this->store->update($state->id, static function (TransferState $s): void {
            $s->status = TransferStatus::Verifying;
            $s->message = 'Checking the file.';
        });
    }

    // ---- verifying and landing -------------------------------------------------------------------------------------

    private function verify(TransferState $state, float $deadline): TransferState
    {
        $part = $this->store->partPath($state->id);

        if (! is_file($part)) {
            throw new MigrationException(MigrationErrorCode::PackageNotFound, ['path' => 'the downloaded file']);
        }

        if ($state->expectedSha256 === null) {
            return $this->land($state); // nothing to compare with: the import itself checks the package end to end
        }

        $handle = fopen($part, 'rb');

        if ($handle === false) {
            throw new MigrationException(MigrationErrorCode::PackageNotFound, ['path' => 'the downloaded file']);
        }

        $context = $state->hashContext !== null ? unserialize((string) base64_decode($state->hashContext, true), ['allowed_classes' => [\HashContext::class]]) : hash_init('sha256');
        $offset = $state->hashContext !== null ? $state->hashOffset : 0;
        $sliceEnd = $offset + $this->limits->verifySliceBytes();
        $size = (int) filesize($part);

        try {
            fseek($handle, $offset);

            // at least one block per call, so a call with no budget still moves on
            do {
                $block = fread($handle, min(4 * 1024 * 1024, $this->limits->verifySliceBytes()));

                if ($block === false || $block === '') {
                    break;
                }

                hash_update($context, $block);
                $offset += strlen($block);
            } while ($offset < $size && $offset < $sliceEnd && microtime(true) < $deadline);
        } finally {
            fclose($handle);
        }

        if ($offset < $size) {
            return $this->store->update($state->id, static function (TransferState $s) use ($context, $offset, $size): void {
                $s->hashContext = base64_encode(serialize($context));
                $s->hashOffset = $offset;
                $s->message = sprintf('Checking the file: %d%%.', intdiv($offset * 100, max(1, $size)));
            });
        }

        if (! hash_equals($state->expectedSha256, hash_final($context))) {
            $this->store->discardPart($state->id);

            throw new MigrationException(MigrationErrorCode::PackageHashMismatch, ['reason' => 'a link can be used once: ask for a new one']);
        }

        return $this->land($state);
    }

    /** Make the file durable and move it into the inbox under a name that was reserved first (nothing is overwritten). */
    private function land(TransferState $state): TransferState
    {
        $part = $this->store->partPath($state->id);
        $handle = fopen($part, 'r+b');

        if ($handle !== false) {
            fflush($handle);
            if (function_exists('fsync')) {
                @fsync($handle);
            }
            fclose($handle);
        }

        $directory = $this->inbox->directory();
        MigrationPaths::ensureDirectory($directory);

        $stem = substr($state->name, 0, -strlen('.oeparts'));
        $final = null;

        for ($n = 0; $n < 100 && $final === null; $n++) {
            $name = $n === 0 ? $state->name : $stem.'-'.$n.'.oeparts';

            if (! ImportInbox::isPackageName($name)) {
                continue;
            }

            $reserved = @fopen($directory.DIRECTORY_SEPARATOR.$name, 'x');

            if ($reserved !== false) {
                fclose($reserved);
                $final = $name;
            }
        }

        if ($final === null || ! @rename($part, $directory.DIRECTORY_SEPARATOR.$final)) {
            if ($final !== null) {
                @unlink($directory.DIRECTORY_SEPARATOR.$final);
            }

            throw new MigrationException(MigrationErrorCode::PreflightNotWritable, ['path' => 'the import folder']);
        }

        return $this->store->update($state->id, static function (TransferState $s) use ($final): void {
            $s->status = TransferStatus::Ready;
            $s->finalName = $final;
            $s->finishedAt = Carbon::now()->toIso8601String();
            $s->claimEncrypted = null;
            $s->hashContext = null;
            $s->nextAttemptAt = null;
            $s->error = null;
            $s->message = 'The package is in the import folder: '.$final;
        });
    }

    // ---- failing, pausing, cancelling ------------------------------------------------------------------------------

    private function failedStep(TransferState $state, MigrationException $e): TransferState
    {
        $state = $this->require($state->id);

        if (! SafeHttpFetcher::isRetryable($e)) {
            return $this->fail($state, $e);
        }

        $attempts = $state->attempts + 1;

        if ($attempts >= $this->limits->maxAttempts()) {
            return $this->fail($state, $e);
        }

        $wait = max($this->limits->backoffSeconds($attempts), min(600, (int) ($e->params['retry_after'] ?? 0)));

        return $this->store->update($state->id, function (TransferState $s) use ($attempts, $wait, $e): void {
            $s->status = TransferStatus::Paused;
            $s->attempts = $attempts;
            $s->nextAttemptAt = Carbon::now()->addSeconds($wait)->toIso8601String();
            $s->error = ['code' => $e->errorCode->value, 'params' => $e->params];
            $s->message = "Trying again in {$wait} seconds (attempt {$attempts} of ".$this->limits->maxAttempts().').';
        });
    }

    private function fail(TransferState $state, MigrationException $e): TransferState
    {
        Log::warning('migration: a transfer failed', ['transfer' => $state->id, 'code' => $e->errorCode->value, 'detail' => $e->getMessage()]);
        $this->store->discardPart($state->id);

        return $this->store->update($state->id, static function (TransferState $s) use ($e): void {
            $s->status = TransferStatus::Failed;
            $s->finishedAt = Carbon::now()->toIso8601String();
            $s->claimEncrypted = null;
            $s->hashContext = null;
            $s->error = ['code' => $e->errorCode->value, 'params' => $e->params];
            $s->message = 'The download failed.';
        });
    }

    public function cancel(string $id): TransferState
    {
        $state = $this->require($id);

        if ($state->status->isTerminal()) {
            return $state;
        }

        $this->store->discardPart($id);

        return $this->store->update($id, static function (TransferState $s): void {
            $s->status = TransferStatus::Cancelled;
            $s->finishedAt = Carbon::now()->toIso8601String();
            $s->claimEncrypted = null;
            $s->hashContext = null;
            $s->message = 'Cancelled.';
        });
    }

    // ---- reading ---------------------------------------------------------------------------------------------------

    public function find(string $id): ?TransferState
    {
        return $this->store->find($id);
    }

    public function require(string $id): TransferState
    {
        return $this->store->find($id) ?? throw new MigrationException(MigrationErrorCode::PackageNotFound, ['path' => 'transfer '.$id]);
    }

    /**
     * What a screen or the command line shows for a transfer.
     *
     * @return array<string, mixed>
     */
    public function progress(TransferState $state): array
    {
        $part = $this->store->partPath($state->id);
        $bytes = $state->status === TransferStatus::Ready ? ($state->total ?? 0) : (is_file($part) ? (int) filesize($part) : 0);

        return [
            'id' => $state->id,
            'status' => $state->status->value,
            'host' => $state->host,
            'name' => $state->finalName ?? $state->name,
            'bytes' => $bytes,
            'total' => $state->total,
            'percent' => $state->total !== null && $state->total > 0 ? min(100, intdiv($bytes * 100, $state->total)) : null,
            'attempts' => $state->attempts,
            'next_attempt_at' => $state->nextAttemptAt,
            'message' => $state->message,
            'error' => $state->error,
            'done' => $state->status->isTerminal(),
        ];
    }

    private function url(TransferState $state): string
    {
        $revealed = EncryptedSettingValue::reveal($state->urlEncrypted, 'transfer url', true);

        if (! $revealed->ok || $revealed->value === '') {
            throw new MigrationException(MigrationErrorCode::KeyEscrowLost);
        }

        return $revealed->value;
    }
}

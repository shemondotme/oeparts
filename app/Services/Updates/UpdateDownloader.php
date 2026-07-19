<?php

namespace App\Services\Updates;

use App\Services\Updates\Exceptions\UpdateException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * UpdateDownloader (Module 21, Chunk 3.2) — fetches a release zip to local
 * staging, resumably and verified.
 *
 * Pure PHP / streamed (flat memory even for large zips). Resumable via HTTP Range:
 * a partial file left by a killed download is continued from its current size
 * (server 206), or restarted if the server ignores the range (200). Retries with
 * backoff on transient failure, then verifies the SHA-256 against the manifest —
 * mandatory (rule #11); a corrupt file is deleted so the next attempt starts clean.
 */
class UpdateDownloader
{
    public function downloadPath(string $version): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $version);

        return $this->stateDir().'/downloads/oeparts-'.$safe.'.zip';
    }

    /** @param array<string,mixed> $manifest release manifest (download_url, sha256, size_bytes, version) */
    public function download(array $manifest): string
    {
        $url = (string) ($manifest['download_url'] ?? '');
        if ($url === '') {
            throw new UpdateException('Release manifest has no download URL.');
        }

        $version  = (string) ($manifest['version'] ?? 'update');
        $expected = $manifest['sha256'] ?? null;
        $size     = (int) ($manifest['size_bytes'] ?? 0);
        $path     = $this->downloadPath($version);
        $this->ensureDir(dirname($path));

        // Idempotent: a previous run already produced a valid file.
        if ($this->isComplete($path, $size, $expected)) {
            return $path;
        }

        $retries = max(0, (int) config('updates.download.retries', 3));
        $backoff = (array) config('updates.download.backoff', [1, 3, 5]);
        $timeout = (int) config('updates.download.timeout', 300);
        $lastError = null;

        for ($attempt = 0; $attempt <= $retries; $attempt++) {
            try {
                $this->fetch($url, $path, $size, $timeout);
                $this->verify($path, $size, $expected);

                return $path;
            } catch (\Throwable $e) {
                $lastError = $e;
                Log::channel(config('updates.log_channel', 'stack'))
                    ->warning('Update download attempt '.($attempt + 1).' failed: '.$e->getMessage());

                $sleep = (int) ($backoff[$attempt] ?? end($backoff) ?: 0);
                if ($sleep > 0 && $attempt < $retries) {
                    sleep($sleep);
                }
            }
        }

        throw new UpdateException('Download failed after '.($retries + 1).' attempt(s): '
            .($lastError?->getMessage() ?? 'unknown error'));
    }

    /** One download attempt — resumes from the partial file when possible. */
    private function fetch(string $url, string $path, int $size, int $timeout): void
    {
        $from = is_file($path) ? (int) filesize($path) : 0;

        // A file already AT (not just past) the expected size got here only because
        // isComplete()'s sha256 check rejected it — its content is corrupt despite
        // the right length (e.g. a byte-shifted resume, or a race with another
        // concurrent attempt writing the same path). Requesting "bytes={size}-" on
        // an exactly-$size-byte file asks for a range past EOF, which every server
        // correctly rejects with 416 — and since this file is never modified by that
        // rejection, every subsequent attempt (and every future apply attempt, since
        // the file persists across FSM runs) hits the identical 416 forever. Confirmed
        // live: two failed apply attempts in a row, identical "HTTP 416" error, until
        // the file was manually inspected. >= (not >) catches this case too.
        if ($size > 0 && $from >= $size) {
            @unlink($path);
            $from = 0;
        }

        $headers  = $from > 0 ? ['Range' => "bytes={$from}-"] : [];
        $response = Http::timeout($timeout)->withHeaders($headers)->withOptions(['stream' => true])->get($url);

        if (! $response->successful()) {
            throw new UpdateException('Download server returned HTTP '.$response->status().'.');
        }

        // 206 = partial content (append); anything else (200) = full body (overwrite).
        $append = $from > 0 && $response->status() === 206;
        $out = fopen($path, $append ? 'ab' : 'wb');
        if ($out === false) {
            throw new UpdateException('Cannot open the download file for writing.');
        }

        $body = $response->toPsrResponse()->getBody();
        try {
            while (! $body->eof()) {
                $chunk = $body->read(1 << 20);
                if ($chunk === '') {
                    break;
                }
                fwrite($out, $chunk);
            }
        } finally {
            fclose($out);
            $body->close();
        }

        clearstatcache(true, $path);
        if ($size > 0 && (int) filesize($path) < $size) {
            // Keep the partial — the next attempt resumes from here.
            throw new UpdateException('Incomplete download ('.filesize($path).'/'.$size.' bytes).');
        }
    }

    private function verify(string $path, int $size, ?string $expected): void
    {
        clearstatcache(true, $path);

        if ($size > 0 && (int) filesize($path) !== $size) {
            @unlink($path);
            throw new UpdateException('Downloaded size mismatch.');
        }

        if (config('updates.download.verify_sha256', true)) {
            if (empty($expected)) {
                throw new UpdateException('Release manifest has no sha256 checksum to verify against.');
            }
            if (! hash_equals((string) $expected, hash_file('sha256', $path))) {
                @unlink($path);
                throw new UpdateException('Downloaded archive failed sha256 verification.');
            }
        }
    }

    private function isComplete(string $path, int $size, ?string $expected): bool
    {
        if (! is_file($path)) {
            return false;
        }
        if ($size > 0 && (int) filesize($path) !== $size) {
            return false;
        }
        if (config('updates.download.verify_sha256', true) && $expected) {
            return hash_equals((string) $expected, hash_file('sha256', $path));
        }

        return $size > 0;
    }

    private function stateDir(): string
    {
        return rtrim((string) config('updates.state_path', storage_path('app/updates')), '/\\');
    }

    private function ensureDir(string $dir): void
    {
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }
}

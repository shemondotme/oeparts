<?php

namespace App\Services\Migration\Transport;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sends one file, or one byte range of it, safely for a multi-gigabyte body served through PHP. It knows nothing about who may
 * download; the caller decides that and passes hooks for what should happen on the way.
 *
 *   - one `Range` header is understood (start-end, start-, -suffix); several ranges are ignored (the whole file is sent); a
 *     range that starts past the end gets 416 with the real size;
 *   - the loop reads 1 MiB at a time, flushes each piece, stops when the client has gone, and never holds more than that in
 *     memory; output buffers are closed first and the time limit is lifted where the host allows it;
 *   - hooks: `onBytes(int)` with the bytes sent so far in batches, `onComplete()` when the LAST byte has been delivered, and
 *     `onFinish()` whatever happened (a lock to give back, say).
 */
class RangeFileStreamer
{
    private const RANGE = '/^bytes=(\d*)-(\d*)$/';

    /** @param  bool  $closeOutputBuffers  true on a real server (PHP's own output buffering would hold a multi-gigabyte body in memory); a test harness turns it off, because its own buffer must survive */
    public function __construct(private readonly bool $closeOutputBuffers = true) {}

    /**
     * @param  array<string, string>  $extraHeaders
     * @param  (callable(int): void)|null  $onBytes
     * @param  (callable(): void)|null  $onComplete
     * @param  (callable(): void)|null  $onFinish
     */
    public function respond(string $path, string $downloadName, int $size, ?string $rangeHeader, array $extraHeaders = [], ?callable $onBytes = null, ?callable $onComplete = null, ?callable $onFinish = null): Response
    {
        $range = $this->range($rangeHeader, $size);
        $headers = $extraHeaders + [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.str_replace(['"', "\r", "\n", '\\'], '', $downloadName).'"',
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
            'X-Accel-Buffering' => 'no',
        ];

        if ($range === 'unsatisfiable') {
            $onFinish !== null && $onFinish();

            return new Response('', 416, $headers + ['Content-Range' => 'bytes */'.$size, 'Content-Length' => '0']);
        }

        [$start, $end] = $range ?? [0, max(0, $size - 1)];
        $partial = $range !== null;
        $headers['Content-Length'] = (string) ($size === 0 ? 0 : $end - $start + 1);

        if ($partial) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        }

        $chunk = max(64 * 1024, (int) config('migration.share.chunk_bytes', 1024 * 1024));

        return new StreamedResponse(function () use ($path, $start, $end, $size, $chunk, $onBytes, $onComplete, $onFinish): void {
            try {
                $this->send($path, $start, $end, $size, $chunk, $onBytes, $onComplete);
            } finally {
                $onFinish !== null && $onFinish();
            }
        }, $partial ? 206 : 200, $headers);
    }

    private function send(string $path, int $start, int $end, int $size, int $chunk, ?callable $onBytes, ?callable $onComplete): void
    {
        if (function_exists('set_time_limit')) {
            try {
                @set_time_limit(0); // may sit in disable_functions on shared hosting
            } catch (\Throwable) {
            }
        }

        while ($this->closeOutputBuffers && ob_get_level() > 0) {
            @ob_end_clean();
        }

        ignore_user_abort(true); // permission is already decided: finish cleanly even if the client drops

        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return;
        }

        $remaining = $size === 0 ? 0 : $end - $start + 1;
        $aborted = false;
        $unreported = 0;

        try {
            fseek($handle, $start);

            while ($remaining > 0 && ! feof($handle)) {
                if (connection_aborted()) {
                    $aborted = true;
                    break;
                }

                $piece = fread($handle, min($chunk, $remaining));

                if ($piece === false || $piece === '') {
                    break;
                }

                echo $piece;
                flush();

                $remaining -= strlen($piece);
                $unreported += strlen($piece);

                if ($unreported >= 32 * 1024 * 1024) {
                    $onBytes !== null && $onBytes($unreported);
                    $unreported = 0;
                }
            }
        } finally {
            fclose($handle);
            $onBytes !== null && $onBytes($unreported);
        }

        if (! $aborted && $remaining === 0 && ($size === 0 || $end === $size - 1)) {
            $onComplete !== null && $onComplete();
        }
    }

    /** @return array{0: int, 1: int}|'unsatisfiable'|null null = send the whole file */
    private function range(?string $header, int $size): array|string|null
    {
        if ($header === null || ! str_starts_with($header, 'bytes=') || str_contains($header, ',') || preg_match(self::RANGE, trim($header), $m) !== 1) {
            return null;
        }

        [, $from, $to] = $m;

        if ($from === '' && $to === '') {
            return null;
        }

        if ($from === '') { // the last N bytes
            $n = (int) $to;

            return $n === 0 || $size === 0 ? 'unsatisfiable' : [max(0, $size - $n), $size - 1];
        }

        $start = (int) $from;

        if ($start >= $size) {
            return 'unsatisfiable';
        }

        $end = $to === '' ? $size - 1 : min((int) $to, $size - 1);

        return $end < $start ? null : [$start, $end];
    }
}

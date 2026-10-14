<?php

namespace App\Services\Migration\Transport;

use App\Models\PackageShareLink;
use App\Services\Migration\Export\PackageOutbox;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Sends a package (or a byte range of it) to a downloader that was let in by ShareLinkService, safely for a multi-gigabyte
 * file served through PHP:
 *
 *   - the file is found by the NAME stored in the link's row, inside the exports folder, never from a request value, and
 *     only when it is a plain file of exactly the recorded size;
 *   - one `Range` header is understood (start-end, start-, -suffix); several ranges are ignored (the whole file is sent);
 *     a range that starts past the end gets 416 with the real size;
 *   - the loop reads 1 MiB at a time, flushes each piece, stops when the client has gone, and never holds more than that
 *     in memory; output buffers are closed first and the time limit is lifted where the host allows it;
 *   - when the LAST byte has been delivered the link is consumed; bytes served are counted on the way.
 */
class ShareStreamer
{
    private const RANGE = '/^bytes=(\d*)-(\d*)$/';

    /** @param  bool  $closeOutputBuffers  true on a real server (PHP's own output buffering would hold a multi-gigabyte body in memory); a test harness turns it off, because its own buffer must survive */
    public function __construct(
        private readonly ShareLinkService $links,
        private readonly PackageOutbox $outbox,
        private readonly bool $closeOutputBuffers = true,
    ) {}

    /** The path of the file this link is for, when it is still exactly what was shared; null otherwise. */
    public function fileFor(PackageShareLink $link): ?string
    {
        $path = $this->outbox->find($link->package_name);

        if ($path === null || ! is_file($path) || is_link($path) || (int) filesize($path) !== $link->package_size) {
            return null;
        }

        return $path;
    }

    /**
     * @param  resource|null  $slot  a ShareSlots handle: released when the transfer ends
     */
    public function respond(PackageShareLink $link, string $path, ?string $rangeHeader, ?string $newClaim, $slot = null, ?ShareSlots $slots = null): Response
    {
        $size = $link->package_size;
        $range = $this->range($rangeHeader, $size);
        $headers = [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => 'attachment; filename="'.$link->package_name.'"',
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'no-store',
            'X-Content-Type-Options' => 'nosniff',
            'X-Accel-Buffering' => 'no',
        ] + ($newClaim !== null ? ['X-OeParts-Claim' => $newClaim] : []);

        if ($range === 'unsatisfiable') {
            $slot !== null && $slots?->release($slot);

            return new Response('', 416, $headers + ['Content-Range' => 'bytes */'.$size, 'Content-Length' => '0']);
        }

        [$start, $end] = $range ?? [0, $size - 1];
        $partial = $range !== null;
        $headers['Content-Length'] = (string) ($size === 0 ? 0 : $end - $start + 1);

        if ($partial) {
            $headers['Content-Range'] = "bytes {$start}-{$end}/{$size}";
        }

        $chunk = max(64 * 1024, (int) config('migration.share.chunk_bytes', 1024 * 1024));

        return new StreamedResponse(function () use ($link, $path, $start, $end, $size, $chunk, $slot, $slots): void {
            try {
                $this->send($link, $path, $start, $end, $size, $chunk);
            } finally {
                $slot !== null && $slots?->release($slot);
            }
        }, $partial ? 206 : 200, $headers);
    }

    private function send(PackageShareLink $link, string $path, int $start, int $end, int $size, int $chunk): void
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

        ignore_user_abort(true); // the claim is already committed: finish cleanly even if the client drops

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
                    $this->links->addBytesServed($link, $unreported);
                    $unreported = 0;
                }
            }
        } finally {
            fclose($handle);
            $this->links->addBytesServed($link, $unreported);
        }

        if (! $aborted && $remaining === 0 && ($size === 0 || $end === $size - 1)) {
            $this->links->consume($link);
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

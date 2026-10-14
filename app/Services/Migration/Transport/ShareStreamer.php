<?php

namespace App\Services\Migration\Transport;

use App\Models\PackageShareLink;
use App\Services\Migration\Export\PackageOutbox;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends a package (or a byte range of it) to a downloader that was let in by ShareLinkService:
 *
 *   - the file is found by the NAME stored in the link's row, inside the exports folder, never from a request value, and
 *     only when it is a plain file of exactly the recorded size;
 *   - the streaming itself (ranges, 1 MiB chunks, buffers, time limit) is RangeFileStreamer's;
 *   - when the LAST byte has been delivered the link is consumed; bytes served are counted on the way; the download slot is
 *     given back when the transfer ends, whatever way it ends.
 */
class ShareStreamer
{
    public function __construct(
        private readonly ShareLinkService $links,
        private readonly PackageOutbox $outbox,
        private readonly RangeFileStreamer $streamer,
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
        return $this->streamer->respond(
            $path,
            $link->package_name,
            $link->package_size,
            $rangeHeader,
            $newClaim !== null ? ['X-OeParts-Claim' => $newClaim] : [],
            fn (int $bytes) => $this->links->addBytesServed($link, $bytes),
            fn () => $this->links->consume($link),
            fn () => $slot !== null && $slots?->release($slot),
        );
    }
}

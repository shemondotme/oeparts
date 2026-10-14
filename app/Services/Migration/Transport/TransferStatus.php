<?php

namespace App\Services\Migration\Transport;

/** Where a package download stands. */
enum TransferStatus: string
{
    case Queued = 'queued';
    case Resolving = 'resolving';
    case Downloading = 'downloading';
    case Verifying = 'verifying';
    case Paused = 'paused';
    case Ready = 'ready';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function isTerminal(): bool
    {
        return in_array($this, [self::Ready, self::Failed, self::Cancelled], true);
    }
}

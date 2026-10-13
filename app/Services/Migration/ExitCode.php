<?php

namespace App\Services\Migration;

/** Exit codes of the oeparts:migrate:* commands, so scripts and CI can tell the outcomes apart. */
enum ExitCode: int
{
    case Ok = 0;
    case Failed = 1;
    case Usage = 2;                 // usage or validation error
    case PreflightBlocked = 3;
    case PackageUnreadable = 4;
    case WrongPassphrase = 5;
    case SystemBusy = 6;            // the shared backup/update lock is held
    case NotConfirmed = 7;          // REPLACE missing, or a populated target without --replace-populated
    case Cancelled = 8;
    case FinishedWithWarnings = 9;  // only under --strict
    case NotFinished = 10;          // the time budget ran out; resumable with --resume
}

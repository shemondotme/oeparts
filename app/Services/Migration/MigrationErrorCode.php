<?php

namespace App\Services\Migration;

/**
 * The single catalogue of things that can go wrong in a site export or import. Every service
 * throws a MigrationException carrying one of these (plus parameters); the UI and the CLI show
 * the title, message and "what to do" of lang/<locale>/migration.php under
 * migration.error.<code>, never the technical exception message (that goes to the log).
 */
enum MigrationErrorCode: string
{
    // The package file itself
    case PackageNotFound = 'package_not_found';
    case PackageNotOeparts = 'package_not_oeparts';
    case PackageIncomplete = 'package_incomplete';
    case PackageCorrupt = 'package_corrupt';
    case PackageTextMode = 'package_text_mode';
    case PackageHashMismatch = 'package_hash_mismatch';
    case PackageChanged = 'package_changed';

    // Passphrase and key
    case PassphraseEmpty = 'passphrase_empty';
    case PassphraseWrong = 'passphrase_wrong';
    case PassphraseLocked = 'passphrase_locked';
    case PassphraseOverHttp = 'passphrase_over_http';
    case KeyEscrowLost = 'key_escrow_lost';
    case KdfMemory = 'kdf_memory';
    case KdfUnavailable = 'kdf_unavailable';
    case KdfPassphraseWeak = 'kdf_passphrase_weak';

    // Compatibility
    case VersionTooNew = 'version_too_new';
    case MigrationSetDiffers = 'migration_set_differs';

    // Preflight
    case PreflightPhp = 'preflight_php';
    case PreflightExtension = 'preflight_extension';
    case PreflightDbVersion = 'preflight_db_version';
    case PreflightDbEngine = 'preflight_db_engine';
    case PreflightDbPrivileges = 'preflight_db_privileges';
    case PreflightDisk = 'preflight_disk';
    case PreflightMemory = 'preflight_memory';
    case PreflightNotWritable = 'preflight_not_writable';

    // Target and confirmation
    case TargetNotFresh = 'target_not_fresh';
    case ReplaceNotConfirmed = 'replace_not_confirmed';
    case RecoveryPointTooLarge = 'recovery_point_too_large';

    // Locks and runs
    case LockBusy = 'lock_busy';
    case LockLost = 'lock_lost';
    case FreezeActive = 'freeze_active';
    case FreezeNeedsQueueOrCli = 'freeze_needs_queue_or_cli';
    case FreezeTooLong = 'freeze_too_long';
    case NoWorker = 'no_worker';
    case RunStale = 'run_stale';
    case StageStalled = 'stage_stalled';
    case ImportFailedStep = 'import_failed_step';
    case RollbackFailed = 'rollback_failed';
    case TokenInvalid = 'token_invalid';
    case WizardOwnedElsewhere = 'wizard_owned_elsewhere';

    // Getting the file onto the server
    case UrlNotHttps = 'url_not_https';
    case UrlBlocked = 'url_blocked';
    case UrlRedirectBlocked = 'url_redirect_blocked';
    case UrlTooLarge = 'url_too_large';
    case UrlTimeout = 'url_timeout';
    case UrlHttpError = 'url_http_error';
    case UploadTooLarge = 'upload_too_large';
    case ShareExpired = 'share_expired';

    // The new site's address
    case SiteUrlInvalid = 'site_url_invalid';
    case SiteUrlHttpsUnavailable = 'site_url_https_unavailable';

    /** Translation key for one part of this code's text: title, message or fix. */
    public function langKey(string $part): string
    {
        return 'migration.error.'.$this->value.'.'.$part;
    }
}

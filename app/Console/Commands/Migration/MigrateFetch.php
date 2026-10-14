<?php

namespace App\Console\Commands\Migration;

use App\Console\Concerns\EmitsJson;
use App\Services\Migration\ExitCode;
use App\Services\Migration\MigrationErrorCode;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Transport\TransferDriver;
use App\Services\Migration\Transport\TransferManager;
use App\Services\Migration\Transport\TransferStatus;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;

/**
 * Downloads a package from a link into the import folder, and waits for it. Safe to stop and run again with --resume=ID: the
 * bytes already received stay and the download carries on from where the file ends.
 *
 * Exit codes: 0 the package is in the import folder; 1 the download failed; 2 usage; 3 not enough disk space;
 * 4 the file is not the package it was announced as; 8 stopped by the person (Ctrl+C); 10 not finished (time budget).
 */
class MigrateFetch extends Command
{
    use EmitsJson;

    protected $signature = 'oeparts:migrate:fetch
        {link? : The link of the package (a share string with #sha256=... is understood)}
        {--sha256= : The checksum the package was shared with}
        {--name= : The file name to keep in the import folder (default: from the link)}
        {--resume= : The id of a transfer to carry on instead of starting a new one}
        {--budget=0 : Stop after this many seconds (0 = until it is finished); --resume continues}
        {--json : Print newline-delimited JSON events on standard output}';

    protected $description = 'Download a package from a link into the import folder';

    protected bool $stop = false;

    public function handle(TransferManager $manager, TransferDriver $driver): int
    {
        if (function_exists('pcntl_async_signals') && function_exists('pcntl_signal') && defined('SIGINT')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGINT, function (): void {
                $this->stop = true;
            });
        }

        try {
            if ($this->option('resume') !== null) {
                $state = $manager->require((string) $this->option('resume'));
            } elseif ($this->argument('link') === null) {
                return $this->finish($this->reportUsage('Give the link of the package, or --resume=ID.'));
            } else {
                $state = $manager->start((string) $this->argument('link'), $this->option('sha256') ?: null, $this->option('name') ?: null, 'command line');
                $this->event('transfer', ['transfer_id' => $state->id]);
                $this->human('Download '.$state->id.' started from '.$state->host.'.');
            }

            $budget = max(0.0, (float) $this->option('budget'));
            $final = $driver->drive($state->id, $budget > 0 ? $budget : null, function (array $p): void {
                $this->event('progress', Arr::only($p, ['status', 'percent', 'bytes', 'total', 'message']));
                $this->human($p['message']);
            }, fn (): bool => $this->stop);
        } catch (MigrationException $e) {
            $this->reportError($e);

            return $this->finish($this->exitFor($e), ['error' => $e->errorCode->value]);
        }

        return match ($final->status) {
            TransferStatus::Ready => $this->finish(ExitCode::Ok, ['transfer_id' => $final->id, 'package' => $final->finalName]),
            TransferStatus::Failed => $this->failed($final->id, (array) $final->error),
            TransferStatus::Cancelled => $this->finish(ExitCode::Cancelled, ['transfer_id' => $final->id]),
            default => $this->unfinished($final->id),
        };
    }

    /** @param  array<string, mixed>  $error */
    private function failed(string $id, array $error): int
    {
        $e = new MigrationException(MigrationErrorCode::tryFrom((string) ($error['code'] ?? '')) ?? MigrationErrorCode::UrlHttpError, (array) ($error['params'] ?? []));
        $this->reportError($e);

        return $this->finish($this->exitFor($e), ['transfer_id' => $id, 'error' => $e->errorCode->value]);
    }

    private function unfinished(string $id): int
    {
        $this->human('The download is not finished. Carry on with: php artisan oeparts:migrate:fetch --resume='.$id);

        return $this->finish($this->stop ? ExitCode::Cancelled : ExitCode::NotFinished, ['transfer_id' => $id]);
    }

    private function exitFor(MigrationException $e): ExitCode
    {
        return match ($e->errorCode) {
            MigrationErrorCode::PreflightDisk => ExitCode::PreflightBlocked,
            MigrationErrorCode::PackageNotFound, MigrationErrorCode::PackageNotOeparts, MigrationErrorCode::PackageCorrupt, MigrationErrorCode::PackageHashMismatch => ExitCode::PackageUnreadable,
            default => ExitCode::Failed,
        };
    }
}

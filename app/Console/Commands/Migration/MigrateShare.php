<?php

namespace App\Console\Commands\Migration;

use App\Console\Concerns\EmitsJson;
use App\Models\PackageShareLink;
use App\Services\Migration\ExitCode;
use App\Services\Migration\Export\PackageOutbox;
use App\Services\Migration\MigrationException;
use App\Services\Migration\Transport\ShareLinkService;
use Illuminate\Console\Command;
use Illuminate\Support\Number;

/**
 * Makes a one-time download link for an exported package, lists the links, or revokes one. The link is shown ONCE (only a hash
 * of its token is kept), and it never contains the passphrase: pass that on separately.
 *
 * Exit codes: 0 ok; 1 failed (no such package, too many live links); 2 usage.
 */
class MigrateShare extends Command
{
    use EmitsJson;

    protected $signature = 'oeparts:migrate:share
        {package? : The package in the exports folder (default: the newest one)}
        {--ttl= : Hours the link stays valid (default 24, at most 168)}
        {--ip= : Only this address may download}
        {--note= : A note for yourself}
        {--list : Show the links instead of making one}
        {--revoke= : The id of a link to revoke}
        {--json : Print newline-delimited JSON events on standard output}';

    protected $description = 'Make, list or revoke a one-time download link for an exported package';

    public function handle(ShareLinkService $links, PackageOutbox $outbox): int
    {
        if ($this->option('revoke') !== null) {
            $done = $links->revoke((int) $this->option('revoke'));
            $this->human($done ? 'The link is revoked.' : 'No live link has that id.');

            return $this->finish($done ? ExitCode::Ok : ExitCode::Failed, ['revoked' => $done]);
        }

        if ($this->option('list')) {
            return $this->list($links);
        }

        $name = $this->argument('package') ?? ($outbox->list()[0]['name'] ?? null);

        if ($name === null) {
            return $this->finish($this->reportUsage('There is no exported package to share. Export one first, or name it.'));
        }

        try {
            $made = $links->create(
                (string) $name,
                null,
                $this->option('ttl') !== null ? (int) $this->option('ttl') : null,
                $this->option('ip') ?: null,
                $this->option('note') ?: null,
            );
        } catch (MigrationException $e) {
            $this->reportError($e);

            return $this->finish(ExitCode::Failed, ['error' => $e->errorCode->value]);
        }

        $link = $made['link'];
        $this->human('Link for '.$link->package_name.' ('.Number::fileSize($link->package_size).'), valid until '.$link->expires_at->toDateTimeString().':');
        $this->human('');
        $this->human($made['share']);
        $this->human('');
        $this->human('It works once and is not shown again. The passphrase is NOT part of it: give that to the other side separately.');

        if ($made['warning'] !== null) {
            $this->humanWarn($made['warning']);
        }

        $this->event('share', ['link_id' => $link->getKey(), 'package' => $link->package_name, 'expires_at' => $link->expires_at->toIso8601String(), 'url' => $made['share']]);

        return $this->finish(ExitCode::Ok, ['link_id' => $link->getKey(), 'url' => $made['share']], $made['warning'] === null ? [] : [$made['warning']]);
    }

    private function list(ShareLinkService $links): int
    {
        $links->expireOld();
        $rows = PackageShareLink::query()->orderByDesc('id')->limit(50)->get();

        foreach ($rows as $row) {
            $this->event('link', ['link_id' => $row->getKey(), 'package' => $row->package_name, 'state' => $row->state, 'expires_at' => $row->expires_at->toIso8601String(), 'bytes_served' => $row->bytes_served, 'denied' => $row->deny_count]);
        }

        if (! $this->jsonOutput()) {
            $this->table(['Id', 'Package', 'State', 'Valid until', 'Sent', 'Refused'], $rows->map(fn (PackageShareLink $l): array => [
                $l->getKey(), $l->package_name, $l->state, $l->expires_at->toDateTimeString(), Number::fileSize($l->bytes_served), $l->deny_count,
            ])->all());
        }

        return $this->finish(ExitCode::Ok, ['links' => $rows->count()]);
    }
}

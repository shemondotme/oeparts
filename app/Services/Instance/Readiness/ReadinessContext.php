<?php

namespace App\Services\Instance\Readiness;

use App\Services\Instance\InstanceState;
use App\Services\Instance\SafeMode;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStateStore;

/**
 * What a check may look at besides the database: the mode, the ledger, the import this site came from, and (when the check is
 * run for a web request) the host that was asked for.
 */
final class ReadinessContext
{
    private ?ImportState $import = null;

    private bool $importLoaded = false;

    public function __construct(
        public readonly SafeMode $safe,
        public readonly InstanceState $ledger,
        private readonly ImportStateStore $runs,
        private readonly ?ImportState $current = null,
        public readonly ?string $requestHost = null,
    ) {}

    /** The context the application's own services give: this installation's mode, ledger and runs, for a given import and request host. */
    public static function make(?ImportState $current = null, ?string $requestHost = null): self
    {
        return new self(app(SafeMode::class), app(InstanceState::class), app(ImportStateStore::class), $current, $requestHost);
    }

    /** The import run this site was made by: the one running now, else the one the ledger remembers; null on a site that never imported. */
    public function import(): ?ImportState
    {
        if ($this->current !== null) {
            return $this->current;
        }

        if (! $this->importLoaded) {
            $this->importLoaded = true;
            $id = (string) ($this->ledger->read()['import']['run_id'] ?? '');

            try {
                $this->import = ImportStateStore::validId($id) ? $this->runs->find($id) : null;
            } catch (\RuntimeException) {
                $this->import = null;
            }
        }

        return $this->import;
    }

    public function runsStore(): ImportStateStore
    {
        return $this->runs;
    }

    /** The host the site says it lives at (config app.url), lower case, or '' when it has none. */
    public function appHost(): string
    {
        $host = parse_url((string) config('app.url'), PHP_URL_HOST);

        return is_string($host) ? strtolower($host) : '';
    }

    public function check(string $key, string $label, string $group, string $status, string $message = '', array $meta = [], string $fix = '', bool $overridable = false): ReadinessCheck
    {
        return new ReadinessCheck($key, $label, $group, $status, $message, $meta, $fix, $overridable);
    }
}

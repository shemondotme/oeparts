<?php

namespace App\Console\Commands\Migration;

use App\Services\Migration\Installer\ImportWizardState;
use App\Services\Migration\Installer\InstallerPackageUnlock;
use Illuminate\Console\Command;

/**
 * Hands the installer's import branch back to whoever opens /install next.
 *
 * The branch belongs to the one browser that started it. If that browser is gone (a closed laptop, a lost cookie) the owner
 * can either wait for the idle timeout or run this on the server. It removes only the remembered choices; it never touches
 * a package, a run or the database.
 */
class MigrateWizardReset extends Command
{
    protected $signature = 'oeparts:migrate:wizard-reset';

    protected $description = 'Release the installer import branch so another browser can start it';

    public function handle(ImportWizardState $wizard, InstallerPackageUnlock $unlock): int
    {
        if (! $wizard->exists()) {
            $this->info('Nobody has started the installer import branch. Nothing to reset.');

            return self::SUCCESS;
        }

        // The keys of a package that was opened in the branch leave the escrow with it.
        $unlock->release();
        $wizard->reset();

        $this->info('The installer import branch is free. The next browser that opens /install can start it.');

        return self::SUCCESS;
    }
}

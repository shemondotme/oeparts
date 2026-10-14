<?php

namespace App\Services\Migration\Import\Stages;

use App\Services\Instance\InstanceState;
use App\Services\Instance\OriginResetRegistry;
use App\Services\Migration\Import\ImportContext;
use App\Services\Migration\Import\ImportMarker;
use App\Services\Migration\Import\ImportSettings;
use App\Services\Migration\Import\ImportState;
use App\Services\Migration\Import\ImportStepResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stage 18. What belongs to the OLD site's visitors and server does not come along alive.
 *
 *   tables     push subscriptions, admin sessions, API tokens, sign-in codes and password-reset tokens are emptied: they are tied
 *              to the old address, and (because a populated site is replaced) to account ids that now mean other people;
 *   settings   everything that arms something on its own (automatic cleanup, the backup schedule, automatic security updates) is
 *              forced OFF in both launch modes, with a row written even where none existed, so a default can never switch it on.
 *              The list is the registry in config('instance.origin_reset') plus a switch per Cleanup Engine target.
 *
 * What was switched off is recorded in the ledger and listed in the report ("switch these on when you are ready"). Repeating
 * the stage changes nothing.
 */
final class FinOriginResetStage extends FinalisingStage
{
    /** System tables that hold something tied to the old site. They are not part of any group, so db_clear never touched them. */
    private const TABLES = ['admin_push_subscriptions', 'admin_sessions', 'personal_access_tokens', 'otps', 'password_reset_tokens'];

    public function __construct(
        ImportMarker $marker,
        private readonly OriginResetRegistry $registry,
        private readonly ImportSettings $settings,
        private readonly InstanceState $ledger,
    ) {
        parent::__construct($marker);
    }

    public function key(): string
    {
        return 'fin_origin_reset';
    }

    protected function after(): string
    {
        return 'fin.normalize.done';
    }

    protected function flag(): string
    {
        return 'fin.origin.done';
    }

    public function step(ImportState $state, ImportContext $context): ImportStepResult
    {
        if ($state->flag($this->flag())) {
            return ImportStepResult::done('What belonged to the old site is reset.');
        }

        $this->guard($state);

        $emptied = [];

        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table)) {
                $rows = (int) DB::table($table)->count();
                DB::table($table)->delete();

                if ($rows > 0) {
                    $emptied[$table] = $rows;
                }
            }
        }

        $switchedOff = [];

        foreach ($this->registry->settings() as $group => $keys) {
            foreach ($keys as $key) {
                if ($this->settings->stored($group, $key) !== '0') {
                    $this->settings->put($group, $key, '0', 'boolean');
                    $switchedOff[] = $group.'.'.$key;
                }
            }
        }

        $state->package['origin_reset'] = ['tables' => $emptied, 'switched_off' => $switchedOff];

        $this->ledger->update(function (array $ledger) use ($switchedOff): array {
            $ledger['import'] = ($ledger['import'] ?? []);
            $ledger['import']['switched_off'] = array_values(array_unique([...(array) ($ledger['import']['switched_off'] ?? []), ...$switchedOff]));

            return $ledger;
        });

        $state->setFlag($this->flag());

        return ImportStepResult::done(count($emptied).' table(s) emptied, '.count($switchedOff).' setting(s) switched off.');
    }
}

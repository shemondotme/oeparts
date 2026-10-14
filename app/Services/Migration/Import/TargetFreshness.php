<?php

namespace App\Services\Migration\Import;

use App\Services\Migration\Groups\GroupKey;
use App\Services\Migration\Groups\GroupRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The ONE classification of the site an import would replace, used by the preflight, the admin wizard, the confirm
 * screen, the command-line exit code and the installer door, so they cannot disagree.
 *
 * A classification, not a refusal: the admin panel and the command line may replace any of the three classes (a
 * populated one only with extra, typed confirmation); the installer door only runs where nobody has used the site.
 *
 *   empty      no row outside the core tables: what a fresh install leaves.
 *   fresh      content but no business activity, and not larger than the recovery-point cap.
 *   populated  business activity (an order, a payment, a customer, an invoice, a message, a subscriber, a refund)
 *              or more rows than the recovery point can hold.
 *
 * The recovery-point cap counts the SAME thing: rows in every table outside the System group.
 */
final class TargetFreshness
{
    /** Tables whose rows mean somebody USED the site. */
    public const BUSINESS_TABLES = ['orders', 'payments', 'users', 'custom_invoices', 'contact_messages', 'newsletter_subscribers', 'refund_requests'];

    public function __construct(private readonly GroupRegistry $registry = new GroupRegistry, private readonly ?string $connection = null) {}

    public function assess(): TargetAssessment
    {
        $core = array_flip($this->registry->tablesOf([GroupKey::Core]));
        $db = DB::connection($this->connection);
        $schema = Schema::connection($this->connection);

        $rows = [];
        $total = 0;
        $nonCore = 0;

        foreach ($this->registry->exportableTables() as $table) {
            if (! $schema->hasTable($table)) {
                continue;
            }

            $count = (int) $db->table($table)->count();

            if ($count === 0) {
                continue;
            }

            $rows[$table] = $count;
            $total += $count;

            if (! isset($core[$table])) {
                $nonCore += $count;
            }
        }

        $business = array_intersect_key($rows, array_flip(self::BUSINESS_TABLES));
        $cap = max(1, (int) config('migration.import.recovery_max_rows', 500_000));

        $class = match (true) {
            $business !== [] || $total > $cap => TargetClass::Populated,
            $nonCore > 0 => TargetClass::Fresh,
            default => TargetClass::Empty,
        };

        return new TargetAssessment($class, $business, $rows, $total, $nonCore, $cap);
    }
}

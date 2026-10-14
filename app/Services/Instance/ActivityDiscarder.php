<?php

namespace App\Services\Instance;

use App\Services\Migration\Export\FkGraph;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What a staging copy has done since it was moved in, and how to take it back out.
 *
 * Going live must not carry test customers, test orders and test payments into the real shop. The import records, for every table
 * that holds business activity, the highest id of the rows it brought (the watermark); anything above it was created on this copy.
 * Discarding deletes exactly those rows, children before parents, with hard deletes (a soft-deleted user would keep its rows and
 * defeat every cascade), all in one transaction so a refusal by the database leaves nothing half deleted. Then the order and invoice
 * counters go back to where they stood when the copy was made, so the real shop's first invoice has no gap in front of it.
 */
class ActivityDiscarder
{
    /** Tables that hold what a visitor or customer creates. */
    public const WATCHED = [
        'users', 'orders', 'payments', 'custom_invoices', 'contact_messages', 'part_inquiries',
        'newsletter_subscribers', 'refund_requests', 'product_reviews', 'abandoned_carts',
    ];

    public function __construct(private readonly InstanceState $ledger) {}

    /**
     * Rows above the watermark, per table. A table with no recorded watermark (a site that was not made by an import) is not counted.
     *
     * @return array<string, int>
     */
    public function activity(): array
    {
        $found = [];

        foreach ($this->tables() as $table) {
            $watermark = $this->ledger->watermark($table);

            if ($watermark === null) {
                continue;
            }

            $rows = (int) DB::table($table)->where('id', '>', $watermark)->count();

            if ($rows > 0) {
                $found[$table] = $rows;
            }
        }

        return $found;
    }

    /**
     * Delete everything above the watermarks and put the numbering back. Repeating it deletes nothing more.
     *
     * @return array{deleted: array<string, int>, counters_restored: array<int, string>}
     */
    public function discard(): array
    {
        $deleted = [];

        DB::transaction(function () use (&$deleted): void {
            foreach ($this->childFirst($this->tables()) as $table) {
                $watermark = $this->ledger->watermark($table);

                if ($watermark === null) {
                    continue;
                }

                $rows = DB::table($table)->where('id', '>', $watermark)->delete();

                if ($rows > 0) {
                    $deleted[$table] = $rows;
                }
            }
        });

        return ['deleted' => $deleted, 'counters_restored' => $this->restoreCounters()];
    }

    /** @return array<int, string> the watched tables this database has */
    private function tables(): array
    {
        return array_values(array_filter(self::WATCHED, static fn (string $t): bool => Schema::hasTable($t) && Schema::hasColumn($t, 'id')));
    }

    /**
     * Order tables so that a table comes before every table it points at (a payment before its order, an order before its customer).
     *
     * @param  array<int, string>  $tables
     * @return array<int, string>
     */
    private function childFirst(array $tables): array
    {
        $parents = [];

        foreach ((new FkGraph($tables))->edges() as $edge) {
            if (! $edge->isSelfReference() && in_array($edge->child, $tables, true) && in_array($edge->parent, $tables, true)) {
                $parents[$edge->child][$edge->parent] = true;
            }
        }

        $ordered = [];
        $remaining = $tables;

        while ($remaining !== []) {
            $progress = false;

            foreach ($remaining as $i => $table) {
                // A table can go once nothing still waiting points at it.
                $pointedAt = false;

                foreach ($remaining as $other) {
                    if ($other !== $table && isset($parents[$other][$table])) {
                        $pointedAt = true;

                        break;
                    }
                }

                if (! $pointedAt) {
                    $ordered[] = $table;
                    unset($remaining[$i]);
                    $progress = true;
                }
            }

            if (! $progress) {
                array_push($ordered, ...array_values($remaining)); // a cycle: delete in the listed order and let the database speak

                break;
            }
        }

        return $ordered;
    }

    /** @return array<int, string> the series whose counter was put back */
    private function restoreCounters(): array
    {
        $series = (array) ($this->ledger->read()['sequence_baseline']['series'] ?? []);
        $restored = [];

        foreach ($series as $type => $baseline) {
            $changed = DB::table('sequences')->where('type', (string) $type)->update([
                'current_value' => (int) ($baseline['value'] ?? 0),
                'last_reset_month' => $baseline['last_reset_month'] ?? null,
                'updated_at' => now(),
            ]);

            if ($changed > 0) {
                $restored[] = (string) $type;
            }
        }

        return $restored;
    }
}

<?php

namespace App\Services\Migration\Import;

use Illuminate\Support\Facades\DB;

/**
 * Makes sure the next order, invoice or document number can never repeat one that already exists.
 *
 * Numbers look like `ORD-202610-000042` (a prefix, the month, a counter that starts again every month) or `RMA-000007`. The
 * counter lives in the `sequences` table, which comes from the package; the numbers themselves live in the orders and invoices.
 * After a move the two can disagree (a package taken while an order was being placed, a counter that was reset on purpose), and
 * a repeated number is an invoice with the same number as another, which is a legal problem. So for every series: the counter is
 * never LOWER than the highest number already issued this month.
 *
 * A counter is never lowered, only raised. A series whose numbers live nowhere this code knows about is left as it is.
 */
final class SequenceFloor
{
    /** sequence type => where its numbers are stored: [table, column, a column=value filter or null] */
    private const SOURCES = [
        'order' => [['orders', 'order_number', null]],
        'invoice' => [['orders', 'invoice_number', null], ['custom_invoices', 'invoice_number', ['document_type', 'invoice']]],
        'quote' => [['custom_invoices', 'invoice_number', ['document_type', 'quote']]],
        'proforma' => [['custom_invoices', 'invoice_number', ['document_type', 'proforma']]],
        'credit_note' => [['custom_invoices', 'invoice_number', ['document_type', 'credit_note']]],
    ];

    /**
     * Raise the counters that are below the numbers already issued, and return what each series looks like afterwards.
     *
     * @return array<string, array{value: int, last_reset_month: string|null, raised_from: int|null}>
     */
    public function apply(): array
    {
        $month = now()->format('Y-m');
        $compact = now()->format('Ym');
        $report = [];

        foreach (DB::table('sequences')->get() as $row) {
            $type = (string) $row->type;
            $value = (int) $row->current_value;
            $lastReset = $row->last_reset_month === null ? null : (string) $row->last_reset_month;
            $highest = $this->highestIssued($type, (bool) $row->resets_monthly ? $compact : null);
            $raisedFrom = null;

            if ($highest > 0) {
                $sameMonth = ! (bool) $row->resets_monthly || $lastReset === $month;
                $floor = $sameMonth ? max($value, $highest) : $highest;

                if ($floor !== $value || ($row->resets_monthly && $lastReset !== $month)) {
                    DB::table('sequences')->where('id', $row->id)->update([
                        'current_value' => $floor,
                        'last_reset_month' => $row->resets_monthly ? $month : $lastReset,
                        'updated_at' => now(),
                    ]);

                    $raisedFrom = $value;
                    $value = $floor;
                    $lastReset = $row->resets_monthly ? $month : $lastReset;
                }
            }

            $report[$type] = ['value' => $value, 'last_reset_month' => $lastReset, 'raised_from' => $raisedFrom];
        }

        return $report;
    }

    /**
     * The highest counter in the numbers issued this month (or ever, for a series that does not reset), 0 when there are none.
     */
    private function highestIssued(string $type, ?string $compactMonth): int
    {
        $highest = 0;

        foreach (self::SOURCES[$type] ?? [] as [$table, $column, $filter]) {
            $query = DB::table($table)->whereNotNull($column);

            if ($filter !== null) {
                $query->where($filter[0], $filter[1]);
            }

            if ($compactMonth !== null) {
                $query->where($column, 'like', '%-'.$compactMonth.'-%');
            }

            $pattern = $compactMonth !== null ? '/-'.$compactMonth.'-(\d+)$/' : '/-(\d+)$/';

            foreach ($query->pluck($column) as $number) {
                if (preg_match($pattern, (string) $number, $m) === 1) {
                    $highest = max($highest, (int) $m[1]);
                }
            }
        }

        return $highest;
    }
}

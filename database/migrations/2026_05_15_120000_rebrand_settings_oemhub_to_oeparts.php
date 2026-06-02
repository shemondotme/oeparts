<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Update stored settings from legacy OEMHub branding to OeParts (existing installs).
     */
    public function up(): void
    {
        if (! Schema::hasTable('settings')) {
            return;
        }

        $exactEmailUpdates = [
            ['general', 'site_email', 'info@oemhub.eu', 'info@oeparts.lt'],
            ['contact', 'email', 'info@oemhub.eu', 'info@oeparts.lt'],
            ['email', 'from_address', 'no-reply@oemhub.eu', 'no-reply@oeparts.lt'],
            ['email', 'reply_to', 'info@oemhub.eu', 'info@oeparts.lt'],
        ];

        foreach ($exactEmailUpdates as [$group, $key, $from, $to]) {
            DB::table('settings')
                ->where('group', $group)
                ->where('key', $key)
                ->where('value', $from)
                ->update(['value' => $to, 'updated_at' => now()]);
        }

        foreach (DB::table('settings')->cursor() as $row) {
            if ($row->value === null || $row->value === '') {
                continue;
            }

            $v = $row->value;
            $new = str_replace(
                ['OEM·HUB.', 'OEMHUB · EU', 'OEMHub EU GmbH'],
                ['Oe·Parts.', 'OeParts · EU', 'OeParts UAB'],
                $v
            );
            $new = str_replace('OEMHub', 'OeParts', $new);

            if ($new !== $v) {
                DB::table('settings')->where('id', $row->id)->update([
                    'value' => $new,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        // Intentionally empty — rebrand is one-way for production data.
    }
};

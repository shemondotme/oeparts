<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Idempotent + reversible (rule #42).
return new class extends Migration
{
    public function up(): void
    {
        // A prior job retry (or a duplicate dispatch) could already have
        // inserted more than one recipient row per (campaign_id,
        // subscriber_id) pair before this constraint existed — collapse
        // those down to one row each, preferring a 'sent' row over
        // 'pending'/'failed'/'bounced' so a real send isn't lost, before
        // adding the unique index.
        $duplicateGroups = DB::table('newsletter_campaign_recipients')
            ->select('campaign_id', 'subscriber_id')
            ->groupBy('campaign_id', 'subscriber_id')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateGroups as $group) {
            $rows = DB::table('newsletter_campaign_recipients')
                ->where('campaign_id', $group->campaign_id)
                ->where('subscriber_id', $group->subscriber_id)
                ->orderByRaw("FIELD(status, 'sent', 'failed', 'bounced', 'pending')")
                ->orderBy('id')
                ->get();

            $keep = $rows->first();
            $idsToDelete = $rows->skip(1)->pluck('id');

            if ($idsToDelete->isNotEmpty()) {
                DB::table('newsletter_campaign_recipients')->whereIn('id', $idsToDelete)->delete();
            }
        }

        if (! Schema::hasIndex('newsletter_campaign_recipients', ['campaign_id', 'subscriber_id'], 'unique')) {
            Schema::table('newsletter_campaign_recipients', function (Blueprint $table) {
                $table->unique(['campaign_id', 'subscriber_id']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('newsletter_campaign_recipients', ['campaign_id', 'subscriber_id'], 'unique')) {
            Schema::table('newsletter_campaign_recipients', function (Blueprint $table) {
                $table->dropUnique(['campaign_id', 'subscriber_id']);
            });
        }
    }
};

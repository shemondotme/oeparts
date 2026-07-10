<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The admin Reply action only ever emailed the customer — nothing recorded
 * what was said, by whom, or when. These columns give replies an audit trail
 * (Expand-only, rule #43; idempotent + reversible, rule #42).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            if (! Schema::hasColumn('contact_messages', 'reply_body')) {
                $table->text('reply_body')->nullable()->after('status');
            }
            if (! Schema::hasColumn('contact_messages', 'replied_at')) {
                $table->timestamp('replied_at')->nullable()->after('reply_body');
            }
            if (! Schema::hasColumn('contact_messages', 'replied_by')) {
                $table->foreignId('replied_by')->nullable()->after('replied_at')->constrained('admins')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('contact_messages', function (Blueprint $table) {
            if (Schema::hasColumn('contact_messages', 'replied_by')) {
                $table->dropConstrainedForeignId('replied_by');
            }
            if (Schema::hasColumn('contact_messages', 'replied_at')) {
                $table->dropColumn('replied_at');
            }
            if (Schema::hasColumn('contact_messages', 'reply_body')) {
                $table->dropColumn('reply_body');
            }
        });
    }
};

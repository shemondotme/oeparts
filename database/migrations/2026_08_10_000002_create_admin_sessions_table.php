<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Laravel's stock sessions.user_id is populated by the DatabaseSessionHandler
 * from the app's DEFAULT guard (config('auth.defaults.guard') — 'web',
 * i.e. customers), never the 'admin' guard. EventServiceProvider::onLogin()
 * used to try to "invalidate this admin's other sessions" by deleting
 * sessions.user_id = $admin->id — during an admin-only browsing session
 * that column is NULL (no web-guard login happened), so the query matched
 * zero rows in the common case, and in the pathological case where a
 * customer happens to share the same numeric ID, it would delete that
 * unrelated customer's session instead. This table tracks admin-guard
 * sessions explicitly so that feature actually works.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('session_id')->unique();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_sessions');
    }
};

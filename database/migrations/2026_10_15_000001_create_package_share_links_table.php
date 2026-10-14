<?php

use App\Support\Database\SafeSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Site migration — one-time download links for exported packages (served from the SOURCE site at /_pkg/{token}).
 *
 * Only the sha256 of a token is stored, never the token. `state` moves active -> claimed (the first request) -> consumed
 * (the whole file was delivered) or expired/revoked. The claim secret that lets the same downloader resume is kept as a
 * hash too. The table is excluded from backups (a restore must not bring back a consumed link) and belongs to the
 * `system` group of the migration registry (it never travels in a package).
 *
 * Idempotent (existence-checked + SafeSchema) like every other migration here.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('package_share_links')) {
            return;
        }

        SafeSchema::create('create_package_share_links', 'package_share_links', function (Blueprint $table) {
            $table->id();
            $table->char('token_hash', 64)->unique();
            $table->string('package_name', 190);                    // a file in the exports folder; the only place a file name comes from
            $table->unsignedBigInteger('package_size');
            $table->char('package_sha256', 64)->nullable();         // for the share string only
            $table->string('state', 16)->default('active');         // active | claimed | consumed | expired | revoked
            $table->timestamp('expires_at');
            $table->string('bound_ip', 45)->nullable();             // best effort (a proxy can hide the real address)
            $table->char('claim_hash', 64)->nullable();
            $table->timestamp('claim_expires_at')->nullable();
            $table->timestamp('first_claimed_at')->nullable();
            $table->timestamp('consumed_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedBigInteger('bytes_served')->default(0);
            $table->unsignedInteger('deny_count')->default(0);
            $table->unsignedBigInteger('created_by_admin_id')->nullable();
            $table->string('note', 190)->nullable();
            $table->timestamps();

            $table->index(['state', 'expires_at'], 'package_share_links_state_expires_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('package_share_links');
    }
};

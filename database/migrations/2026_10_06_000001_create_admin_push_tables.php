<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Admin Web Push (installable admin PWA + browser/OS notifications).
 *
 *  - admin_push_subscriptions: one row per admin device/browser.
 *  - push_topics:              admin-controllable catalogue of notification kinds
 *                              (enable, urgency, sound, allowed roles). New kinds
 *                              are auto-discovered the first time they are sent.
 *  - admin_push_preferences:   each admin's own switches, quiet hours, overrides.
 *  - admin_push_deferred:      non-urgent pushes held back during quiet hours.
 *  - admin_push_deliveries:    delivery log (stats + debugging), pruned daily.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->text('endpoint');
            $table->char('endpoint_hash', 64)->unique();
            $table->string('public_key', 255);
            $table->string('auth_token', 255);
            $table->string('content_encoding', 20)->default('aes128gcm');
            $table->string('user_agent', 255)->nullable();
            $table->string('device_label', 120)->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->unsignedSmallInteger('failure_count')->default(0);
            $table->timestamps();
        });

        Schema::create('push_topics', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->string('label', 150);
            $table->string('group', 60)->default('General');
            $table->string('description', 255)->nullable();
            $table->string('urgency', 10)->default('normal'); // normal | urgent
            $table->boolean('push_enabled')->default(true);
            $table->boolean('sound_enabled')->default(true);
            $table->json('allowed_roles')->nullable(); // null = every admin role
            $table->boolean('is_auto')->default(false);
            $table->unsignedSmallInteger('sort')->default(100);
            $table->timestamps();
        });

        Schema::create('admin_push_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->unique()->constrained('admins')->cascadeOnDelete();
            $table->boolean('push_enabled')->default(true);
            $table->boolean('sound_enabled')->default(true);
            $table->boolean('hide_details')->nullable(); // null = follow the site-wide default
            $table->boolean('quiet_enabled')->default(false);
            $table->string('quiet_start', 5)->default('22:00');
            $table->string('quiet_end', 5)->default('08:00');
            $table->json('topic_overrides')->nullable(); // [topic_key => bool]
            $table->timestamps();
        });

        Schema::create('admin_push_deferred', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->string('topic', 100);
            $table->string('title', 255);
            $table->string('body', 500)->nullable();
            $table->string('url', 500)->nullable();
            $table->timestamp('created_at')->nullable();
            $table->index(['admin_id', 'created_at']);
        });

        Schema::create('admin_push_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('admins')->cascadeOnDelete();
            $table->unsignedBigInteger('subscription_id')->nullable();
            $table->string('topic', 100)->nullable();
            $table->string('status', 10); // sent | failed | expired
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->string('error', 255)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_push_deliveries');
        Schema::dropIfExists('admin_push_deferred');
        Schema::dropIfExists('admin_push_preferences');
        Schema::dropIfExists('push_topics');
        Schema::dropIfExists('admin_push_subscriptions');
    }
};

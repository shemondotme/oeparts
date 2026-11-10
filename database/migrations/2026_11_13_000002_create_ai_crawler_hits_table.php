<?php

use App\Support\Database\SafeSchema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Daily visit counts of the AI crawlers (GPTBot, ClaudeBot, PerplexityBot…) — one row per bot per day, never one
 * per request, so the table stays tiny however busy the crawlers are. No addresses, paths or visitor data.
 * Idempotent via SafeSchema.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('ai_crawler_hits')) {
            return;
        }

        SafeSchema::create('create_ai_crawler_hits_table', 'ai_crawler_hits', function (Blueprint $table) {
            $table->id();
            $table->date('hit_date');
            $table->string('bot', 40);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_seen_at')->nullable();
            $table->unique(['hit_date', 'bot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_crawler_hits');
    }
};

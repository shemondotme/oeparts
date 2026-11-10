<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The phrase a written page or post should be found for, per language — the
 * input of the on-page content analysis in the editor. Optional; nothing on
 * the public site reads it. Both tables are small (CMS pages, blog posts).
 * Idempotent + reversible.
 */
return new class extends Migration
{
    private const TABLES = ['pages', 'blog_posts'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'focus_keyword')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->json('focus_keyword')->nullable();
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasColumn($table, 'focus_keyword')) {
                Schema::table($table, function (Blueprint $blueprint) {
                    $blueprint->dropColumn('focus_keyword');
                });
            }
        }
    }
};

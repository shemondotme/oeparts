<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * menu_items.url was NOT NULL, but a 'page'-type item (added later,
 * alongside page_id, to let a menu item link to a CMS Page instead of a
 * raw URL) has no static URL to store — its target is resolved dynamically
 * from the page relationship. The admin form's url TextInput is only
 * ->hidden() when type=page, not ->dehydrated(false), so it still submits
 * url=null — every attempt to create/save a page-type menu item via the
 * admin panel crashed with a raw NOT NULL constraint violation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('url', 500)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('url', 500)->nullable(false)->change();
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * testimonials.rating was a bare tinyInteger with no range constraint —
 * the admin form's Select already only offers 1-5, but nothing at the DB
 * level stopped a future API/import path from inserting an out-of-range
 * value, which would throw a raw ValueError the moment
 * str_repeat('★', $rating) ran (TestimonialResource's table column and
 * ViewTestimonial both format the rating this way — str_repeat() rejects a
 * negative count).
 */
return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            // SQLite has no ALTER TABLE ... ADD CONSTRAINT; only a full
            // rebuild can add a CHECK to an existing table, which isn't
            // worth it for the test DB — the app's own MySQL migration path
            // is what matters here, and out-of-range ratings can't reach
            // this table any other way in the test suite.
            return;
        }

        DB::statement('ALTER TABLE testimonials ADD CONSTRAINT testimonials_rating_range CHECK (rating BETWEEN 1 AND 5)');
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return;
        }

        DB::statement('ALTER TABLE testimonials DROP CONSTRAINT testimonials_rating_range');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('language_strings', function (Blueprint $table) {
            $table->id();
            $table->string('lang_code', 5);
            $table->string('group', 100);
            $table->string('key', 200);
            $table->text('value');
            $table->timestamps();

            $table->unique(['lang_code', 'group', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('language_strings');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate existing string labels to JSON format before changing column type
        DB::table('menu_items')->get()->each(function ($item) {
            $existing = $item->label;
            if ($existing && !$this->isJson($existing)) {
                DB::table('menu_items')->where('id', $item->id)->update([
                    'label' => json_encode(['en' => $existing, 'de' => null, 'lt' => null, 'fr' => null, 'es' => null]),
                ]);
            }
        });

        Schema::table('menu_items', function (Blueprint $table) {
            $table->json('label')->nullable()->change();
            $table->string('type', 20)->default('url')->after('label');
            $table->foreignId('page_id')->nullable()->after('type')->constrained('pages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->string('label', 100)->change();
            $table->dropForeign(['page_id']);
            $table->dropColumn(['type', 'page_id']);
        });
    }

    private function isJson(string $str): bool
    {
        json_decode($str);
        return json_last_error() === JSON_ERROR_NONE;
    }
};

<?php

use App\Models\Setting;
use App\Services\UiCopyInstaller;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        (new UiCopyInstaller)->installSettings();
    }

    public function down(): void
    {
        $keys = UiCopyInstaller::installedUiKeyPrefixes();
        foreach ($keys as $k) {
            Setting::where('group', 'ui')->where('key', $k)->delete();
        }
    }
};

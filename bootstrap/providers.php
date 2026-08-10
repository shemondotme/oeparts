<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\SettingsSyncServiceProvider;
use App\Providers\TranslationOverrideServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    AdminPanelProvider::class,
    SettingsSyncServiceProvider::class,
    TranslationOverrideServiceProvider::class,
];

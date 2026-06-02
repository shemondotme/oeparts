<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\SettingsSyncServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    SettingsSyncServiceProvider::class,
];

<?php

namespace App\Filament\Support;

trait HasDrilldownFilters
{
    protected $queryString = [
        'tableFilters' => ['except' => []],
        'tableSort' => ['except' => ''],
        'tableSearch' => ['except' => ''],
    ];
}

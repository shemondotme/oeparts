<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    use HasFactory;

    protected $fillable = [
        'country_code',
        'country_name',
        'rate',
        'is_active',
        'notes',
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (TaxRate $rate): void {
            $rate->country_code = strtoupper($rate->country_code);
        });
    }
}

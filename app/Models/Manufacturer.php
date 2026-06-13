<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Manufacturer extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'slug', 'logo_id', 'country_code',
        'is_active', 'is_verified_oem', 'sort_order',
    ];

    protected $casts = [
        'name'            => 'array',
        'is_active'       => 'boolean',
        'is_verified_oem' => 'boolean',
    ];

    public function logo(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'logo_id');
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function carModels(): HasMany
    {
        return $this->hasMany(CarModel::class);
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}

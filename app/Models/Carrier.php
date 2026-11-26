<?php

namespace App\Models;

use App\Services\CacheService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Carrier extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'tracking_url', 'is_active', 'sort_order'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Names of the carriers enabled in the admin (Carriers > Active), in the
     * admin's display order. The single source for every carrier list the
     * storefront shows; cached, and cleared by CarrierObserver.
     *
     * @return list<string>
     */
    public static function activeNames(): array
    {
        return app(CacheService::class)->rememberActiveCarriers(
            fn (): array => static::query()
                ->active()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->pluck('name')
                ->map(fn ($name) => trim((string) $name))
                ->filter()
                ->values()
                ->all()
        );
    }
}

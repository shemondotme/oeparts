<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CarModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'manufacturer_id', 'name', 'slug',
        'year_from', 'year_to', 'is_active', 'sort_order',
        'meta_title', 'meta_description', 'intro',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'meta_title' => 'array',
        'meta_description' => 'array',
        'intro' => 'array',
    ];

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_car_models');
    }

    /** Advanced SEO overrides (canonical, social image, robots) — the polymorphic seo_meta row. */
    public function seoMeta(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'metable');
    }
}

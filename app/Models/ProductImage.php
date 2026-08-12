<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id', 'path', 'thumbnail_path', 'medium_path',
        'alt_text', 'is_featured', 'sort_order',
    ];

    protected $casts = [
        'alt_text' => 'array',
        'is_featured' => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Falls back to the original upload when ProcessProductImage hasn't
     * run yet (or GD isn't available) — the gallery must never show a
     * broken thumbnail, only temporarily serve full-size.
     */
    public function getThumbnailUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->thumbnail_path ?: $this->path);
    }

    public function getMediumUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->medium_path ?: $this->path);
    }

    public function getUrlAttribute(): string
    {
        return Storage::disk('public')->url($this->path);
    }
}

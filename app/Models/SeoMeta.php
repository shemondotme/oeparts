<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SeoMeta extends Model
{
    protected $table = 'seo_meta';

    protected $fillable = [
        'metable_type', 'metable_id', 'meta_title', 'meta_description',
        'canonical_url', 'og_title', 'og_description', 'og_image_id', 'robots',
    ];

    public function metable(): MorphTo
    {
        return $this->morphTo();
    }

    public function ogImage(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'og_image_id');
    }
}

<?php

namespace App\Models;

use App\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Page extends Model
{
    use HasFactory;

    protected $fillable = [
        'title', 'slug', 'content', 'featured_image_id', 'status',
        'meta_title', 'meta_description', 'is_homepage', 'is_header',
        'is_footer', 'created_by', 'published_at',
    ];

    protected $casts = [
        'title'            => 'array',
        'content'          => 'array',
        'meta_title'       => 'array',
        'meta_description' => 'array',
        'status'           => ContentStatus::class,
        'is_homepage'      => 'boolean',
        'is_header'        => 'boolean',
        'is_footer'        => 'boolean',
        'published_at'     => 'datetime',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'featured_image_id');
    }
}

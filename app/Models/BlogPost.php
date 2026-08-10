<?php

namespace App\Models;

use App\Enums\ContentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

class BlogPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'category_id', 'title', 'slug', 'excerpt', 'content',
        'featured_image_id', 'author_id', 'status',
        'meta_title', 'meta_description', 'published_at', 'last_reviewed_at',
    ];

    protected $casts = [
        'title'            => 'array',
        'excerpt'          => 'array',
        'content'          => 'array',
        'meta_title'       => 'array',
        'meta_description' => 'array',
        'status'           => ContentStatus::class,
        'published_at'     => 'datetime',
        'last_reviewed_at' => 'date',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function featuredImage(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'featured_image_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'author_id');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(BlogTag::class, 'blog_post_tags', 'post_id', 'tag_id');
    }

    public function seoMeta(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'metable');
    }

    /**
     * status=Published alone isn't "live" — a post can be scheduled with a
     * future published_at. BlogController's own list/show queries always
     * added a separate published_at <= now() check on top of this scope,
     * but callers that used ONLY this scope (the blog index sidebar's
     * category/tag counts) showed a future-scheduled post's category/tag as
     * already having content — a non-zero count and a working-looking
     * filter link that led to an empty/404 result until the schedule hit.
     */
    public function scopePublished($q)
    {
        return $q->where('status', ContentStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }
}

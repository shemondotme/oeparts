<?php

namespace App\Models;

use App\Enums\ContentStatus;
use App\Support\MenuRegistry;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;

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

    public function seoMeta(): MorphOne
    {
        return $this->morphOne(SeoMeta::class, 'metable');
    }

    protected static function booted(): void
    {
        // Single-homepage invariant, mirroring Language::is_default — the
        // form's own helper text already promises "Only one page can be
        // set as the homepage. This will override the current homepage,"
        // but nothing enforced it, so two pages could both carry the flag
        // with no defined winner.
        static::saved(function (Page $page): void {
            // Not gated on wasChanged(): a page mass-assigned is_homepage
            // at creation time (not just toggled later via an update) must
            // enforce the invariant too, and this query is cheap enough to
            // not need the optimization.
            if ($page->is_homepage) {
                static::whereKeyNot($page->getKey())
                    ->where('is_homepage', true)
                    ->update(['is_homepage' => false]);
            }
        });

        static::saved(fn () => MenuRegistry::forgetPageFlagged());
        static::deleted(fn () => MenuRegistry::forgetPageFlagged());
    }
}

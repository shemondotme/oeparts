<?php

namespace App\Models;

use App\Enums\SectionLocation;
use App\Enums\SectionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Section extends Model
{
    use HasFactory;

    /**
     * The single source of truth for section types: one entry per storefront
     * component in resources/views/components/sections/. The home page
     * renders via @includeIf('components.sections.' . $type), which silently
     * skips unknown types — so the admin MUST offer exactly these values.
     * A test asserts every DB type and every blade component stays in sync.
     *
     * @var array<string, string>
     */
    public const TYPES = [
        'hero'             => 'Hero (search + specification panel)',
        'trust_bar'        => 'Trust Bar',
        'featured_brands'  => 'Featured Brands',
        'how_it_works'     => 'How It Works',
        'popular_searches' => 'Popular Searches',
        'stats_counter'    => 'Stats Counter',
        'testimonials'     => 'Testimonials',
        'blog_preview'     => 'Blog Preview',
        'shipping_info'    => 'Shipping Info',
        'faqs'             => 'FAQs Accordion',
        'part_inquiry'     => 'Part Inquiry',
        'contact_cta'      => 'Contact CTA',
        'newsletter'       => 'Newsletter',
        'banner'           => 'Banner / B2B Strip',
    ];

    protected $fillable = [
        'type', 'location', 'title', 'content', 'is_active', 'status', 'publish_at', 
        'published_by', 'updated_by', 'sort_order',
    ];

    protected $casts = [
        'location'      => SectionLocation::class,
        'status'        => SectionStatus::class,
        'title'         => 'array',
        'content'       => 'array',
        'is_active'     => 'boolean',
        'publish_at'    => 'datetime',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    /**
     * Admin who published this section.
     */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'published_by');
    }

    /**
     * Admin who last updated this section.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'updated_by');
    }

    /**
     * Version history of this section.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(SectionVersion::class)->latest('created_at');
    }

    /**
     * Save a version snapshot.
     */
    public function saveVersion(string $action = 'updated', ?int $adminId = null, ?string $summary = null): SectionVersion
    {
        return $this->versions()->create([
            'created_by'     => $adminId ?? auth('admin')->id(),
            'action'         => $action,
            'snapshot'       => $this->toArray(),
            'change_summary' => $summary,
            'created_at'     => now(),
        ]);
    }

    /**
     * Restore from a version snapshot.
     */
    public function restoreFromVersion(SectionVersion $version): bool
    {
        $snapshot = $version->snapshot;
        unset($snapshot['id'], $snapshot['created_at'], $snapshot['updated_at']);

        // Cast location/status back if they are enum values
        if (isset($snapshot['location'])) {
            $snapshot['location'] = $snapshot['location']['value'] ?? $snapshot['location'];
        }
        if (isset($snapshot['status'])) {
            $snapshot['status'] = $snapshot['status']['value'] ?? $snapshot['status'];
        }

        return $this->update($snapshot);
    }

    /**
     * Scope: only published sections.
     */
    public function scopePublished($query)
    {
        return $query->where('status', SectionStatus::Published)
                     ->where(function ($q) {
                         $q->whereNull('publish_at')
                           ->orWhere('publish_at', '<=', now());
                     });
    }

    /**
     * Scope: only draft sections.
     */
    public function scopeDraft($query)
    {
        return $query->where('status', SectionStatus::Draft);
    }

    /**
     * Scope: only scheduled sections.
     */
    public function scopeScheduled($query)
    {
        return $query->where('status', SectionStatus::Scheduled)
                     ->where('publish_at', '>', now());
    }

    /**
     * Check if section should be displayed frontend.
     */
    public function isVisible(): bool
    {
        // Only published sections are visible
        if ($this->status !== SectionStatus::Published) {
            return false;
        }

        // If scheduled (should not happen in Published status, but check anyway)
        if ($this->publish_at && $this->publish_at > now()) {
            return false;
        }

        return true;
    }

    /**
     * Publish this section immediately.
     */
    public function publish(?int $adminId = null): bool
    {
        $this->update([
            'status' => SectionStatus::Published,
            'publish_at' => now(),
            'published_by' => $adminId ?? auth('admin')->id(),
        ]);

        return true;
    }

    /**
     * Archive this section.
     */
    public function archive(): bool
    {
        $this->update(['status' => SectionStatus::Archived]);
        return true;
    }
}

<?php

namespace App\Models;

use App\Enums\RedirectType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Redirect extends Model
{
    use HasFactory;

    public const MATCH_EXACT = 'exact';

    public const MATCH_WILDCARD = 'wildcard';

    public const MATCH_REGEX = 'regex';

    /** Cache key holding every active wildcard/regex rule (HandleRedirects). */
    public const PATTERN_CACHE_KEY = 'redirect.patterns';

    protected $fillable = [
        'from_url', 'to_url', 'match_type', 'group_name', 'type', 'is_active', 'expires_at', 'hit_count',
    ];

    protected $casts = [
        'type' => RedirectType::class,
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
    ];

    /** A rule that is switched on AND has not passed its expiry. */
    public function scopeActive($q)
    {
        return $q->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function isPattern(): bool
    {
        return in_array($this->match_type, [self::MATCH_WILDCARD, self::MATCH_REGEX], true);
    }

    /**
     * HandleRedirects looks up Request::path(), which Laravel already
     * strips of leading/trailing slashes and never re-cases — but the
     * admin form's own placeholder text ("e.g. /old-page or /en/old-path")
     * told admins to enter a leading slash, and nothing normalized case
     * either. A redirect saved by following that exact guidance, or with
     * mixed case, silently never matched the lookup key.
     *
     * A REGEX rule is left alone apart from trimming whitespace: lower-casing
     * it would change what it means (\D vs \d, \S vs \s) — it is matched
     * case-insensitively instead.
     */
    protected static function booted(): void
    {
        static::saving(function (Redirect $redirect) {
            // A 410 (Gone) rule has no destination. The form hides the field for it, and a hidden field is not
            // submitted at all — so the value arrives as null for a NOT NULL column. Stored as empty.
            if ($redirect->to_url === null) {
                $redirect->to_url = '';
            }

            if ($redirect->match_type === self::MATCH_REGEX) {
                if ($redirect->isDirty('from_url')) {
                    $redirect->from_url = trim($redirect->from_url);
                }

                return;
            }

            if ($redirect->isDirty('from_url')) {
                $redirect->from_url = strtolower(trim($redirect->from_url, '/'));
            }
        });

        // HandleRedirects caches both hits AND the "no redirect" miss under
        // "redirect.{path}" for 60s. Without busting it here, a brand-new
        // or just-edited redirect could keep serving the old
        // destination — or serve nothing at all — for up to a minute after
        // an admin saves it, and a deleted redirect could keep firing.
        static::saved(function (Redirect $redirect) {
            Cache::forget('redirect.'.$redirect->from_url);
            Cache::forget(self::PATTERN_CACHE_KEY);

            $original = $redirect->getOriginal('from_url');
            if ($original && $original !== $redirect->from_url) {
                Cache::forget('redirect.'.$original);
            }
        });

        static::deleted(function (Redirect $redirect) {
            Cache::forget('redirect.'.$redirect->from_url);
            Cache::forget(self::PATTERN_CACHE_KEY);
        });
    }
}

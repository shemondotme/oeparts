<?php

namespace App\Models;

use App\Enums\RedirectType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Redirect extends Model
{
    use HasFactory;

    protected $fillable = [
        'from_url', 'to_url', 'type', 'is_active', 'hit_count',
    ];

    protected $casts = [
        'type'      => RedirectType::class,
        'is_active' => 'boolean',
    ];

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    /**
     * HandleRedirects looks up Request::path(), which Laravel already
     * strips of leading/trailing slashes and never re-cases — but the
     * admin form's own placeholder text ("e.g. /old-page or /en/old-path")
     * told admins to enter a leading slash, and nothing normalized case
     * either. A redirect saved by following that exact guidance, or with
     * mixed case, silently never matched the lookup key.
     */
    protected static function booted(): void
    {
        static::saving(function (Redirect $redirect) {
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

            $original = $redirect->getOriginal('from_url');
            if ($original && $original !== $redirect->from_url) {
                Cache::forget('redirect.'.$original);
            }
        });

        static::deleted(function (Redirect $redirect) {
            Cache::forget('redirect.'.$redirect->from_url);
        });
    }
}

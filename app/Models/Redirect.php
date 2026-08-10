<?php

namespace App\Models;

use App\Enums\RedirectType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
    }
}

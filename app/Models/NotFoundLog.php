<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * NotFoundLog — one row per distinct 404 path (SEO Module), deduplicated by
 * path_hash so a repeatedly-hit dead link accumulates a hit_count instead of
 * flooding the table with one row per request. Recorded from bootstrap/app.php's
 * NotFoundHttpException renderable hook.
 */
class NotFoundLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'path', 'path_hash', 'lang', 'referer', 'ip_address',
        'hit_count', 'resolved', 'first_seen_at', 'last_seen_at',
    ];

    protected $casts = [
        'resolved' => 'boolean',
        'hit_count' => 'integer',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
    ];

    /** Record one hit against $path, creating or incrementing the deduplicated row. */
    public static function recordHit(string $path, ?string $lang, ?string $referer, ?string $ip): void
    {
        $path = mb_substr($path, 0, 500);
        $hash = hash('sha256', $path);
        $referer = $referer ? mb_substr($referer, 0, 500) : null;

        $existing = static::query()->where('path_hash', $hash)->first();

        if ($existing) {
            $existing->increment('hit_count', 1, [
                'lang' => $lang,
                'referer' => $referer,
                'ip_address' => $ip,
                'last_seen_at' => now(),
            ]);

            return;
        }

        try {
            static::query()->create([
                'path' => $path,
                'path_hash' => $hash,
                'lang' => $lang,
                'referer' => $referer,
                'ip_address' => $ip,
                'hit_count' => 1,
                'resolved' => false,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
            ]);
        } catch (\Illuminate\Database\QueryException) {
            // Race: a concurrent request inserted this path_hash first.
            static::query()->where('path_hash', $hash)->increment('hit_count', 1, ['last_seen_at' => now()]);
        }
    }
}

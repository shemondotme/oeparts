<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SearchLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'search_query', 'normalized_query', 'result_count',
        'manufacturer_id', 'car_model_id', 'lang', 'user_id', 'ip_address',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * created_at is deliberately not mass-assignable, and $timestamps =
     * false means Eloquent won't auto-populate it either — without this,
     * every SearchLog::create() call silently persisted a NULL created_at,
     * which broke the "popular OEMs" suggestion query (filters on
     * created_at) and the admin log's default sort. Mirrors ActivityLog's
     * same fix for the same shape (created_at only, no updated_at column).
     */
    protected static function booted(): void
    {
        static::creating(function (SearchLog $log) {
            $log->created_at ??= now();
        });
    }

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function carModel(): BelongsTo
    {
        return $this->belongsTo(CarModel::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FailedSearchLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'search_query', 'normalized_query', 'lang',
        'user_id', 'ip_address', 'inquiry_submitted',
    ];

    protected $casts = [
        'inquiry_submitted' => 'boolean',
        'created_at' => 'datetime',
    ];

    /**
     * See the matching comment on SearchLog — same shape (created_at only,
     * no updated_at column), same silent-NULL bug: the admin "failed
     * searches today" navigation badge filters on created_at and was
     * permanently stuck at zero.
     */
    protected static function booted(): void
    {
        static::creating(function (FailedSearchLog $log) {
            $log->created_at ??= now();
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function partInquiries(): HasMany
    {
        return $this->hasMany(PartInquiry::class);
    }
}

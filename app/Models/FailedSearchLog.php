<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FailedSearchLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'search_query', 'normalized_query', 'lang',
        'user_id', 'ip_address', 'inquiry_submitted',
    ];

    protected $casts = [
        'inquiry_submitted' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function partInquiries(): HasMany
    {
        return $this->hasMany(PartInquiry::class);
    }
}

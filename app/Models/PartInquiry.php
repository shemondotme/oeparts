<?php

namespace App\Models;

use App\Enums\PartInquiryStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

class PartInquiry extends Model
{
    use HasFactory;

    protected $fillable = [
        'failed_search_log_id', 'email', 'phone', 'oem_number',
        'manufacturer', 'car_model', 'year', 'vin_number',
        'quantity', 'urgency', 'notes', 'status', 'admin_note', 'ip_address',
    ];

    protected $casts = [
        'status' => PartInquiryStatus::class,
        'quantity' => 'integer',
    ];

    public function failedSearchLog(): BelongsTo
    {
        return $this->belongsTo(FailedSearchLog::class);
    }

    public function scopeNew(Builder $q): Builder
    {
        return $q->where('status', PartInquiryStatus::New->value);
    }
}

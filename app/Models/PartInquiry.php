<?php

namespace App\Models;

use App\Enums\PartInquiryStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartInquiry extends Model
{
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
}

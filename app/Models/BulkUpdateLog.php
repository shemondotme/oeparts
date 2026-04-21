<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BulkUpdateLog extends Model
{
    protected $fillable = [
        'admin_id',
        'action_type',
        'target_manufacturer_id',
        'affected_rows_count',
        'payload',
        'entity_type',
        'filters',
        'updates',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'action_type' => \App\Enums\BulkUpdateAction::class,
        'payload' => 'array',
        'filters' => 'array',
        'updates' => 'array',
        'created_at' => 'datetime',
    ];

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }

    public function targetManufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class, 'target_manufacturer_id');
    }
}

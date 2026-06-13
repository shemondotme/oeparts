<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SavedView extends Model
{
    protected $fillable = [
        'admin_id', 'name', 'resource',
        'filters', 'sort_column', 'sort_direction', 'search',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
        ];
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SectionVersion extends Model
{
    use HasFactory;
    
    public $timestamps = false;

    protected $fillable = [
        'section_id', 'created_by', 'action', 'snapshot', 'change_summary', 'created_at',
    ];

    protected $casts = [
        'snapshot' => 'array',
        'created_at' => 'datetime',
    ];

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}

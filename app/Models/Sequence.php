<?php

namespace App\Models;

use App\Enums\SequenceType;
use Illuminate\Database\Eloquent\Model;

class Sequence extends Model
{
    protected $fillable = [
        'type', 'current_value', 'resets_monthly', 'last_reset_month',
    ];

    protected $casts = [
        'type'           => SequenceType::class,
        'resets_monthly' => 'boolean',
    ];
}

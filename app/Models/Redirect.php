<?php

namespace App\Models;

use App\Enums\RedirectType;
use Illuminate\Database\Eloquent\Model;

class Redirect extends Model
{
    protected $fillable = [
        'from_url', 'to_url', 'type', 'is_active', 'hit_count',
    ];

    protected $casts = [
        'type'      => RedirectType::class,
        'is_active' => 'boolean',
    ];
}

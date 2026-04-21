<?php

namespace App\Models;

use App\Enums\SectionLocation;
use Illuminate\Database\Eloquent\Model;

class Section extends Model
{
    protected $fillable = [
        'type', 'location', 'title', 'content', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'location'  => SectionLocation::class,
        'title'     => 'array',
        'content'   => 'array',
        'is_active' => 'boolean',
    ];
}

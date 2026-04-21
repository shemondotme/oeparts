<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Faq extends Model
{
    protected $fillable = ['category', 'question', 'answer', 'sort_order', 'is_active'];

    protected $casts = [
        'question'  => 'array',
        'answer'    => 'array',
        'is_active' => 'boolean',
    ];
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    use HasFactory;

    protected $fillable = [
        'name', 'company', 'location', 'quote', 'rating', 'is_active', 'sort_order',
    ];

    protected $casts = [
        'quote'     => 'array',
        'is_active' => 'boolean',
    ];
}

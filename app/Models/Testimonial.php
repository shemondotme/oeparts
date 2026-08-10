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

    /**
     * Backstops the DB-level CHECK constraint (MySQL only — SQLite's test
     * DB can't add one to an existing table) with a friendly exception
     * instead of a raw DB error. TestimonialResource's/ViewTestimonial's
     * star display does str_repeat('★', $rating), which throws a ValueError
     * on a negative count — an out-of-range rating must never reach save().
     */
    protected static function booted(): void
    {
        static::saving(function (Testimonial $testimonial) {
            if ($testimonial->rating < 1 || $testimonial->rating > 5) {
                throw new \InvalidArgumentException('Testimonial rating must be between 1 and 5.');
            }
        });
    }
}

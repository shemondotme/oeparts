<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    use HasFactory;

    protected $table = 'product_reviews';

    protected $fillable = [
        'product_id', 'reviewer_name', 'title', 'comment', 'rating', 'status', 'ip_address',
    ];

    protected $casts = [
        'rating' => 'integer',
    ];

    /**
     * Backstops the DB-level CHECK constraint (MySQL only — SQLite's test
     * DB can't add one to an existing table) with a friendly exception
     * instead of a raw DB error — same reasoning as Testimonial::booted().
     */
    protected static function booted(): void
    {
        static::saving(function (Review $review) {
            if ($review->rating < 1 || $review->rating > 5) {
                throw new \InvalidArgumentException('Review rating must be between 1 and 5.');
            }
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeApproved($q)
    {
        return $q->where('status', 'approved');
    }

    public function scopePending($q)
    {
        return $q->where('status', 'pending');
    }
}

<?php

namespace App\Models;

use App\Enums\InventoryChangeType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryLog extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'product_id', 'admin_id', 'change_type',
        'old_status', 'new_status', 'note',
    ];

    protected $casts = [
        'change_type' => InventoryChangeType::class,
        'old_status' => 'boolean',
        'new_status' => 'boolean',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // $timestamps is off and created_at is not fillable, so nothing ever set it:
        // every row had created_at = NULL, which made the "when" column blank on the
        // Inventory Log page and left no age to apply a retention policy to.
        static::creating(function (self $log): void {
            $log->created_at ??= now();
        });
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}

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
        'old_status'  => 'boolean',
        'new_status'  => 'boolean',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}

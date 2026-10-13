<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property OrderStatus $old_status
 * @property OrderStatus $new_status
 */
class OrderStatusHistory extends Model
{
    use HasFactory;

    const UPDATED_AT = null;

    protected $table = 'order_status_history';

    protected $fillable = [
        'order_id', 'admin_id', 'old_status', 'new_status', 'note',
    ];

    protected function casts(): array
    {
        return [
            'old_status' => OrderStatus::class,
            'new_status' => OrderStatus::class,
        ];
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** @return BelongsTo<Admin, $this> */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}

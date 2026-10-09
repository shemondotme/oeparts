<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'order_id', 'product_id', 'oem_number_snapshot',
        'manufacturer_snapshot', 'condition_snapshot',
        'quantity', 'unit_price', 'total_price', 'cost_price',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'total_price' => 'decimal:2',
        'cost_price' => 'decimal:2',
    ];

    /** Profit on this line (selling total minus quantity x cost), or null while the cost is unknown. */
    public function margin(): ?string
    {
        if ($this->cost_price === null) {
            return null;
        }

        return bcsub((string) $this->total_price, bcmul((string) $this->quantity, (string) $this->cost_price, 2), 2);
    }

    /** Margin as a percentage of the selling total, or null when unknown or the total is zero. */
    public function marginPercent(): ?string
    {
        $margin = $this->margin();

        if ($margin === null || bccomp((string) $this->total_price, '0', 2) <= 0) {
            return null;
        }

        return bcdiv(bcmul($margin, '100', 4), (string) $this->total_price, 1);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

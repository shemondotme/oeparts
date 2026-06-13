<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductCrossReference extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'product_id', 'cross_oem_number', 'normalized_cross_oem',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

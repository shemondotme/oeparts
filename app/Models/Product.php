<?php

namespace App\Models;

use App\Enums\ProductCondition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'manufacturer_id', 'oem_number', 'normalized_oem',
        'name', 'description', 'condition', 'price',
        'delivery_time', 'moq', 'is_in_stock', 'is_active',
    ];

    protected $casts = [
        'name'        => 'array',
        'description' => 'array',
        'condition'   => ProductCondition::class,
        'price'       => 'decimal:2',
        'is_in_stock' => 'boolean',
        'is_active'   => 'boolean',
    ];

    public function manufacturer(): BelongsTo
    {
        return $this->belongsTo(Manufacturer::class);
    }

    public function crossReferences(): HasMany
    {
        return $this->hasMany(ProductCrossReference::class);
    }

    public function carModels(): BelongsToMany
    {
        return $this->belongsToMany(CarModel::class, 'product_car_models');
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function inventoryLogs(): HasMany
    {
        return $this->hasMany(InventoryLog::class);
    }
}

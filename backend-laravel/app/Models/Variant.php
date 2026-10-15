<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Variant extends Model
{
    protected $fillable = [
        'commodity_id',
        'variant_name',
        'grade',
        'unit',
        'price',
        'stock_qty',
        'reserved_qty',
        'min_order',
        'weight_per_unit_kg',
        'version',
    ];

    protected $casts = [
        'price' => 'float',
        'stock_qty' => 'float',
        'reserved_qty' => 'float',
        'min_order' => 'float',
        'weight_per_unit_kg' => 'float',
        'version' => 'integer',
    ];

    public function commodity()
    {
        return $this->belongsTo(Commodity::class);
    }

    public function stockMovements()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function getAvailableStockAttribute(): float
    {
        return max(0, $this->stock_qty - $this->reserved_qty);
    }
}

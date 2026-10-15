<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    protected $fillable = [
        'order_id',
        'variant_id',
        'name_snapshot',
        'grade',
        'qty',
        'unit_price',
        'line_total',
    ];

    protected $casts = [
        'qty' => 'float',
        'unit_price' => 'float',
        'line_total' => 'float',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }
}

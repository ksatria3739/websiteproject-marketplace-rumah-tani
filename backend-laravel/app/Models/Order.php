<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    protected $fillable = [
        'code',
        'payment_id',
        'buyer_id',
        'seller_id',
        'region_id',
        'dest_region_id',
        'ship_address',
        'status',
        'subtotal',
        'tax',
        'admin_fee',
        'shipping_cost',
        'total',
        'total_weight_kg',
    ];

    protected $casts = [
        'subtotal' => 'float',
        'tax' => 'float',
        'admin_fee' => 'float',
        'shipping_cost' => 'float',
        'total' => 'float',
        'total_weight_kg' => 'float',
    ];

    public function payment()
    {
        return $this->belongsTo(Payment::class);
    }

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function originRegion()
    {
        return $this->belongsTo(Region::class, 'region_id');
    }

    public function destRegion()
    {
        return $this->belongsTo(Region::class, 'dest_region_id');
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function shipment()
    {
        return $this->hasOne(Shipment::class);
    }
}

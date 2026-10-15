<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Shipment extends Model
{
    protected $fillable = [
        'order_id',
        'vehicle_id',
        'route_id',
        'status',
        'tracking_code',
        'last_lat',
        'last_lng',
    ];

    protected $casts = [
        'last_lat' => 'float',
        'last_lng' => 'float',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function route()
    {
        return $this->belongsTo(Route::class);
    }

    public function events()
    {
        return $this->hasMany(ShipmentEvent::class)->orderBy('id', 'asc');
    }
}

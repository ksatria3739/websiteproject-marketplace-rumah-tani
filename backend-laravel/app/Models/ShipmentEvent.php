<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ShipmentEvent extends Model
{
    protected $fillable = [
        'shipment_id',
        'status',
        'note',
        'lat',
        'lng',
    ];

    protected $casts = [
        'lat' => 'float',
        'lng' => 'float',
    ];

    public function shipment()
    {
        return $this->belongsTo(Shipment::class);
    }
}

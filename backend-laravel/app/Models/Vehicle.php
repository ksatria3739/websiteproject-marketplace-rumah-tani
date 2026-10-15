<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = [
        'owner_id',
        'plate',
        'type',
        'capacity_kg',
        'driver_name',
        'is_available',
    ];

    protected $casts = [
        'capacity_kg' => 'float',
        'is_available' => 'boolean',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }
}

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

    protected $appends = [
        'plate_number',
        'model',
        'max_capacity_kg',
        'status',
    ];

    public function getPlateNumberAttribute()
    {
        return $this->plate;
    }

    public function getModelAttribute()
    {
        return $this->type;
    }

    public function getMaxCapacityKgAttribute()
    {
        return $this->capacity_kg;
    }

    public function getStatusAttribute()
    {
        return $this->is_available ? 'available' : 'busy';
    }

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function shipments()
    {
        return $this->hasMany(Shipment::class);
    }
}

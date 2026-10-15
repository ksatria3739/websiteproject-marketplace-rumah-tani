<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Route extends Model
{
    protected $fillable = [
        'owner_id',
        'origin_region_id',
        'dest_region_id',
        'base_fee',
        'fee_per_kg_km',
    ];

    protected $casts = [
        'base_fee' => 'float',
        'fee_per_kg_km' => 'float',
    ];

    public function owner()
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function originRegion()
    {
        return $this->belongsTo(Region::class, 'origin_region_id');
    }

    public function destRegion()
    {
        return $this->belongsTo(Region::class, 'dest_region_id');
    }
}

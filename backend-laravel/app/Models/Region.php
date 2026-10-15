<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Region extends Model
{
    protected $fillable = ['name', 'province', 'lat', 'lng'];

    public function commodities()
    {
        return $this->hasMany(Commodity::class);
    }
}

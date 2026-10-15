<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Commodity extends Model
{
    protected $fillable = [
        'farmer_id',
        'region_id',
        'category_id',
        'name',
        'slug',
        'description',
        'image_url',
        'curation',
        'curated_by',
        'curation_note',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function farmer()
    {
        return $this->belongsTo(User::class, 'farmer_id');
    }

    public function curator()
    {
        return $this->belongsTo(User::class, 'curated_by');
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function variants()
    {
        return $this->hasMany(Variant::class);
    }
}

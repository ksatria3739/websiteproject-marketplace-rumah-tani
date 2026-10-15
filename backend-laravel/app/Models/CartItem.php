<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CartItem extends Model
{
    protected $fillable = [
        'user_id',
        'variant_id',
        'qty',
        'negotiation_id',
    ];

    protected $casts = [
        'qty' => 'float',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }

    public function negotiation()
    {
        return $this->belongsTo(Negotiation::class);
    }
}

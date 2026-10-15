<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Negotiation extends Model
{
    protected $fillable = [
        'buyer_id',
        'seller_id',
        'variant_id',
        'qty',
        'status',
        'agreed_price',
        'expires_at',
    ];

    protected $casts = [
        'qty' => 'float',
        'agreed_price' => 'float',
        'expires_at' => 'datetime',
    ];

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function seller()
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }

    public function offers()
    {
        return $this->hasMany(NegotiationOffer::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NegotiationOffer extends Model
{
    protected $fillable = [
        'negotiation_id',
        'from_user_id',
        'unit_price',
        'message',
    ];

    protected $casts = [
        'unit_price' => 'float',
    ];

    public function negotiation()
    {
        return $this->belongsTo(Negotiation::class);
    }

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $fillable = [
        'buyer_id',
        'gateway_ref',
        'method',
        'amount',
        'status',
        'pay_code',
        'expires_at',
        'paid_at',
        'raw_webhook',
    ];

    protected $casts = [
        'amount' => 'float',
        'expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'raw_webhook' => 'array',
    ];

    public function buyer()
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}

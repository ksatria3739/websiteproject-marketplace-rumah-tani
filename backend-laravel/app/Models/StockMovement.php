<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'variant_id',
        'delta',
        'reason',
        'ref_id',
        'actor_id',
    ];

    protected $casts = [
        'delta' => 'float',
    ];

    public function variant()
    {
        return $this->belongsTo(Variant::class);
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

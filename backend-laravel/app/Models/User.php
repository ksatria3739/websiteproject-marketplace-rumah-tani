<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'phone',
        'role',
        'buyer_type',
        'company_name',
        'region_id',
        'address',
        'kyc_status',
        'kyc_note',
        'is_active',
        'token_version',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function region()
    {
        return $this->belongsTo(Region::class);
    }

    public function kycDocuments()
    {
        return $this->hasMany(KycDocument::class);
    }

    public function commodities()
    {
        return $this->hasMany(Commodity::class, 'farmer_id');
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    public function vehicles()
    {
        return $this->hasMany(Vehicle::class, 'owner_id');
    }

    public function routes()
    {
        return $this->hasMany(Route::class, 'owner_id');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isStaffAdmin(): bool
    {
        return in_array($this->role, ['super_admin', 'admin']);
    }

    public function isTengkulak(): bool
    {
        return $this->role === 'admin_tengkulak';
    }

    public function isPetani(): bool
    {
        return $this->role === 'petani';
    }

    public function isPembeli(): bool
    {
        return $this->role === 'pembeli';
    }
}

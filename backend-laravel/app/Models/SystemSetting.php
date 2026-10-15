<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = ['key', 'value', 'updated_by'];

    public static function getVal(string $key, $default = null)
    {
        $setting = static::find($key);
        return $setting ? $setting->value : $default;
    }

    public static function getAllSettings(): array
    {
        $all = static::all();
        $out = [
            'tax_rate' => 0.11,
            'admin_fee_rate' => 0.02,
            'admin_fee_min' => 2500,
            'ship_base_fee' => 15000,
            'ship_fee_per_kg_km' => 0.35,
            'payment_ttl_minutes' => 60,
        ];
        foreach ($all as $item) {
            $out[$item->key] = is_numeric($item->value) ? (float) $item->value : $item->value;
        }
        return $out;
    }
}

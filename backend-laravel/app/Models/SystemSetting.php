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
            'platform_fee_percent' => 2.0,
            'ppn_rate' => 11.0,
            'min_order_b2b_kg' => 50,
            'negotiation_expiry_days' => 3,
        ];
        foreach ($all as $item) {
            $val = is_numeric($item->value) ? (float) $item->value : $item->value;
            $out[$item->key] = $val;

            if ($item->key === 'platform_fee_percent') {
                $out['admin_fee_rate'] = ((float) $val) / 100.0;
            } elseif ($item->key === 'admin_fee_rate') {
                $out['platform_fee_percent'] = ((float) $val) * 100.0;
            } elseif ($item->key === 'ppn_rate') {
                $out['tax_rate'] = ((float) $val) / 100.0;
            } elseif ($item->key === 'tax_rate') {
                $out['ppn_rate'] = ((float) $val) * 100.0;
            }
        }
        return $out;
    }
}

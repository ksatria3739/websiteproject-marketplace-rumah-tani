<?php

namespace App\Services;

use App\Models\Region;
use App\Models\Route;
use App\Models\SystemSetting;

class PricingService
{
    const EARTH_RADIUS_KM = 6371;

    public static function distanceKm(Region $origin, Region $dest): float
    {
        if ($origin->id === $dest->id) {
            return 20.0; // intra-region base distance
        }

        $lat1 = deg2rad((float) $origin->lat);
        $lng1 = deg2rad((float) $origin->lng);
        $lat2 = deg2rad((float) $dest->lat);
        $lng2 = deg2rad((float) $dest->lng);

        $dlat = $lat2 - $lat1;
        $dlng = $lng2 - $lng1;

        $a = sin($dlat / 2) ** 2 + cos($lat1) * cos($lat2) * sin($dlng / 2) ** 2;
        $c = 2 * asin(sqrt($a));

        return round(self::EARTH_RADIUS_KM * $c, 2);
    }

    public static function calculateShippingCost(float $totalWeightKg, float $distanceKm, int $originRegionId, int $destRegionId): float
    {
        $settings = SystemSetting::getAllSettings();

        // Check if there is a specific custom regional route rate
        $route = Route::where('origin_region_id', $originRegionId)
            ->where('dest_region_id', $destRegionId)
            ->first();

        $baseFee = ($route && $route->base_fee > 0) ? $route->base_fee : $settings['ship_base_fee'];
        $ratePerKgKm = ($route && $route->fee_per_kg_km > 0) ? $route->fee_per_kg_km : $settings['ship_fee_per_kg_km'];

        // Road tortuosity factor ~1.3
        $rawCost = $baseFee + ($totalWeightKg * $distanceKm * 1.3 * $ratePerKgKm);

        // Round up to nearest Rp500
        return (float) (ceil($rawCost / 500) * 500);
    }

    public static function calculateTotals(float $subtotal, float $shippingCost): array
    {
        $settings = SystemSetting::getAllSettings();

        $tax = round($subtotal * $settings['tax_rate'], 2);
        $adminFee = round(max($subtotal * $settings['admin_fee_rate'], $settings['admin_fee_min']), 2);
        $grandTotal = round($subtotal + $tax + $adminFee + $shippingCost, 2);

        return [
            'subtotal' => $subtotal,
            'tax' => $tax,
            'admin_fee' => $adminFee,
            'shipping' => $shippingCost,
            'total' => $grandTotal,
        ];
    }
}

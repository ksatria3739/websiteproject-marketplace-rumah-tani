<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Region;
use App\Models\Route;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\Vehicle;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LogisticsController extends Controller
{
    const STATUS_FLOW = ['preparing', 'picked_up', 'in_transit', 'arrived_hub', 'out_for_delivery', 'delivered'];

    public function estimate(Request $request)
    {
        $validated = $request->validate([
            'weightKg' => 'required|numeric|min:0.1',
            'originRegionId' => 'required|exists:regions,id',
            'destRegionId' => 'required|exists:regions,id',
        ]);

        $origin = Region::findOrFail($validated['originRegionId']);
        $dest = Region::findOrFail($validated['destRegionId']);

        $distanceKm = PricingService::distanceKm($origin, $dest);
        $cost = PricingService::calculateShippingCost(
            (float) $validated['weightKg'],
            $distanceKm,
            $origin->id,
            $dest->id
        );

        return response()->json([
            'distanceKm' => $distanceKm,
            'cost' => $cost,
        ]);
    }

    public function track($code)
    {
        $shipment = Shipment::with(['order.originRegion', 'order.destRegion', 'vehicle', 'events'])
            ->where('tracking_code', $code)
            ->firstOrFail();

        $order = $shipment->order;

        return response()->json([
            'id' => $shipment->id,
            'tracking_code' => $shipment->tracking_code,
            'status' => $shipment->status,
            'last_lat' => $shipment->last_lat,
            'last_lng' => $shipment->last_lng,
            'updated_at' => $shipment->updated_at,
            'actual_weight_kg' => $order ? (float) $order->total_weight_kg : 0,
            'total_weight_kg' => $order ? (float) $order->total_weight_kg : 0,
            'order' => $order ? [
                'id' => $order->id,
                'order_number' => $order->code,
                'code' => $order->code,
            ] : null,
            'vehicle' => $shipment->vehicle ? [
                'id' => $shipment->vehicle->id,
                'plate' => $shipment->vehicle->plate,
                'plate_number' => $shipment->vehicle->plate,
                'type' => $shipment->vehicle->type,
                'model' => $shipment->vehicle->type,
                'capacity_kg' => (float) $shipment->vehicle->capacity_kg,
                'max_capacity_kg' => (float) $shipment->vehicle->capacity_kg,
                'driver_name' => $shipment->vehicle->driver_name,
                'driver_phone' => '-',
            ] : null,
            'plate' => $shipment->vehicle ? $shipment->vehicle->plate : null,
            'driver_name' => $shipment->vehicle ? $shipment->vehicle->driver_name : null,
            'origin' => $order && $order->originRegion ? $order->originRegion->name : '-',
            'destination' => $order && $order->destRegion ? $order->destRegion->name : '-',
            'o_lat' => $order && $order->originRegion ? (float) $order->originRegion->lat : -6.2,
            'o_lng' => $order && $order->originRegion ? (float) $order->originRegion->lng : 106.8,
            'd_lat' => $order && $order->destRegion ? (float) $order->destRegion->lat : -6.2,
            'd_lng' => $order && $order->destRegion ? (float) $order->destRegion->lng : 106.8,
            'events' => $shipment->events->map(fn($e) => [
                'id' => $e->id,
                'status' => $e->status,
                'note' => $e->note,
                'lat' => $e->lat,
                'lng' => $e->lng,
                'created_at' => $e->created_at,
            ]),
        ]);
    }

    public function listVehicles(Request $request)
    {
        $user = $request->user();
        $query = Vehicle::query();

        if (!in_array($user->role, ['super_admin', 'admin'])) {
            $query->where('owner_id', $user->id);
        }

        return response()->json($query->orderBy('plate')->get());
    }

    public function storeVehicle(Request $request)
    {
        $validated = $request->validate([
            'plate' => 'nullable|string|min:3',
            'plate_number' => 'nullable|string|min:3',
            'type' => 'nullable|string',
            'model' => 'nullable|string',
            'capacityKg' => 'nullable|numeric|min:10',
            'max_capacity_kg' => 'nullable|numeric|min:10',
            'driverName' => 'nullable|string',
            'driver_name' => 'nullable|string',
        ]);

        $plate = strtoupper(trim($validated['plate'] ?? $validated['plate_number'] ?? ''));
        if (empty($plate)) {
            return response()->json(['error' => 'Nomor polisi kendaraan wajib diisi.'], 422);
        }

        if (Vehicle::where('plate', $plate)->exists()) {
            return response()->json(['error' => 'Nomor polisi kendaraan sudah terdaftar di sistem.'], 422);
        }

        $type = trim($validated['type'] ?? $validated['model'] ?? 'Armada Angkut');
        $capacity = (float) ($validated['capacityKg'] ?? $validated['max_capacity_kg'] ?? 1000);
        $driver = trim($validated['driverName'] ?? $validated['driver_name'] ?? '');

        $vehicle = Vehicle::create([
            'owner_id' => $request->user()->id,
            'plate' => $plate,
            'type' => $type,
            'capacity_kg' => $capacity,
            'driver_name' => $driver ?: null,
            'is_available' => true,
        ]);

        return response()->json($vehicle, 201);
    }

    public function listRoutes(Request $request)
    {
        $user = $request->user();
        $query = Route::with(['originRegion', 'destRegion']);

        if (!in_array($user->role, ['super_admin', 'admin'])) {
            $query->where('owner_id', $user->id);
        }

        $routes = $query->get()->map(function ($r) {
            return [
                'id' => $r->id,
                'origin_region_id' => $r->origin_region_id,
                'dest_region_id' => $r->dest_region_id,
                'origin' => $r->originRegion->name,
                'destination' => $r->destRegion->name,
                'base_fee' => $r->base_fee,
                'fee_per_kg_km' => $r->fee_per_kg_km,
            ];
        });

        return response()->json($routes);
    }

    public function saveRoute(Request $request)
    {
        $validated = $request->validate([
            'originRegionId' => 'required|exists:regions,id',
            'destRegionId' => 'required|exists:regions,id',
            'baseFee' => 'required|numeric|min:0',
            'feePerKgKm' => 'required|numeric|min:0',
        ]);

        $user = $request->user();

        $route = Route::updateOrCreate(
            [
                'owner_id' => $user->id,
                'origin_region_id' => $validated['originRegionId'],
                'dest_region_id' => $validated['destRegionId'],
            ],
            [
                'base_fee' => $validated['baseFee'],
                'fee_per_kg_km' => $validated['feePerKgKm'],
            ]
        );

        return response()->json($route);
    }

    public function listShipments(Request $request)
    {
        $user = $request->user();
        $query = Shipment::with(['order.originRegion', 'order.destRegion', 'vehicle']);

        if ($user->role === 'admin_tengkulak') {
            $userRegion = Region::find($user->region_id);
            $query->whereHas('order', function ($o) use ($user, $userRegion) {
                $o->where('region_id', $user->region_id);
                if ($userRegion) {
                    $o->orWhereHas('originRegion', function ($r) use ($userRegion) {
                        $r->where('province', $userRegion->province);
                    });
                }
            });
        }

        $shipments = $query->latest('updated_at')->take(200)->get()->map(function ($s) {
            $order = $s->order;
            $originName = $order && $order->originRegion ? $order->originRegion->name : '-';
            $destName = $order && $order->destRegion ? $order->destRegion->name : '-';

            return [
                'id' => $s->id,
                'order_id' => $s->order_id,
                'order_code' => $order ? $order->code : '-',
                'tracking_code' => $s->tracking_code,
                'status' => $s->status,
                'updated_at' => $s->updated_at,
                'total_weight_kg' => $order ? (float)$order->total_weight_kg : 0,
                'actual_weight_kg' => $order ? (float)$order->total_weight_kg : 0,
                'vehicle_id' => $s->vehicle_id,
                'vehicle' => $s->vehicle ? [
                    'id' => $s->vehicle->id,
                    'plate' => $s->vehicle->plate,
                    'plate_number' => $s->vehicle->plate,
                    'type' => $s->vehicle->type,
                    'model' => $s->vehicle->type,
                    'capacity_kg' => (float) $s->vehicle->capacity_kg,
                    'max_capacity_kg' => (float) $s->vehicle->capacity_kg,
                    'driver_name' => $s->vehicle->driver_name,
                    'driver_phone' => '-',
                ] : null,
                'plate' => $s->vehicle ? $s->vehicle->plate : '-',
                'driver_name' => $s->vehicle ? $s->vehicle->driver_name : '-',
                'origin' => $originName,
                'destination' => $destName,
                'origin_region' => ['name' => $originName],
                'destination_region' => ['name' => $destName],
            ];
        });

        return response()->json($shipments);
    }

    public function allocateShipment(Request $request)
    {
        $orderId = $request->input('orderId', $request->input('order_id'));
        $shipmentId = $request->input('shipmentId', $request->input('shipment_id'));
        if (!$orderId && $shipmentId) {
            $sh = Shipment::find($shipmentId);
            if ($sh) {
                $orderId = $sh->order_id;
            }
        }
        $vehicleId = $request->input('vehicleId', $request->input('vehicle_id'));
        $routeId = $request->input('routeId', $request->input('route_id'));

        $request->merge([
            'orderId' => $orderId,
            'vehicleId' => $vehicleId,
            'routeId' => $routeId,
        ]);

        $validated = $request->validate([
            'orderId' => 'required|exists:orders,id',
            'vehicleId' => 'required|exists:vehicles,id',
            'routeId' => 'nullable|exists:routes,id',
        ]);

        $user = $request->user();

        $shipment = DB::transaction(function () use ($validated, $user) {
            $order = Order::where('id', $validated['orderId'])->lockForUpdate()->firstOrFail();

            $userRegion = Region::find($user->region_id);
            $orderRegion = $order->originRegion;
            $sameRegion = ($order->region_id === $user->region_id) || ($userRegion && $orderRegion && $userRegion->province === $orderRegion->province);

            if ($user->role === 'admin_tengkulak' && !$sameRegion) {
                abort(403, 'Pesanan berada di luar wilayah kendali Anda.');
            }

            if (!in_array($order->status, ['paid', 'processing'])) {
                abort(409, 'Pesanan harus berstatus LUNAS atau PROCESSING untuk dialokasikan pengiriman.');
            }

            $vehicle = Vehicle::where('id', $validated['vehicleId'])->lockForUpdate()->firstOrFail();
            if ($user->role !== 'super_admin' && $vehicle->owner_id !== $user->id) {
                abort(403, 'Armada bukan milik Anda.');
            }

            // Check active vehicle load capacity
            $currentLoad = Shipment::where('vehicle_id', $vehicle->id)
                ->where('status', '!=', 'delivered')
                ->where('order_id', '!=', $order->id)
                ->with('order')
                ->get()
                ->sum(fn($s) => $s->order ? $s->order->total_weight_kg : 0);

            if (($currentLoad + $order->total_weight_kg) > $vehicle->capacity_kg) {
                abort(409, "Kapasitas armada {$vehicle->plate} terlampaui. Muatan aktif: {$currentLoad} kg, Pesanan ini: {$order->total_weight_kg} kg, Maksimal: {$vehicle->capacity_kg} kg.");
            }

            $shipment = Shipment::where('order_id', $order->id)->first();
            if ($shipment) {
                $shipment->vehicle_id = $vehicle->id;
                if (!empty($validated['routeId'])) {
                    $shipment->route_id = $validated['routeId'];
                }
                $shipment->status = 'preparing';
                $shipment->save();
            } else {
                $trackingCode = 'TRK-' . strtoupper(Str::random(10));
                $shipment = Shipment::create([
                    'order_id' => $order->id,
                    'vehicle_id' => $vehicle->id,
                    'route_id' => $validated['routeId'] ?? null,
                    'status' => 'preparing',
                    'tracking_code' => $trackingCode,
                ]);
            }

            ShipmentEvent::create([
                'shipment_id' => $shipment->id,
                'status' => 'preparing',
                'note' => "Armada {$vehicle->plate} dialokasikan oleh " . $user->name,
            ]);

            $order->status = 'processing';
            $order->save();

            return $shipment;
        });

        return response()->json($shipment, 200);
    }

    public function updateStatus(Request $request, $id)
    {
        $allowedStatuses = [
            'preparing', 'draft',
            'picked_up', 'assigned', 'loading',
            'in_transit',
            'arrived_hub', 'arrived', 'out_for_delivery',
            'delivered',
        ];

        $validated = $request->validate([
            'status' => ['required', Rule::in($allowedStatuses)],
            'note' => 'nullable|string',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
        ]);

        $normalizedStatus = match ($validated['status']) {
            'draft' => 'preparing',
            'assigned' => 'picked_up',
            'loading' => 'in_transit',
            'arrived' => 'arrived_hub',
            default => $validated['status'],
        };

        $user = $request->user();

        DB::transaction(function () use ($validated, $normalizedStatus, $user, $id) {
            $shipment = Shipment::with(['order.originRegion', 'vehicle'])->where('id', $id)->lockForUpdate()->firstOrFail();

            // Authorization:
            // Super Admin & Admin Support L3 can update any shipment nationwide
            // Tengkulak can update if:
            // 1. Their vehicle is assigned to the shipment
            // 2. The order is in their region
            // 3. The origin province matches their region province
            if ($user->role === 'admin_tengkulak') {
                $isVehicleOwner = $shipment->vehicle && ($shipment->vehicle->owner_id == $user->id);
                $isRegionMatch = $shipment->order && (
                    $shipment->order->region_id == $user->region_id ||
                    $shipment->order->origin_region_id == $user->region_id
                );

                $userRegion = $user->region_id ? Region::find($user->region_id) : null;
                $isProvinceMatch = false;
                if ($userRegion && $shipment->order && $shipment->order->originRegion) {
                    $isProvinceMatch = ($shipment->order->originRegion->province === $userRegion->province);
                }

                if (!$isVehicleOwner && !$isRegionMatch && !$isProvinceMatch) {
                    abort(403, 'Di luar wilayah kendali Anda.');
                }
            }

            $shipment->status = $normalizedStatus;
            if (isset($validated['lat'])) $shipment->last_lat = $validated['lat'];
            if (isset($validated['lng'])) $shipment->last_lng = $validated['lng'];
            $shipment->save();

            $defaultNote = match ($normalizedStatus) {
                'preparing' => "Pengemasan komoditas hasil panen di hub/kebun asal.",
                'picked_up' => "Armada telah menjemput muatan hasil panen di titik asal.",
                'in_transit' => "Armada sedang dalam perjalanan menuju kota tujuan.",
                'arrived_hub' => "Armada dan muatan telah tiba di Hub Transit Kota.",
                'out_for_delivery' => "Kurir logistik sedang menuju ke alamat penerima.",
                'delivered' => "Pesanan telah sukses diterima oleh pembeli.",
                default => "Status logistik diperbarui menjadi {$normalizedStatus}.",
            };

            ShipmentEvent::create([
                'shipment_id' => $shipment->id,
                'status' => $normalizedStatus,
                'note' => $validated['note'] ?: "{$defaultNote} (diperbarui oleh {$user->name})",
                'lat' => $validated['lat'] ?? $shipment->last_lat,
                'lng' => $validated['lng'] ?? $shipment->last_lng,
            ]);

            // Sync with parent Order status
            if (in_array($normalizedStatus, ['picked_up', 'in_transit', 'arrived_hub', 'out_for_delivery'])) {
                if ($shipment->order && $shipment->order->status !== 'completed' && $shipment->order->status !== 'delivered') {
                    $shipment->order->status = 'shipped';
                    $shipment->order->save();
                }
            } elseif ($normalizedStatus === 'delivered') {
                if ($shipment->order && $shipment->order->status !== 'completed') {
                    $shipment->order->status = 'delivered';
                    $shipment->order->save();
                }
            }
        });

        return response()->json(['ok' => true, 'status' => $normalizedStatus]);
    }
}

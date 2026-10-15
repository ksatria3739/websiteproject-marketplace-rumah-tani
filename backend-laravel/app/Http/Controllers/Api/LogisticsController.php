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
            'plate' => $shipment->vehicle ? $shipment->vehicle->plate : null,
            'driver_name' => $shipment->vehicle ? $shipment->vehicle->driver_name : null,
            'origin' => $order->originRegion->name,
            'destination' => $order->destRegion->name,
            'o_lat' => (float) $order->originRegion->lat,
            'o_lng' => (float) $order->originRegion->lng,
            'd_lat' => (float) $order->destRegion->lat,
            'd_lng' => (float) $order->destRegion->lng,
            'events' => $shipment->events->map(fn($e) => [
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
            'plate' => 'required|string|min:3|unique:vehicles,plate',
            'type' => 'required|string',
            'capacityKg' => 'required|numeric|min:10',
            'driverName' => 'nullable|string',
        ]);

        $vehicle = Vehicle::create([
            'owner_id' => $request->user()->id,
            'plate' => strtoupper($validated['plate']),
            'type' => $validated['type'],
            'capacity_kg' => $validated['capacityKg'],
            'driver_name' => $validated['driverName'] ?? null,
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
                    'plate_number' => $s->vehicle->plate,
                    'model' => $s->vehicle->type,
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
        $validated = $request->validate([
            'status' => ['required', Rule::in(self::STATUS_FLOW)],
            'note' => 'nullable|string',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
        ]);

        $user = $request->user();

        DB::transaction(function () use ($validated, $user, $id) {
            $shipment = Shipment::with('order')->where('id', $id)->lockForUpdate()->firstOrFail();

            if ($user->role === 'admin_tengkulak' && $shipment->order->region_id !== $user->region_id) {
                abort(403, 'Di luar wilayah kendali Anda.');
            }

            $currentIndex = array_search($shipment->status, self::STATUS_FLOW);
            $targetIndex = array_search($validated['status'], self::STATUS_FLOW);

            if ($targetIndex !== ($currentIndex + 1)) {
                $expected = self::STATUS_FLOW[$currentIndex + 1] ?? 'selesai';
                abort(409, "Alur status pengiriman harus bertahap. Tahap berikutnya adalah '{$expected}'.");
            }

            $shipment->status = $validated['status'];
            if (isset($validated['lat'])) $shipment->last_lat = $validated['lat'];
            if (isset($validated['lng'])) $shipment->last_lng = $validated['lng'];
            $shipment->save();

            ShipmentEvent::create([
                'shipment_id' => $shipment->id,
                'status' => $validated['status'],
                'note' => $validated['note'] ?? null,
                'lat' => $validated['lat'] ?? null,
                'lng' => $validated['lng'] ?? null,
            ]);

            // Sync with parent Order status
            if ($validated['status'] === 'picked_up') {
                $shipment->order->status = 'shipped';
                $shipment->order->save();
            } elseif ($validated['status'] === 'delivered') {
                $shipment->order->status = 'delivered';
                $shipment->order->save();
            }
        });

        return response()->json(['ok' => true]);
    }
}

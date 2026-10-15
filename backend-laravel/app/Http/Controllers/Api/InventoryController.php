<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Commodity;
use App\Models\StockMovement;
use App\Models\Variant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    private function assertCanManage($user, Commodity $commodity)
    {
        if (in_array($user->role, ['super_admin', 'admin'])) {
            return true;
        }

        if ($user->role === 'petani' && $commodity->farmer_id === $user->id) {
            return true;
        }

        abort(403, 'Anda tidak memiliki hak akses untuk mengelola komoditas ini.');
    }

    public function index(Request $request)
    {
        $user = $request->user();

        $query = Commodity::with(['variants', 'region', 'category', 'farmer']);

        if ($user->role === 'petani') {
            $query->where('farmer_id', $user->id);
        } elseif ($user->role === 'admin_tengkulak') {
            $query->where('region_id', $user->region_id);
        }

        if ($request->filled('farmerId') && in_array($user->role, ['super_admin', 'admin'])) {
            $query->where('farmer_id', $request->farmerId);
        }

        $commodities = $query->latest()->get()->map(function ($c) {
            return [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'curation' => $c->curation,
                'curation_note' => $c->curation_note,
                'is_active' => $c->is_active,
                'region_id' => $c->region_id,
                'region' => $c->region ? $c->region->name : null,
                'category' => $c->category ? $c->category->name : null,
                'farmer_id' => $c->farmer_id,
                'farmer_name' => $c->farmer ? $c->farmer->name : null,
                'image_url' => $c->image_url,
                'description' => $c->description,
                'variants' => $c->variants->map(fn($v) => [
                    'id' => $v->id,
                    'name' => $v->variant_name,
                    'grade' => $v->grade,
                    'price' => $v->price,
                    'stock' => $v->stock_qty,
                    'stock_qty' => $v->stock_qty,
                    'reserved' => $v->reserved_qty,
                    'reserved_qty' => $v->reserved_qty,
                    'available' => $v->available_stock,
                    'unit' => $v->unit,
                ]),
            ];
        });

        return response()->json($commodities);
    }

    /**
     * Upload commodity image in real-time.
     */
    public function uploadImage(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,webp,gif|max:10240',
        ]);

        $file = $request->file('image');
        $filename = 'comm_' . time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
        $path = $file->storeAs('commodities', $filename, 'public');

        $relativeUrl = '/storage/' . $path;
        $fullUrl = url($relativeUrl);

        return response()->json([
            'ok' => true,
            'path' => $relativeUrl,
            'url' => $fullUrl,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => 'required|string|min:2',
            'categoryId' => 'required|exists:categories,id',
            'regionId' => 'nullable|exists:regions,id',
            'description' => 'nullable|string',
            'imageUrl' => 'nullable|string',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp,gif|max:10240',
            'variants' => 'required|array|min:1',
            'variants.*.variantName' => 'required|string',
            'variants.*.grade' => ['required', Rule::in(['A', 'B', 'C'])],
            'variants.*.unit' => 'nullable|string',
            'variants.*.price' => 'required|numeric|min:100',
            'variants.*.stockQty' => 'required|numeric|min:0',
            'variants.*.minOrder' => 'nullable|numeric|min:1',
            'variants.*.weightPerUnitKg' => 'nullable|numeric|min:0.01',
        ]);

        $regionId = $validated['regionId'] ?? $user->region_id;
        if (!$regionId) {
            return response()->json(['error' => 'regionId is required'], 422);
        }

        // Direct file upload fallback if sent along with form
        $imageUrl = $validated['imageUrl'] ?? null;
        if ($request->hasFile('image')) {
            $file = $request->file('image');
            $filename = 'comm_' . time() . '_' . Str::random(8) . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs('commodities', $filename, 'public');
            $imageUrl = '/storage/' . $path;
        }

        // Petani submits -> pending curation; Tengkulak/Super Admin -> approved directly
        $curation = $user->role === 'petani' ? 'pending' : 'approved';
        $curatedBy = $curation === 'approved' ? $user->id : null;

        $slug = Str::slug($validated['name']) . '-' . Str::lower(Str::random(5));

        $commodityId = DB::transaction(function () use ($validated, $user, $regionId, $curation, $curatedBy, $slug, $imageUrl) {
            $commodity = Commodity::create([
                'farmer_id' => $user->id,
                'region_id' => $regionId,
                'category_id' => $validated['categoryId'],
                'name' => $validated['name'],
                'slug' => $slug,
                'description' => $validated['description'] ?? null,
                'image_url' => $imageUrl,
                'curation' => $curation,
                'curated_by' => $curatedBy,
                'is_active' => true,
            ]);

            foreach ($validated['variants'] as $v) {
                $variant = Variant::create([
                    'commodity_id' => $commodity->id,
                    'variant_name' => $v['variantName'],
                    'grade' => $v['grade'],
                    'unit' => $v['unit'] ?? 'kg',
                    'price' => $v['price'],
                    'stock_qty' => $v['stockQty'],
                    'min_order' => $v['minOrder'] ?? 1,
                    'weight_per_unit_kg' => $v['weightPerUnitKg'] ?? 1.0,
                ]);

                if ($v['stockQty'] > 0) {
                    StockMovement::create([
                        'variant_id' => $variant->id,
                        'delta' => $v['stockQty'],
                        'reason' => 'harvest',
                        'actor_id' => $user->id,
                    ]);
                }
            }

            return $commodity->id;
        });

        return response()->json([
            'id' => $commodityId,
            'curation' => $curation,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $commodity = Commodity::findOrFail($id);
        $this->assertCanManage($request->user(), $commodity);

        $validated = $request->validate([
            'name' => 'nullable|string|min:2',
            'description' => 'nullable|string',
            'imageUrl' => 'nullable|url',
        ]);

        if (!empty($validated['name'])) {
            $commodity->name = $validated['name'];
        }
        if (array_key_exists('description', $validated)) {
            $commodity->description = $validated['description'];
        }
        if (array_key_exists('imageUrl', $validated)) {
            $commodity->image_url = $validated['imageUrl'];
        }

        // If petani modifies name or specs, reset to pending curation
        if ($request->user()->role === 'petani') {
            $commodity->curation = 'pending';
        }

        $commodity->save();

        return response()->json(['ok' => true]);
    }

    public function updateVariant(Request $request, $variantId)
    {
        $variant = Variant::findOrFail($variantId);
        $this->assertCanManage($request->user(), $variant->commodity);

        $validated = $request->validate([
            'price' => 'nullable|numeric|min:1',
            'minOrder' => 'nullable|numeric|min:1',
        ]);

        if (isset($validated['price'])) {
            $variant->price = $validated['price'];
        }
        if (isset($validated['minOrder'])) {
            $variant->min_order = $validated['minOrder'];
        }

        $variant->save();

        return response()->json(['ok' => true]);
    }

    /**
     * Delta-based real-time stock update with database locking.
     * Guaranteed free from race conditions under high concurrency.
     */
    public function adjustStock(Request $request, $variantId)
    {
        $validated = $request->validate([
            'delta' => 'required|numeric|not_in:0',
            'reason' => ['nullable', Rule::in(['harvest', 'adjust', 'l3_support', 'audit', 'restock'])],
        ]);

        $user = $request->user();
        $delta = (float) $validated['delta'];
        $defaultReason = in_array($user->role, ['super_admin', 'admin']) ? 'l3_support' : 'adjust';
        $reason = $validated['reason'] ?? $defaultReason;

        $result = DB::transaction(function () use ($variantId, $user, $delta, $reason) {
            // Pessimistic write lock (SELECT ... FOR UPDATE)
            $variant = Variant::where('id', $variantId)->lockForUpdate()->firstOrFail();
            $this->assertCanManage($user, $variant->commodity);

            $newStock = $variant->stock_qty + $delta;

            // Database-level integrity guard
            if ($newStock < 0) {
                abort(409, "Stok tidak boleh bernilai negatif (stok saat ini: {$variant->stock_qty})");
            }

            if ($newStock < $variant->reserved_qty) {
                abort(409, "Stok tidak boleh lebih kecil dari pesanan yang sedang direservasi ({$variant->reserved_qty})");
            }

            $variant->stock_qty = $newStock;
            $variant->version += 1;
            $variant->save();

            StockMovement::create([
                'variant_id' => $variant->id,
                'delta' => $delta,
                'reason' => $reason,
                'actor_id' => $user->id,
            ]);

            return [
                'stock_qty' => $variant->stock_qty,
                'reserved_qty' => $variant->reserved_qty,
                'available' => $variant->available_stock,
                'movement' => [
                    'delta' => $delta,
                    'reason' => $reason,
                    'actor_id' => $user->id,
                ],
            ];
        });

        return response()->json($result);
    }
}

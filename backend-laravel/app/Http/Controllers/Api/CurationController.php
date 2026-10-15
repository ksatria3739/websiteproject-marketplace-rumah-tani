<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Commodity;
use App\Models\Variant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CurationController extends Controller
{
    public function queue(Request $request)
    {
        $user = $request->user();

        $query = Commodity::with(['farmer', 'region', 'variants'])
            ->where('curation', 'pending');

        if ($user->role === 'admin_tengkulak') {
            $query->where('region_id', $user->region_id);
        }

        $items = $query->latest()->get()->map(function ($c) {
            return [
                'id' => $c->id,
                'name' => $c->name,
                'created_at' => $c->created_at,
                'farmer' => $c->farmer->name,
                'region' => $c->region->name,
                'variants' => $c->variants->map(fn($v) => [
                    'id' => $v->id,
                    'variant_name' => $v->variant_name,
                    'grade' => $v->grade,
                    'price' => $v->price,
                    'stock' => $v->stock_qty,
                ]),
            ];
        });

        return response()->json($items);
    }

    public function decision(Request $request, $id)
    {
        $validated = $request->validate([
            'approve' => 'required|boolean',
            'note' => 'nullable|string',
            'gradeOverrides' => 'nullable|array',
            'gradeOverrides.*.variantId' => 'required|exists:variants,id',
            'gradeOverrides.*.grade' => 'required|in:A,B,C',
        ]);

        $user = $request->user();
        $commodity = Commodity::findOrFail($id);

        if ($user->role === 'admin_tengkulak' && $commodity->region_id !== $user->region_id) {
            return response()->json(['error' => 'Komoditas berada di luar wilayah kendali Anda.'], 403);
        }

        DB::transaction(function () use ($validated, $user, $commodity) {
            if (!empty($validated['gradeOverrides'])) {
                foreach ($validated['gradeOverrides'] as $override) {
                    Variant::where('id', $override['variantId'])
                        ->where('commodity_id', $commodity->id)
                        ->update(['grade' => $override['grade']]);
                }
            }

            $commodity->curation = $validated['approve'] ? 'approved' : 'rejected';
            $commodity->curated_by = $user->id;
            $commodity->curation_note = $validated['note'] ?? null;
            $commodity->save();

            AuditLog::create([
                'actor_id' => $user->id,
                'action' => 'commodity.curation',
                'entity' => 'commodity',
                'entity_id' => (string) $commodity->id,
                'meta' => [
                    'approved' => $validated['approve'],
                    'note' => $validated['note'] ?? null,
                ],
            ]);
        });

        return response()->json(['ok' => true]);
    }
}

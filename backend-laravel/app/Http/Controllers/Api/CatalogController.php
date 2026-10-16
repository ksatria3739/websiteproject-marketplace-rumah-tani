<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Article;
use App\Models\Category;
use App\Models\Commodity;
use App\Models\Region;
use App\Models\SystemSetting;
use Illuminate\Http\Request;

class CatalogController extends Controller
{
    public function index(Request $request)
    {
        $query = Commodity::with(['region', 'category', 'variants'])
            ->where('curation', 'approved')
            ->where('is_active', true);

        if ($request->filled('q')) {
            $q = strtolower($request->q);
            $query->whereRaw('LOWER(name) LIKE ?', ["%{$q}%"]);
        }

        if ($request->filled('regionId')) {
            $query->where('region_id', $request->regionId);
        }

        if ($request->filled('categoryId')) {
            $query->where('category_id', $request->categoryId);
        }

        if ($request->filled('grade')) {
            $grade = $request->grade;
            $query->whereHas('variants', function ($v) use ($grade) {
                $v->where('grade', $grade);
            });
        }

        $perPage = min(60, max(1, (int) $request->input('limit', 20)));
        $paginated = $query->latest()->paginate($perPage);

        $items = collect($paginated->items())->map(function ($c) {
            $minPrice = $c->variants->min('price') ?? 0;
            $totalAvailable = $c->variants->sum(fn($v) => max(0, $v->stock_qty - $v->reserved_qty));
            $grades = $c->variants->pluck('grade')->unique()->values()->all();

            return [
                'id' => $c->id,
                'name' => $c->name,
                'slug' => $c->slug,
                'image_url' => $c->image_url,
                'region' => $c->region->name,
                'region_id' => $c->region_id,
                'category' => $c->category->name,
                'category_id' => $c->category_id,
                'price_from' => $minPrice,
                'available' => $totalAvailable,
                'grades' => $grades,
            ];
        });

        return response()->json([
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'total' => $paginated->total(),
            'items' => $items,
        ]);
    }

    public function show($slug)
    {
        $commodity = Commodity::with(['region', 'category', 'farmer', 'variants'])
            ->where('slug', $slug)
            ->where('curation', 'approved')
            ->where('is_active', true)
            ->firstOrFail();

        $variants = $commodity->variants->map(function ($v) {
            return [
                'id' => $v->id,
                'variant_name' => $v->variant_name,
                'grade' => $v->grade,
                'unit' => $v->unit,
                'price' => $v->price,
                'available' => max(0, $v->stock_qty - $v->reserved_qty),
                'stock_qty' => $v->stock_qty,
                'min_order' => $v->min_order,
                'weight_per_unit_kg' => $v->weight_per_unit_kg,
            ];
        });

        return response()->json([
            'id' => $commodity->id,
            'name' => $commodity->name,
            'slug' => $commodity->slug,
            'description' => $commodity->description,
            'image_url' => $commodity->image_url,
            'region' => [
                'id' => $commodity->region->id,
                'name' => $commodity->region->name,
                'province' => $commodity->region->province,
            ],
            'region_name' => $commodity->region->name,
            'region_id' => $commodity->region_id,
            'category' => [
                'id' => $commodity->category->id,
                'name' => $commodity->category->name,
            ],
            'category_name' => $commodity->category->name,
            'category_id' => $commodity->category_id,
            'farmer' => [
                'id' => $commodity->farmer->id,
                'name' => $commodity->farmer->name,
                'address' => $commodity->farmer->address,
            ],
            'farmer_name' => $commodity->farmer->name,
            'variants' => $variants,
        ]);
    }

    public function meta()
    {
        return response()->json([
            'regions' => Region::orderBy('name')->get(['id', 'name', 'province', 'lat', 'lng']),
            'categories' => Category::orderBy('name')->get(['id', 'name', 'slug']),
            'settings' => SystemSetting::getAllSettings(),
        ]);
    }

    public function articles()
    {
        $articles = Article::where('published', true)
            ->latest('published_at')
            ->take(50)
            ->get(['slug', 'title', 'excerpt', 'cover_url', 'published_at']);

        return response()->json($articles);
    }

    public function articleDetail($slug)
    {
        $article = Article::where('slug', $slug)
            ->where('published', true)
            ->firstOrFail();

        return response()->json($article);
    }

    public function storeRegion(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:regions,name',
            'province' => 'required|string|max:100',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
        ]);

        $region = Region::create([
            'name' => trim($validated['name']),
            'province' => trim($validated['province']),
            'lat' => $validated['lat'] ?? -6.2088,
            'lng' => $validated['lng'] ?? 106.8456,
        ]);

        return response()->json($region, 201);
    }
}

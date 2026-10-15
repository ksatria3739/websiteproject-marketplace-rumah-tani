<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Commodity;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Variant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    const PAID_STATUSES = ['paid', 'processing', 'shipped', 'delivered', 'completed'];

    public function analytics(Request $request)
    {
        $from = $request->query('from', '1970-01-01');
        $to = $request->query('to', '2099-12-31');
        $groupBy = $request->query('groupBy', 'day'); // day, week, month

        $dateFormat = match ($groupBy) {
            'week' => '%Y-%u',
            'month' => '%Y-%m',
            default => '%Y-%m-%d',
        };

        // 1. Transaction Trend
        $trend = Order::whereIn('status', self::PAID_STATUSES)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw("DATE_FORMAT(created_at, '{$dateFormat}') as period, COUNT(*) as orders, SUM(total) as gmv, SUM(admin_fee) as revenue")
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->map(fn($r) => [
                'period' => $r->period,
                'orders' => (int) $r->orders,
                'gmv' => (float) $r->gmv,
                'revenue' => (float) $r->revenue,
            ]);

        // 2. Top Commodities by volume
        $top = OrderItem::whereHas('order', function ($o) use ($from, $to) {
                $o->whereIn('status', self::PAID_STATUSES)
                  ->whereBetween('created_at', [$from, $to]);
            })
            ->with('variant.commodity')
            ->selectRaw('variant_id, SUM(qty) as total_qty, SUM(line_total) as total_sales')
            ->groupBy('variant_id')
            ->orderByDesc('total_qty')
            ->take(10)
            ->get()
            ->map(function ($row) {
                $commName = $row->variant && $row->variant->commodity
                    ? $row->variant->commodity->name . ' (' . $row->variant->grade . ')'
                    : 'Komoditas #' . $row->variant_id;

                return [
                    'name' => $commName,
                    'qty' => (float) $row->total_qty,
                    'sales' => (float) $row->total_sales,
                ];
            });

        // 3. Operational Profit & Loss (P&L)
        $summary = Order::whereIn('status', self::PAID_STATUSES)
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('
                COALESCE(SUM(subtotal), 0) as gross_sales,
                COALESCE(SUM(admin_fee), 0) as admin_fee_revenue,
                COALESCE(SUM(tax), 0) as tax_collected,
                COALESCE(SUM(shipping_cost), 0) as shipping_collected,
                COUNT(*) as orders_count
            ')
            ->first();

        $adminRev = (float) $summary->admin_fee_revenue;
        $shippingCollected = (float) $summary->shipping_collected;
        // Operating logistics margin ~15%
        $carrierShare = $shippingCollected * 0.85;
        $shippingMargin = $shippingCollected - $carrierShare;
        $operatingProfit = $adminRev + $shippingMargin;

        $profit = [
            'gross_sales' => (float) $summary->gross_sales,
            'revenue' => $adminRev + $shippingMargin,
            'admin_fee_revenue' => $adminRev,
            'tax_collected' => (float) $summary->tax_collected,
            'shipping_collected' => $shippingCollected,
            'shipping_margin' => $shippingMargin,
            'operating_profit' => $operatingProfit,
            'orders' => (int) $summary->orders_count,
        ];

        return response()->json([
            'trend' => $trend,
            'top' => $top,
            'profit' => $profit,
        ]);
    }

    public function exportReport(Request $request)
    {
        $format = $request->query('format', 'csv');
        $analytics = $this->analytics($request)->getData(true);

        if ($format === 'csv') {
            $csvData = "=== LAPORAN OPERASIONAL RUMAH TANI ===\n\n";
            $csvData .= "PROFIT & LOSS\n";
            foreach ($analytics['profit'] as $k => $v) {
                $csvData .= "{$k}," . number_format($v, 2, '.', '') . "\n";
            }

            $csvData .= "\nTOP KOMODITAS\n";
            $csvData .= "Komoditas,Kuantitas (kg),Total Penjualan (IDR)\n";
            foreach ($analytics['top'] as $item) {
                $csvData .= "\"{$item['name']}\",{$item['qty']},{$item['sales']}\n";
            }

            $csvData .= "\nTREN TRANSAKSI\n";
            $csvData .= "Periode,Jumlah Pesanan,GMV (IDR),Revenue Admin (IDR)\n";
            foreach ($analytics['trend'] as $item) {
                $csvData .= "{$item['period']},{$item['orders']},{$item['gmv']},{$item['revenue']}\n";
            }

            return response($csvData, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="rumah-tani-report.csv"',
            ]);
        }

        return response()->json($analytics);
    }

    public function listUsers(Request $request)
    {
        $users = User::with(['region', 'kycDocuments'])
            ->latest()
            ->take(500)
            ->get()
            ->map(fn($u) => [
                'id' => $u->id,
                'email' => $u->email,
                'full_name' => $u->name,
                'name' => $u->name,
                'phone' => $u->phone,
                'address' => $u->address,
                'role' => $u->role,
                'buyer_type' => $u->buyer_type,
                'company_name' => $u->company_name,
                'region_id' => $u->region_id,
                'region' => $u->region ? $u->region->name : null,
                'kyc_status' => $u->kyc_status,
                'kyc_note' => $u->kyc_note,
                'is_active' => $u->is_active,
                'created_at' => $u->created_at,
                'docs' => $u->kycDocuments->map(fn($d) => [
                    'id' => $d->id,
                    'type' => $d->doc_type,
                    'file' => $d->file_path,
                    'url' => url('/api/kyc/documents/' . $d->id . '/file'),
                    'stream_url' => url('/api/kyc/documents/' . $d->id . '/file'),
                    'storage_url' => url('/storage/' . $d->file_path),
                    'created_at' => $d->created_at,
                ]),
            ]);

        return response()->json($users);
    }

    public function createTengkulak(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|min:2',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'regionId' => 'required|exists:regions,id',
            'phone' => 'nullable|string',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'admin_tengkulak',
            'region_id' => $validated['regionId'],
            'phone' => $validated['phone'] ?? null,
            'kyc_status' => 'approved',
            'is_active' => true,
        ]);

        return response()->json([
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'role' => $user->role,
        ], 201);
    }

    public function toggleUserStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'isActive' => 'required|boolean',
        ]);

        if ($request->user()->id == $id) {
            return response()->json(['error' => 'Tidak dapat menonaktifkan akun sendiri.'], 409);
        }

        $user = User::findOrFail($id);
        $user->is_active = $validated['isActive'];
        // Revoke all tokens if deactivated
        if (!$validated['isActive']) {
            $user->tokens()->delete();
            $user->token_version += 1;
        }
        $user->save();

        return response()->json(['ok' => true]);
    }

    public function updateUserRole(Request $request, $id)
    {
        $validated = $request->validate([
            'role' => ['required', Rule::in(['super_admin', 'admin', 'admin_tengkulak', 'petani', 'pembeli'])],
            'buyerType' => ['nullable', Rule::in(['b2b', 'b2c'])],
        ]);

        $currentUser = $request->user();
        $targetUser = User::findOrFail($id);

        // Admin support cannot modify super_admin or admin roles, nor promote someone to super_admin or admin
        if ($currentUser->role === 'admin') {
            if (in_array($targetUser->role, ['super_admin', 'admin'])) {
                return response()->json(['error' => 'Admin Support L3 tidak memiliki wewenang mengubah peran administrator lain.'], 403);
            }
            if (in_array($validated['role'], ['super_admin', 'admin'])) {
                return response()->json(['error' => 'Hanya Super Admin yang berhak mengangkat peran Administrator Sistem.'], 403);
            }
        }

        // Super Admin cannot demote themselves if they are the sole active Super Admin
        if ($currentUser->id === $targetUser->id && $validated['role'] !== 'super_admin') {
            $superAdminCount = User::where('role', 'super_admin')->where('is_active', true)->count();
            if ($superAdminCount <= 1) {
                return response()->json(['error' => 'Tidak dapat menurunkan peran satu-satunya Super Admin aktif di sistem.'], 422);
            }
        }

        $oldRole = $targetUser->role;
        $targetUser->role = $validated['role'];
        if ($validated['role'] === 'pembeli') {
            $targetUser->buyer_type = $validated['buyerType'] ?? ($targetUser->buyer_type ?? 'b2c');
        } else {
            $targetUser->buyer_type = null;
        }
        $targetUser->token_version += 1;
        $targetUser->save();

        AuditLog::create([
            'actor_id' => $currentUser->id,
            'action' => 'user.role_change',
            'entity' => 'user',
            'entity_id' => (string) $targetUser->id,
            'meta' => [
                'old_role' => $oldRole,
                'new_role' => $targetUser->role,
                'changed_by' => $currentUser->email,
            ],
        ]);

        return response()->json([
            'ok' => true,
            'user' => [
                'id' => $targetUser->id,
                'name' => $targetUser->name,
                'email' => $targetUser->email,
                'role' => $targetUser->role,
                'buyer_type' => $targetUser->buyer_type,
            ],
        ]);
    }

    public function getSettings()
    {
        $settings = SystemSetting::orderBy('key')->get();
        return response()->json($settings);
    }

    public function updateSetting(Request $request, $key)
    {
        $validated = $request->validate([
            'value' => 'required|string',
        ]);

        $setting = SystemSetting::updateOrCreate(
            ['key' => $key],
            ['value' => $validated['value'], 'updated_by' => $request->user()->id]
        );

        AuditLog::create([
            'actor_id' => $request->user()->id,
            'action' => 'setting.update',
            'entity' => 'setting',
            'entity_id' => $key,
            'meta' => ['value' => $validated['value']],
        ]);

        return response()->json(['ok' => true]);
    }

    public function transactions(Request $request)
    {
        $orders = Order::with(['buyer', 'seller'])
            ->latest()
            ->take(300)
            ->get()
            ->map(fn($o) => [
                'code' => $o->code,
                'status' => $o->status,
                'total' => $o->total,
                'created_at' => $o->created_at,
                'buyer' => $o->buyer ? $o->buyer->name : '-',
                'seller' => $o->seller ? $o->seller->name : '-',
            ]);

        return response()->json($orders);
    }

    public function dashboardSummary(Request $request)
    {
        $user = $request->user();

        $ordersQuery = Order::query();
        if ($user->role === 'petani') {
            $ordersQuery->where('seller_id', $user->id);
        } elseif ($user->role === 'admin_tengkulak') {
            $ordersQuery->where(function ($q) use ($user) {
                $q->where('region_id', $user->region_id)
                  ->orWhere('seller_id', $user->id);
            });
        }

        $toProcess = (clone $ordersQuery)->where('status', 'paid')->count();
        $totalSales = (clone $ordersQuery)->whereIn('status', self::PAID_STATUSES)->sum('subtotal');

        $lowStockQuery = Variant::whereHas('commodity', function ($c) use ($user) {
            if ($user->role === 'petani') {
                $c->where('farmer_id', $user->id);
            } elseif ($user->role === 'admin_tengkulak') {
                $c->where('region_id', $user->region_id);
            }
        })->whereRaw('stock_qty - reserved_qty < 10');

        $lowStockCount = $lowStockQuery->count();

        return response()->json([
            'to_process' => $toProcess,
            'sales' => (float) $totalSales,
            'lowStockVariants' => $lowStockCount,
        ]);
    }
}

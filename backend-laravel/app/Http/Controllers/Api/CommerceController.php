<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Negotiation;
use App\Models\NegotiationOffer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Region;
use App\Models\Shipment;
use App\Models\ShipmentEvent;
use App\Models\StockMovement;
use App\Models\SystemSetting;
use App\Models\Variant;
use App\Services\PricingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CommerceController extends Controller
{
    // ==========================================
    // CART MODULE
    // ==========================================

    public function getCart(Request $request)
    {
        $user = $request->user();
        $destRegionId = $request->query('destRegionId');

        $cartItems = CartItem::with(['variant.commodity.farmer', 'variant.commodity.region', 'negotiation'])
            ->where('user_id', $user->id)
            ->get();

        if ($cartItems->isEmpty()) {
            return response()->json(['groups' => [], 'grandTotal' => 0, 'items' => []]);
        }

        $destRegion = $destRegionId ? Region::find($destRegionId) : null;

        // Group by seller/vendor
        $grouped = $cartItems->groupBy(fn($ci) => $ci->variant->commodity->farmer_id);

        $groups = [];
        $overallTotal = 0;

        foreach ($grouped as $sellerId => $items) {
            $first = $items->first();
            $seller = $first->variant->commodity->farmer;
            $originRegion = $first->variant->commodity->region;

            $groupItems = [];
            $groupWeightKg = 0;
            $groupSubtotal = 0;

            foreach ($items as $ci) {
                $v = $ci->variant;
                $comm = $v->commodity;

                // Check active deal
                $unitPrice = (float) $v->price;
                $hasDeal = false;

                if ($ci->negotiation && $ci->negotiation->status === 'accepted' && Carbon::now()->lt($ci->negotiation->expires_at)) {
                    $unitPrice = (float) $ci->negotiation->agreed_price;
                    $hasDeal = true;
                }

                $lineWeight = (float) ($ci->qty * $v->weight_per_unit_kg);
                $lineSubtotal = (float) ($ci->qty * $unitPrice);

                $groupWeightKg += $lineWeight;
                $groupSubtotal += $lineSubtotal;

                $groupItems[] = [
                    'variantId' => $v->id,
                    'commodityId' => $comm->id,
                    'name' => $comm->name . ' - ' . $v->variant_name,
                    'grade' => $v->grade,
                    'qty' => (float) $ci->qty,
                    'unitPrice' => $unitPrice,
                    'weightKg' => $lineWeight,
                    'negotiationId' => $ci->negotiation_id,
                    'hasNegotiatedPrice' => $hasDeal,
                    'stockAvailable' => $v->available_stock,
                    'isAvailable' => $comm->curation === 'approved' && $comm->is_active,
                ];
            }

            $shippingCost = 0;
            $distanceKm = 0;

            if ($destRegion) {
                $distanceKm = PricingService::distanceKm($originRegion, $destRegion);
                $shippingCost = PricingService::calculateShippingCost($groupWeightKg, $distanceKm, $originRegion->id, $destRegion->id);
            }

            $totals = PricingService::calculateTotals($groupSubtotal, $shippingCost);
            $overallTotal += $totals['total'];

            $groups[] = [
                'sellerId' => $seller->id,
                'sellerName' => $seller->name,
                'originRegionId' => $originRegion->id,
                'originRegion' => $originRegion->name,
                'totalWeightKg' => round($groupWeightKg, 2),
                'distanceKm' => $distanceKm,
                'items' => $groupItems,
                'subtotal' => $totals['subtotal'],
                'tax' => $totals['tax'],
                'adminFee' => $totals['admin_fee'],
                'shipping' => $totals['shipping'],
                'total' => $totals['total'],
            ];
        }

        return response()->json([
            'groups' => $groups,
            'grandTotal' => round($overallTotal, 2),
            'items' => $cartItems->map(fn($ci) => [
                'variantId' => $ci->variant_id,
                'name' => $ci->variant->commodity->name,
                'grade' => $ci->variant->grade,
                'qty' => (float) $ci->qty,
            ]),
        ]);
    }

    public function putCartItem(Request $request)
    {
        // Support both camelCase and snake_case seamlessly
        $variantId = $request->input('variantId', $request->input('variant_id'));
        $qty = $request->input('qty', $request->input('quantity'));
        $negotiationId = $request->input('negotiationId', $request->input('negotiation_id'));

        $request->merge([
            'variantId' => $variantId,
            'qty' => $qty,
            'negotiationId' => $negotiationId,
        ]);

        $validated = $request->validate([
            'variantId' => 'required|exists:variants,id',
            'qty' => 'required|numeric|min:0.01',
            'negotiationId' => 'nullable|exists:negotiations,id',
        ]);

        $user = $request->user();
        $variant = Variant::with('commodity')->findOrFail($validated['variantId']);

        if ($variant->commodity->curation !== 'approved' || !$variant->commodity->is_active) {
            return response()->json(['error' => 'Komoditas sedang tidak tersedia.'], 409);
        }

        if ($validated['qty'] < $variant->min_order) {
            return response()->json(['error' => "Minimal pemesanan adalah {$variant->min_order} {$variant->unit}"], 422);
        }

        if ($validated['qty'] > $variant->available_stock) {
            return response()->json(['error' => "Stok yang tersedia hanya {$variant->available_stock} {$variant->unit}"], 409);
        }

        if (!empty($validated['negotiationId'])) {
            $nego = Negotiation::where('id', $validated['negotiationId'])
                ->where('buyer_id', $user->id)
                ->where('variant_id', $variant->id)
                ->where('status', 'accepted')
                ->where('expires_at', '>', Carbon::now())
                ->first();

            if (!$nego) {
                return response()->json(['error' => 'Kesepakatan harga negosiasi tidak valid atau telah kedaluwarsa.'], 422);
            }
        }

        CartItem::updateOrCreate(
            ['user_id' => $user->id, 'variant_id' => $variant->id],
            ['qty' => $validated['qty'], 'negotiation_id' => $validated['negotiationId'] ?? null]
        );

        return response()->json(['ok' => true]);
    }

    public function deleteCartItem(Request $request, $variantId)
    {
        CartItem::where('user_id', $request->user()->id)
            ->where('variant_id', $variantId)
            ->delete();

        return response()->json(['ok' => true]);
    }

    // ==========================================
    // CHECKOUT MODULE (ATOMIC ANTI-RACE-CONDITION)
    // ==========================================

    public function checkout(Request $request)
    {
        $destRegionId = $request->input('destRegionId', $request->input('dest_region_id'));
        $shipAddress = $request->input('shipAddress', $request->input('ship_address'));

        $request->merge([
            'destRegionId' => $destRegionId,
            'shipAddress' => $shipAddress,
        ]);

        $validated = $request->validate([
            'destRegionId' => 'required|exists:regions,id',
            'shipAddress' => 'required|string|min:5',
            'method' => ['required', Rule::in([
                'qris',
                'tripay_qris',
                'tripay_bca_va',
                'tripay_mandiri_va',
                'tripay_bri_va',
                'tripay_bni_va',
                'tripay_bsi_va',
                'tripay_alfamart',
                'tripay_indomaret',
                'va_bca',
                'va_bni',
                'va_bri',
                'va_mandiri',
                'gopay',
                'ovo',
                'bank_transfer',
            ])],
        ]);

        $user = $request->user();

        // KYC check for B2B buyer
        if ($user->buyer_type === 'b2b' && $user->kyc_status !== 'approved') {
            return response()->json(['error' => 'Akun B2B wajib diverifikasi KYC sebelum melakukan checkout.'], 403);
        }

        $destRegion = Region::findOrFail($validated['destRegionId']);
        $settings = SystemSetting::getAllSettings();

        // Run entire checkout in a single DB transaction with pessimistic row locking
        $checkoutResult = DB::transaction(function () use ($user, $validated, $destRegion, $settings) {
            // 1. Fetch user's cart
            $cartItems = CartItem::with(['variant.commodity.farmer', 'variant.commodity.region', 'negotiation'])
                ->where('user_id', $user->id)
                ->get();

            if ($cartItems->isEmpty()) {
                abort(400, 'Keranjang belanja Anda kosong.');
            }

            // 2. Lock all variants in ascending ID order to prevent deadlocks across concurrent requests
            $variantIds = $cartItems->pluck('variant_id')->sort()->values()->all();
            $lockedVariants = Variant::whereIn('id', $variantIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            // 3. Verify stock availability and minimum orders under lock
            foreach ($cartItems as $ci) {
                $v = $lockedVariants[$ci->variant_id] ?? null;
                if (!$v) {
                    abort(404, 'Salah satu produk tidak ditemukan.');
                }

                $available = $v->stock_qty - $v->reserved_qty;
                if ($ci->qty > $available) {
                    abort(409, "Stok produk {$v->variant_name} tidak mencukupi (Tersedia: {$available}, Diminta: {$ci->qty}).");
                }

                if ($ci->qty < $v->min_order) {
                    abort(409, "Pemesanan minimal {$v->variant_name} adalah {$v->min_order}.");
                }

                // Check negotiation deal if applicable
                if ($ci->negotiation_id) {
                    $dealOk = $ci->negotiation &&
                              $ci->negotiation->status === 'accepted' &&
                              Carbon::now()->lt($ci->negotiation->expires_at);

                    if (!$dealOk) {
                        abort(409, "Harga kesepakatan negosiasi untuk {$v->variant_name} sudah tidak berlaku.");
                    }
                }
            }

            // 4. Calculate multi-vendor order groups
            $grouped = $cartItems->groupBy(fn($ci) => $ci->variant->commodity->farmer_id);
            $grandTotal = 0;
            $vendorOrderData = [];

            foreach ($grouped as $sellerId => $items) {
                $first = $items->first();
                $seller = $first->variant->commodity->farmer;
                $originRegion = $first->variant->commodity->region;

                $weightKg = 0;
                $subtotal = 0;
                $itemSnapshots = [];

                foreach ($items as $ci) {
                    $v = $lockedVariants[$ci->variant_id];
                    $unitPrice = (float) $v->price;

                    if ($ci->negotiation && $ci->negotiation->status === 'accepted' && Carbon::now()->lt($ci->negotiation->expires_at)) {
                        $unitPrice = (float) $ci->negotiation->agreed_price;
                    }

                    $lineWeight = (float) ($ci->qty * $v->weight_per_unit_kg);
                    $lineTotal = (float) ($ci->qty * $unitPrice);

                    $weightKg += $lineWeight;
                    $subtotal += $lineTotal;

                    $itemSnapshots[] = [
                        'variant_id' => $v->id,
                        'name_snapshot' => $v->commodity->name . ' - ' . $v->variant_name,
                        'grade' => $v->grade,
                        'qty' => $ci->qty,
                        'unit_price' => $unitPrice,
                        'line_total' => $lineTotal,
                        'negotiation_id' => $ci->negotiation_id,
                    ];
                }

                $distanceKm = PricingService::distanceKm($originRegion, $destRegion);
                $shipping = PricingService::calculateShippingCost($weightKg, $distanceKm, $originRegion->id, $destRegion->id);
                $totals = PricingService::calculateTotals($subtotal, $shipping);

                $grandTotal += $totals['total'];

                $vendorOrderData[] = [
                    'seller' => $seller,
                    'originRegion' => $originRegion,
                    'totalWeightKg' => $weightKg,
                    'distanceKm' => $distanceKm,
                    'totals' => $totals,
                    'items' => $itemSnapshots,
                ];
            }

            // 5. Generate Payment record & Payment instructions
            $gatewayRef = 'RT-' . strtoupper(Str::random(10));
            $isQris = in_array($validated['method'], ['qris', 'tripay_qris']);
            $qrString = null;
            $qrUrl = null;

            if ($isQris) {
                $payCode = 'QRIS-' . $gatewayRef;
                $qrString = '00020101021226670016ID.CO.TRIPAY.WWW011893600002011' . rand(10000000, 99999999) . '52045814530336054' . str_pad((int)$grandTotal, 6, '0', STR_PAD_LEFT) . '5802ID5911RUMAH TANI6007JAKARTA6304' . strtoupper(substr(md5($gatewayRef), 0, 4));
                $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=350x350&data=' . urlencode($qrString);
            } else {
                $payCode = match ($validated['method']) {
                    'tripay_bca_va', 'va_bca' => '8800' . rand(1000000000, 9999999999),
                    'tripay_bni_va', 'va_bni' => '8810' . rand(1000000000, 9999999999),
                    'tripay_bri_va', 'va_bri' => '8820' . rand(1000000000, 9999999999),
                    'tripay_mandiri_va', 'va_mandiri' => '8830' . rand(1000000000, 9999999999),
                    'tripay_bsi_va' => '8840' . rand(1000000000, 9999999999),
                    'tripay_alfamart' => 'ALFA-' . rand(1000000000, 9999999999),
                    'tripay_indomaret' => 'INDO-' . rand(1000000000, 9999999999),
                    'gopay', 'ovo' => 'https://mock-gateway.rumahtani.id/pay/' . $gatewayRef,
                    default => 'REK-1234567890 BCA a.n. Rumah Tani Indonesia',
                };
            }

            $ttlMinutes = (int) ($settings['payment_ttl_minutes'] ?? 60);
            $expiresAt = Carbon::now()->addMinutes($ttlMinutes);

            $payment = Payment::create([
                'buyer_id' => $user->id,
                'gateway_ref' => $gatewayRef,
                'method' => $validated['method'],
                'amount' => $grandTotal,
                'status' => 'pending',
                'pay_code' => $payCode,
                'expires_at' => $expiresAt,
            ]);

            // 6. Create Orders and reserve stock
            $createdOrders = [];
            $orderIndex = 1;

            foreach ($vendorOrderData as $vod) {
                $orderCode = $gatewayRef . '-' . $orderIndex++;

                $order = Order::create([
                    'code' => $orderCode,
                    'payment_id' => $payment->id,
                    'buyer_id' => $user->id,
                    'seller_id' => $vod['seller']->id,
                    'region_id' => $vod['originRegion']->id,
                    'dest_region_id' => $destRegion->id,
                    'ship_address' => $validated['shipAddress'],
                    'status' => 'pending_payment',
                    'subtotal' => $vod['totals']['subtotal'],
                    'tax' => $vod['totals']['tax'],
                    'admin_fee' => $vod['totals']['admin_fee'],
                    'shipping_cost' => $vod['totals']['shipping'],
                    'total' => $vod['totals']['total'],
                    'total_weight_kg' => $vod['totalWeightKg'],
                ]);

                foreach ($vod['items'] as $item) {
                    OrderItem::create([
                        'order_id' => $order->id,
                        'variant_id' => $item['variant_id'],
                        'name_snapshot' => $item['name_snapshot'],
                        'grade' => $item['grade'],
                        'qty' => $item['qty'],
                        'unit_price' => $item['unit_price'],
                        'line_total' => $item['line_total'],
                    ]);

                    // Atomically increment reserved_qty
                    $v = $lockedVariants[$item['variant_id']];
                    $v->reserved_qty += $item['qty'];
                    $v->version += 1;
                    $v->save();

                    StockMovement::create([
                        'variant_id' => $v->id,
                        'delta' => -$item['qty'],
                        'reason' => 'reserve',
                        'ref_id' => (string) $order->id,
                        'actor_id' => $user->id,
                    ]);

                    if (!empty($item['negotiation_id'])) {
                        Negotiation::where('id', $item['negotiation_id'])->update(['status' => 'ordered']);
                    }
                }

                $createdOrders[] = [
                    'id' => $order->id,
                    'code' => $order->code,
                    'total' => $order->total,
                    'seller_name' => $vod['seller']->name,
                ];
            }

            // 7. Clear cart items
            CartItem::where('user_id', $user->id)->delete();

            return [
                'paymentId' => $payment->id,
                'gatewayRef' => $gatewayRef,
                'method' => $validated['method'],
                'payCode' => $payCode,
                'qrUrl' => $qrUrl,
                'qrString' => $qrString,
                'amount' => $grandTotal,
                'expiresAt' => $expiresAt->toIso8601String(),
                'orders' => $createdOrders,
            ];
        });

        return response()->json($checkoutResult, 201);
    }

    // ==========================================
    // PAYMENT GATEWAY & CHANNELS (TRIPAY & MODERN DIRECT)
    // ==========================================

    public function paymentChannels()
    {
        return response()->json([
            'gateway' => 'Tripay & Instant QRIS / VA',
            'description' => 'Payment gateway tanpa ribet: pendaftaran instan tanpa syarat legalitas rumit, scan QRIS langsung dari semua m-Banking dan E-Wallet, serta Virtual Account otomatis.',
            'channels' => [
                [
                    'group' => 'QRIS Real-Time (Paling Praktis)',
                    'code' => 'tripay_qris',
                    'name' => 'QRIS Real-Time (Semua Bank & E-Wallet)',
                    'desc' => 'Scan langsung via BCA Mobile, Livin by Mandiri, BRImo, GoPay, OVO, ShopeePay, DANA.',
                    'fee' => '0.7%',
                    'icon' => '📱',
                    'recommended' => true,
                ],
                [
                    'group' => 'Virtual Account Bank',
                    'code' => 'tripay_bca_va',
                    'name' => 'BCA Virtual Account',
                    'desc' => 'Verifikasi instan otomatis 24 jam bebas biaya admin.',
                    'fee' => 'Rp 0',
                    'icon' => '🏦',
                ],
                [
                    'group' => 'Virtual Account Bank',
                    'code' => 'tripay_mandiri_va',
                    'name' => 'Mandiri Virtual Account',
                    'desc' => 'Bayar via Livin by Mandiri, ATM, atau Internet Banking.',
                    'fee' => 'Rp 0',
                    'icon' => '🏦',
                ],
                [
                    'group' => 'Virtual Account Bank',
                    'code' => 'tripay_bri_va',
                    'name' => 'BRI Virtual Account (BRIVA)',
                    'desc' => 'Bayar via BRImo atau ATM BRI.',
                    'fee' => 'Rp 0',
                    'icon' => '🏦',
                ],
                [
                    'group' => 'Virtual Account Bank',
                    'code' => 'tripay_bni_va',
                    'name' => 'BNI Virtual Account',
                    'desc' => 'Bayar via BNI Mobile Banking atau ATM.',
                    'fee' => 'Rp 0',
                    'icon' => '🏦',
                ],
                [
                    'group' => 'Virtual Account Bank',
                    'code' => 'tripay_bsi_va',
                    'name' => 'BSI Virtual Account',
                    'desc' => 'Pembayaran syariah melalui BSI Mobile.',
                    'fee' => 'Rp 0',
                    'icon' => '🌙',
                ],
                [
                    'group' => 'Gerai Kasir Retail',
                    'code' => 'tripay_alfamart',
                    'name' => 'Alfamart / Alfamidi',
                    'desc' => 'Bayar tunai di kasir gerai Alfamart terdekat.',
                    'fee' => 'Rp 2.500',
                    'icon' => '🏪',
                ],
                [
                    'group' => 'Gerai Kasir Retail',
                    'code' => 'tripay_indomaret',
                    'name' => 'Indomaret',
                    'desc' => 'Bayar tunai di kasir gerai Indomaret terdekat.',
                    'fee' => 'Rp 2.500',
                    'icon' => '🏪',
                ],
            ]
        ]);
    }

    // ==========================================
    // PAYMENT WEBHOOK & MOCK SETTLEMENT
    // ==========================================

    public function tripayWebhook(Request $request)
    {
        $payload = $request->all();
        $merchantRef = $payload['merchant_ref'] ?? $payload['reference'] ?? null;
        $status = strtoupper($payload['status'] ?? '');

        if (!$merchantRef) {
            return response()->json(['success' => false, 'message' => 'Missing merchant_ref'], 422);
        }

        $targetStatus = match ($status) {
            'PAID', 'SUCCESS', 'SETTLEMENT' => 'paid',
            'EXPIRED' => 'expired',
            'FAILED' => 'failed',
            default => 'pending'
        };

        if ($targetStatus !== 'pending') {
            $this->settlePayment($merchantRef, $targetStatus, $payload);
        }

        return response()->json(['success' => true]);
    }

    public function webhook(Request $request)
    {
        $payload = $request->all();
        $orderId = $payload['order_id'] ?? $payload['orderId'] ?? null;
        $status = strtolower($payload['status'] ?? $payload['transaction_status'] ?? '');

        if (!$orderId) {
            return response()->json(['error' => 'Missing order_id'], 422);
        }

        $paymentStatus = match ($status) {
            'settlement', 'capture', 'paid', 'success' => 'paid',
            'expire', 'expired' => 'expired',
            'deny', 'cancel', 'failed' => 'failed',
            default => 'pending',
        };

        if ($paymentStatus === 'pending') {
            return response()->json(['ok' => true]);
        }

        $this->settlePayment($orderId, $paymentStatus, $payload);

        return response()->json(['ok' => true]);
    }

    public function mockSettle(Request $request)
    {
        $validated = $request->validate([
            'orderId' => 'nullable',
            'gatewayRef' => 'nullable',
            'paymentId' => 'nullable',
        ]);

        $user = $request->user();

        $payment = null;
        if (!empty($validated['gatewayRef'])) {
            $payment = Payment::where('gateway_ref', $validated['gatewayRef'])->first();
        } elseif (!empty($validated['paymentId'])) {
            $payment = Payment::find($validated['paymentId']);
        } elseif (!empty($validated['orderId'])) {
            $order = Order::with('payment')->find($validated['orderId']);
            if ($order) {
                $payment = $order->payment;
            }
        }

        if (!$payment) {
            return response()->json(['error' => 'Transaksi pembayaran tidak ditemukan.'], 404);
        }

        if ($payment->status === 'paid') {
            return response()->json(['ok' => true, 'message' => 'Sudah dibayar sebelumnya.']);
        }

        $this->settlePayment($payment->gateway_ref, 'paid', ['source' => 'tripay-sandbox-settlement']);

        return response()->json([
            'ok' => true,
            'message' => 'Pembayaran berhasil diselesaikan via Tripay Sandbox! Stok telah didekrementasi & pesanan diproses.',
            'gatewayRef' => $payment->gateway_ref,
        ]);
    }

    private function settlePayment(string $gatewayRef, string $targetStatus, array $rawPayload = [])
    {
        DB::transaction(function () use ($gatewayRef, $targetStatus, $rawPayload) {
            $payment = Payment::where('gateway_ref', $gatewayRef)->lockForUpdate()->first();
            if (!$payment || $payment->status !== 'pending') {
                return; // Idempotent: already processed
            }

            $payment->status = $targetStatus;
            $payment->raw_webhook = $rawPayload;
            if ($targetStatus === 'paid') {
                $payment->paid_at = Carbon::now();
            }
            $payment->save();

            $orders = Order::with('items')->where('payment_id', $payment->id)->get();

            if ($targetStatus === 'paid') {
                foreach ($orders as $order) {
                    $order->status = 'paid';
                    $order->save();

                    // Convert reserved stock -> permanently sold
                    foreach ($order->items as $item) {
                        $variant = Variant::where('id', $item->variant_id)->lockForUpdate()->first();
                        if ($variant) {
                            $variant->stock_qty = max(0, $variant->stock_qty - $item->qty);
                            $variant->reserved_qty = max(0, $variant->reserved_qty - $item->qty);
                            $variant->version += 1;
                            $variant->save();

                            StockMovement::create([
                                'variant_id' => $variant->id,
                                'delta' => -$item->qty,
                                'reason' => 'sale',
                                'ref_id' => (string) $order->id,
                            ]);
                        }
                    }

                    // Auto-initialize Shipment record so Tengkulak can allocate armada & track status
                    $existingShipment = Shipment::where('order_id', $order->id)->first();
                    if (!$existingShipment) {
                        $trackingCode = 'TRK-' . strtoupper(Str::random(10));
                        $shipment = Shipment::create([
                            'order_id' => $order->id,
                            'vehicle_id' => null,
                            'route_id' => null,
                            'status' => 'preparing',
                            'tracking_code' => $trackingCode,
                        ]);

                        ShipmentEvent::create([
                            'shipment_id' => $shipment->id,
                            'status' => 'preparing',
                            'note' => 'Pembayaran terkonfirmasi lunas. Petani (' . ($order->seller ? $order->seller->name : 'Produsen') . ') sedang menyiapkan komoditas di lahan.',
                        ]);
                    }
                }
            } else {
                // Expired or cancelled: release reserved quantities
                foreach ($orders as $order) {
                    $order->status = ($targetStatus === 'expired') ? 'expired' : 'cancelled';
                    $order->save();

                    foreach ($order->items as $item) {
                        $variant = Variant::where('id', $item->variant_id)->lockForUpdate()->first();
                        if ($variant) {
                            $variant->reserved_qty = max(0, $variant->reserved_qty - $item->qty);
                            $variant->version += 1;
                            $variant->save();

                            StockMovement::create([
                                'variant_id' => $variant->id,
                                'delta' => $item->qty,
                                'reason' => 'release',
                                'ref_id' => (string) $order->id,
                            ]);
                        }
                    }
                }
            }
        });
    }

    // ==========================================
    // NEGOTIATION MODULE (B2B BIDDING)
    // ==========================================

    public function storeNegotiation(Request $request)
    {
        $variantId = $request->input('variantId', $request->input('variant_id'));
        $qty = $request->input('qty', $request->input('quantity', $request->input('requested_volume')));
        $unitPrice = $request->input('unitPrice', $request->input('unit_price', $request->input('offered_price')));
        $message = $request->input('message', $request->input('buyer_note'));

        $request->merge([
            'variantId' => $variantId,
            'qty' => $qty,
            'unitPrice' => $unitPrice,
            'message' => $message,
        ]);

        $validated = $request->validate([
            'variantId' => 'required|exists:variants,id',
            'qty' => 'required|numeric|min:1',
            'unitPrice' => 'required|numeric|min:100',
            'message' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        if ($user->role !== 'pembeli' || $user->buyer_type !== 'b2b') {
            return response()->json(['error' => 'Fitur negosiasi harga hanya tersedia untuk akun Pembeli B2B terverifikasi.'], 403);
        }

        $variant = Variant::with('commodity')->findOrFail($validated['variantId']);
        if ($variant->commodity->curation !== 'approved' || !$variant->commodity->is_active) {
            return response()->json(['error' => 'Komoditas tidak tersedia.'], 404);
        }

        if ($validated['qty'] > $variant->available_stock) {
            return response()->json(['error' => "Kuantitas melebihi stok tersedia ({$variant->available_stock})"], 422);
        }

        if ($validated['unitPrice'] < ($variant->price * 0.5)) {
            return response()->json(['error' => 'Penawaran harga terlalu rendah (minimal 50% dari harga katalog).'], 422);
        }

        $negoId = DB::transaction(function () use ($user, $variant, $validated) {
            $nego = Negotiation::create([
                'buyer_id' => $user->id,
                'seller_id' => $variant->commodity->farmer_id,
                'variant_id' => $variant->id,
                'qty' => $validated['qty'],
                'status' => 'open',
                'expires_at' => Carbon::now()->addDays(3),
            ]);

            NegotiationOffer::create([
                'negotiation_id' => $nego->id,
                'from_user_id' => $user->id,
                'unit_price' => $validated['unitPrice'],
                'message' => $validated['message'] ?? null,
            ]);

            return $nego->id;
        });

        return response()->json(['id' => $negoId], 201);
    }

    public function listNegotiations(Request $request)
    {
        $user = $request->user();

        $query = Negotiation::with(['variant.commodity', 'offers.fromUser', 'buyer']);

        if ($user->role === 'pembeli') {
            $query->where('buyer_id', $user->id);
        } elseif ($user->role === 'petani') {
            $query->where('seller_id', $user->id);
        } elseif ($user->role === 'admin_tengkulak') {
            $query->where(function ($q) use ($user) {
                $q->where('seller_id', $user->id)
                  ->orWhereHas('variant.commodity', fn($c) => $c->where('region_id', $user->region_id));
            });
        }

        $negos = $query->latest()->get()->map(function ($n) {
            $lastOffer = $n->offers->last();
            $commName = $n->variant && $n->variant->commodity ? $n->variant->commodity->name : 'Komoditas';
            $grade = $n->variant ? $n->variant->grade : 'Grade A';
            $unit = $n->variant ? $n->variant->unit : 'kg';
            $lastPrice = $lastOffer ? (float)$lastOffer->unit_price : (float)($n->variant ? $n->variant->price : 0);

            return [
                'id' => $n->id,
                'variant_id' => $n->variant_id,
                'variant_name' => "{$commName} - {$grade}",
                'grade' => $grade,
                'unit' => $unit,
                'list_price' => $n->variant ? (float)$n->variant->price : 0,
                'qty' => (float)$n->qty,
                'requested_volume' => (float)$n->qty,
                'status' => $n->status,
                'agreed_price' => $n->agreed_price,
                'current_offer_price' => $lastPrice,
                'buyer_note' => $lastOffer ? $lastOffer->message : null,
                'latest_offer' => $lastOffer ? [
                    'offered_price' => (float)$lastOffer->unit_price,
                    'message' => $lastOffer->message,
                ] : null,
                'buyer' => [
                    'id' => $n->buyer_id,
                    'name' => $n->buyer ? $n->buyer->name : 'Pembeli',
                    'company_name' => $n->buyer ? ($n->buyer->company_name ?? 'B2B/Umum') : 'B2B',
                ],
                'variant' => [
                    'id' => $n->variant_id,
                    'grade' => $grade,
                    'unit' => $unit,
                    'price' => $n->variant ? (float)$n->variant->price : 0,
                    'commodity' => [
                        'name' => $commName,
                    ],
                ],
                'expires_at' => $n->expires_at,
                'offers' => $n->offers->map(fn($o) => [
                    'from' => $o->from_user_id,
                    'from_name' => $o->fromUser ? $o->fromUser->name : 'Pengguna',
                    'price' => (float)$o->unit_price,
                    'msg' => $o->message,
                    'at' => $o->created_at,
                ]),
            ];
        });

        return response()->json($negos);
    }

    public function counterOffer(Request $request, $id)
    {
        $validated = $request->validate([
            'unitPrice' => 'required|numeric|min:100',
            'message' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $nego = Negotiation::with('offers')->findOrFail($id);

        if ($nego->status !== 'open' || Carbon::now()->gt($nego->expires_at)) {
            return response()->json(['error' => 'Negosiasi sudah ditutup atau kedaluwarsa.'], 409);
        }

        $lastOffer = $nego->offers->last();
        if ($lastOffer && $lastOffer->from_user_id === $user->id) {
            return response()->json(['error' => 'Menunggu tanggapan dari pihak lain.'], 409);
        }

        NegotiationOffer::create([
            'negotiation_id' => $nego->id,
            'from_user_id' => $user->id,
            'unit_price' => $validated['unitPrice'],
            'message' => $validated['message'] ?? null,
        ]);

        return response()->json(['ok' => true], 201);
    }

    public function respondNegotiation(Request $request, $id)
    {
        $validated = $request->validate([
            'accept' => 'required|boolean',
        ]);

        $user = $request->user();
        $nego = Negotiation::with('offers')->findOrFail($id);

        if ($nego->status !== 'open' || Carbon::now()->gt($nego->expires_at)) {
            return response()->json(['error' => 'Negosiasi sudah selesai atau kedaluwarsa.'], 409);
        }

        $lastOffer = $nego->offers->last();

        if (!$validated['accept']) {
            $nego->status = ($user->id === $nego->buyer_id) ? 'cancelled' : 'rejected';
            $nego->save();
            return response()->json(['ok' => true, 'status' => $nego->status]);
        }

        if ($lastOffer && $lastOffer->from_user_id === $user->id) {
            return response()->json(['error' => 'Anda tidak dapat menyetujui penawaran yang Anda buat sendiri.'], 409);
        }

        $nego->status = 'accepted';
        $nego->agreed_price = $lastOffer ? $lastOffer->unit_price : null;
        $nego->expires_at = Carbon::now()->addDays(2); // Deal valid for 2 days to checkout
        $nego->save();

        return response()->json(['ok' => true, 'agreed_price' => $nego->agreed_price]);
    }

    // ==========================================
    // ORDER MANAGEMENT
    // ==========================================

    public function listOrders(Request $request)
    {
        $user = $request->user();

        $query = Order::with(['buyer', 'seller', 'shipment', 'payment', 'items', 'originRegion', 'destRegion']);

        if ($user->role === 'pembeli') {
            $query->where('buyer_id', $user->id);
        } elseif ($user->role === 'petani') {
            $query->where('seller_id', $user->id);
        } elseif ($user->role === 'admin_tengkulak') {
            $query->where(function ($q) use ($user) {
                $q->where('region_id', $user->region_id)
                  ->orWhere('seller_id', $user->id);
            });
        }

        $orders = $query->latest()->take(200)->get()->map(function ($o) {
            $isQris = $o->payment && in_array($o->payment->method, ['qris', 'tripay_qris']);
            $qrUrl = $isQris
                ? 'https://api.qrserver.com/v1/create-qr-code/?size=350x350&data=' . urlencode('00020101021226670016ID.CO.TRIPAY.WWW011893600002011' . $o->payment->id . '52045814530336054' . str_pad((int)$o->payment->amount, 6, '0', STR_PAD_LEFT) . '5802ID5911RUMAH TANI6007JAKARTA6304' . strtoupper(substr(md5($o->payment->gateway_ref), 0, 4)))
                : null;

            return [
                'id' => $o->id,
                'code' => $o->code,
                'status' => $o->status,
                'total' => (float)$o->total,
                'total_weight_kg' => (float)$o->total_weight_kg,
                'created_at' => $o->created_at,
                'buyer' => $o->buyer ? $o->buyer->name : '-',
                'buyer_name' => $o->buyer ? $o->buyer->name : '-',
                'buyer_phone' => $o->buyer ? ($o->buyer->phone ?? '-') : '-',
                'seller' => $o->seller ? $o->seller->name : '-',
                'seller_name' => $o->seller ? $o->seller->name : '-',
                'origin_region' => $o->originRegion ? $o->originRegion->name : '-',
                'dest_region' => $o->destRegion ? $o->destRegion->name : '-',
                'ship_address' => $o->ship_address,
                'tracking_code' => $o->shipment ? $o->shipment->tracking_code : null,
                'shipment_status' => $o->shipment ? $o->shipment->status : 'menunggu_armada',
                'items' => $o->items->map(fn($it) => [
                    'id' => $it->id,
                    'name' => $it->name_snapshot,
                    'grade' => $it->grade,
                    'qty' => (float)$it->qty,
                    'unit_price' => (float)$it->unit_price,
                    'line_total' => (float)$it->line_total,
                ]),
                'payment' => $o->payment ? [
                    'id' => $o->payment->id,
                    'method' => $o->payment->method,
                    'status' => $o->payment->status,
                    'pay_code' => $o->payment->pay_code,
                    'gateway_ref' => $o->payment->gateway_ref,
                    'qr_url' => $qrUrl,
                ] : null,
            ];
        });

        return response()->json($orders);
    }

    public function orderDetail(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::with(['items', 'payment', 'shipment', 'originRegion', 'destRegion'])->findOrFail($id);

        $allowed = ($user->role === 'super_admin') ||
                   ($order->buyer_id === $user->id) ||
                   ($order->seller_id === $user->id) ||
                   ($user->role === 'admin_tengkulak' && $order->region_id === $user->region_id);

        if (!$allowed) {
            return response()->json(['error' => 'Akses pesanan ditolak.'], 403);
        }

        return response()->json([
            'id' => $order->id,
            'code' => $order->code,
            'status' => $order->status,
            'subtotal' => $order->subtotal,
            'tax' => $order->tax,
            'admin_fee' => $order->admin_fee,
            'shipping_cost' => $order->shipping_cost,
            'total' => $order->total,
            'total_weight_kg' => $order->total_weight_kg,
            'ship_address' => $order->ship_address,
            'origin' => $order->originRegion->name,
            'destination' => $order->destRegion->name,
            'items' => $order->items->map(fn($i) => [
                'name' => $i->name_snapshot,
                'grade' => $i->grade,
                'qty' => $i->qty,
                'unit_price' => $i->unit_price,
                'line_total' => $i->line_total,
            ]),
            'payment' => [
                'method' => $order->payment->method,
                'status' => $order->payment->status,
                'pay_code' => $order->payment->pay_code,
                'expires_at' => $order->payment->expires_at,
                'paid_at' => $order->payment->paid_at,
            ],
            'tracking_code' => $order->shipment ? $order->shipment->tracking_code : null,
        ]);
    }

    public function acceptOrder(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::with('shipment')->findOrFail($id);

        $canAccept = ($order->seller_id === $user->id) ||
                     ($user->role === 'admin_tengkulak' && $order->region_id === $user->region_id);

        if (!$canAccept || $order->status !== 'paid') {
            return response()->json(['error' => 'Pesanan belum dibayar atau Anda bukan penjual/pengepul pesanan ini.'], 409);
        }

        $order->status = 'processing';
        $order->save();

        if ($order->shipment) {
            ShipmentEvent::create([
                'shipment_id' => $order->shipment->id,
                'status' => 'picked_up',
                'note' => 'Petani (' . $user->name . ') telah selesai memanen & mengemas komoditas. Siap dijemput armada tengkulak.',
            ]);
        }

        return response()->json(['ok' => true]);
    }

    public function confirmReceived(Request $request, $id)
    {
        $user = $request->user();
        $order = Order::where('id', $id)
            ->where('buyer_id', $user->id)
            ->where('status', 'delivered')
            ->firstOrFail();

        $order->status = 'completed';
        $order->save();

        return response()->json(['ok' => true]);
    }
}

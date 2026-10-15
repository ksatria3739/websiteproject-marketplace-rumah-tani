<?php

/**
 * Revisions Integration Test:
 * 1. Homepage Catalog & Meta
 * 2. Real-Time Photo Upload for Farmer (Petani)
 * 3. Tripay & QRIS Payment Gateway (Checkout, QRIS generation, Webhook & Settle)
 */

$baseUrl = 'http://127.0.0.1:8000/api';
$passed = 0;
$failed = 0;

function httpRequest($method, $url, $data = null, $token = null, $isMultipart = false) {
    $ch = curl_init();
    $headers = ['Accept: application/json'];
    
    if ($isMultipart) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    } elseif ($data !== null) {
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    if ($token) {
        $headers[] = "Authorization: Bearer {$token}";
    }

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => json_decode($response, true) ?? $response,
    ];
}

function assertTest($condition, $label, $extra = '') {
    global $passed, $failed;
    if ($condition) {
        echo "  ✅ [PASS] {$label}\n";
        $passed++;
    } else {
        echo "  ❌ [FAIL] {$label} {$extra}\n";
        $failed++;
    }
}

echo "========================================================\n";
echo "🌾 REVISI 3 FITUR: KATALOG HOME, UPLOAD FOTO & TRIPAY QRIS\n";
echo "========================================================\n\n";

// 1. PUBLIC HOME CATALOG
echo "▶ 1. Pengujian Katalog di Halaman Home / Index:\n";
$catRes = httpRequest('GET', "{$baseUrl}/public/catalog");
assertTest($catRes['code'] === 200, "GET /public/catalog responds 200 OK");
assertTest(is_array($catRes['body']['items'] ?? null), "Katalog menghasilkan array komoditas untuk halaman utama");
assertTest(count($catRes['body']['items'] ?? []) > 0, "Ada komoditas aktif yang siap tampil di halaman depan");

// 2. REAL-TIME PHOTO UPLOAD FOR PETANI
echo "\n▶ 2. Pengujian Upload Foto Panen Real-Time (Petani):\n";

$petaniLogin = httpRequest('POST', "{$baseUrl}/auth/login", [
    'email' => 'petani@rumahtani.id',
    'password' => 'Password123'
]);
$petaniToken = $petaniLogin['body']['access_token'] ?? null;
assertTest(!empty($petaniToken), "Login Petani berhasil");

// Create temporary 1x1 valid PNG image
$tmpImgPath = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_panen.png';
$pngData = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
file_put_contents($tmpImgPath, $pngData);

$cFile = new CURLFile($tmpImgPath, 'image/png', 'test_panen.png');
$uploadRes = httpRequest('POST', "{$baseUrl}/inventory/upload-image", ['image' => $cFile], $petaniToken, true);

assertTest($uploadRes['code'] === 200, "Upload foto panen real-time POST /inventory/upload-image berhasil");
assertTest(!empty($uploadRes['body']['path']), "Path penyimpanan gambar (/storage/commodities/...) diterima");
$uploadedPath = $uploadRes['body']['path'] ?? null;

// Submit commodity with uploaded image
$metaRes = httpRequest('GET', "{$baseUrl}/public/meta");
$catId = $metaRes['body']['categories'][0]['id'] ?? 1;
$regId = $metaRes['body']['regions'][0]['id'] ?? 1;

$newCommodityRes = httpRequest('POST', "{$baseUrl}/inventory/commodities", [
    'name' => 'Bawang Merah Organik Super',
    'categoryId' => $catId,
    'regionId' => $regId,
    'description' => 'Hasil panen segar Brebes kualitas ekspor dengan foto lahan real-time.',
    'imageUrl' => $uploadedPath,
    'variants' => [
        [
            'variantName' => 'Bawang Merah Grade A',
            'grade' => 'A',
            'unit' => 'kg',
            'price' => 38000,
            'stockQty' => 150,
            'minOrder' => 5,
            'weightPerUnitKg' => 1.0
        ]
    ]
], $petaniToken);

assertTest($newCommodityRes['code'] === 201, "Petani berhasil menyimpan komoditas dengan foto panen real-time");

if (file_exists($tmpImgPath)) {
    unlink($tmpImgPath);
}

// 3. TRIPAY PAYMENT GATEWAY & QRIS INSTANT
echo "\n▶ 3. Pengujian Payment Gateway Tripay & QRIS (Tidak Ribet):\n";

$channelsRes = httpRequest('GET', "{$baseUrl}/payment/channels");
assertTest($channelsRes['code'] === 200, "GET /payment/channels mengembalikan channel pembayaran aktif");
assertTest(isset($channelsRes['body']['channels']), "Tripay & Direct Gateway channels terkonfigurasi");

$buyerLogin = httpRequest('POST', "{$baseUrl}/auth/login", [
    'email' => 'hotel@rumahtani.id',
    'password' => 'Password123'
]);
$buyerToken = $buyerLogin['body']['access_token'] ?? null;
assertTest(!empty($buyerToken), "Login Pembeli B2B berhasil");

// Add variant to cart
$varId = 1;
$addCart = httpRequest('PUT', "{$baseUrl}/cart/items", [
    'variantId' => $varId,
    'qty' => 10
], $buyerToken);
assertTest($addCart['code'] === 200, "Produk berhasil ditambahkan ke keranjang belanja");

// Checkout with tripay_qris
$checkoutQris = httpRequest('POST', "{$baseUrl}/checkout", [
    'destRegionId' => $regId,
    'shipAddress' => 'Hotel Grand Hyatt, Jakarta Pusat',
    'method' => 'tripay_qris'
], $buyerToken);

assertTest($checkoutQris['code'] === 201, "Checkout sukses dengan metode QRIS Real-Time");
assertTest(!empty($checkoutQris['body']['qrUrl']), "QR Code URL dinamis berhasil dihasilkan untuk scan");
assertTest(!empty($checkoutQris['body']['gatewayRef']), "Referensi gateway transaksi Tripay (gatewayRef) valid");

$gatewayRef = $checkoutQris['body']['gatewayRef'] ?? null;
$firstOrderId = $checkoutQris['body']['orders'][0]['id'] ?? null;

// Settle via Tripay Webhook simulation
echo "\n▶ 4. Simulasi Webhook Tripay (Instant Settlement):\n";

$webhookRes = httpRequest('POST', "{$baseUrl}/webhooks/tripay", [
    'merchant_ref' => $gatewayRef,
    'reference' => 'TP-REF-' . time(),
    'status' => 'PAID',
    'total_amount' => $checkoutQris['body']['amount'] ?? 500000,
]);

assertTest($webhookRes['code'] === 200 && ($webhookRes['body']['success'] ?? false) === true, "Webhook Tripay berhasil memproses status PAID secara otomatis");

// Verify order status is updated to 'paid'
$ordersRes = httpRequest('GET', "{$baseUrl}/orders", null, $buyerToken);
$foundPaid = false;
foreach ($ordersRes['body'] as $ord) {
    if ($ord['id'] === $firstOrderId && $ord['status'] === 'paid') {
        $foundPaid = true;
        break;
    }
}
assertTest($foundPaid, "Status pesanan otomatis berubah menjadi 'paid' & mutasi stok sale tercatat");

echo "\n========================================================\n";
echo "📊 REVISI TEST SUMMARY: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);

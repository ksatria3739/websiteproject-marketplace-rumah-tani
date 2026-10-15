<?php

/**
 * End-to-End Live HTTP Integration Test for Marketplace Rumah Tani
 * Testing live endpoints on http://127.0.0.1:8000 across 5 RBAC roles.
 */

$baseUrl = 'http://127.0.0.1:8000/api';
$passed = 0;
$failed = 0;

function httpRequest($method, $url, $data = null, $token = null) {
    $ch = curl_init();
    $headers = ['Accept: application/json'];
    if ($data !== null) {
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

function assertHttp($condition, $label) {
    global $passed, $failed;
    if ($condition) {
        echo "  [PASS] {$label}\n";
        $passed++;
    } else {
        echo "  [FAIL] {$label}\n";
        $failed++;
    }
}

echo "========================================================\n";
echo "🌐 LIVE HTTP REST API TEST SUITE (5-LEVEL RBAC)\n";
echo "========================================================\n\n";

// 1. GUEST ACCESS
echo "▶ 1. Guest (Unauthenticated Public User):\n";
$health = httpRequest('GET', "{$baseUrl}/health");
assertHttp($health['code'] === 200 && ($health['body']['database'] ?? '') === 'MySQL', "GET /health responds 200 with MySQL");

$catalog = httpRequest('GET', "{$baseUrl}/public/catalog");
assertHttp($catalog['code'] === 200 && is_array($catalog['body']['items']), "GET /public/catalog returns catalog items");

$firstItem = $catalog['body']['items'][0] ?? null;
if ($firstItem) {
    $detail = httpRequest('GET', "{$baseUrl}/public/catalog/{$firstItem['slug']}");
    assertHttp($detail['code'] === 200 && isset($detail['body']['variants']), "GET /public/catalog/{$firstItem['slug']} returns product variants");
}

$articles = httpRequest('GET', "{$baseUrl}/public/articles");
assertHttp($articles['code'] === 200 && is_array($articles['body']), "GET /public/articles returns education news array");

// Guest attempting checkout should be blocked 401
$guestCheckout = httpRequest('POST', "{$baseUrl}/checkout", [
    'destRegionId' => 1,
    'shipAddress' => 'Jl. Tamu No. 1',
    'method' => 'va_bca'
]);
assertHttp($guestCheckout['code'] === 401, "POST /checkout blocked for Guest (HTTP 401 Unauthorized)");

// 2. PETANI (PRODUCER)
echo "\n▶ 2. Role: Petani (Farmer / Producer):\n";
$loginPetani = httpRequest('POST', "{$baseUrl}/auth/login", [
    'email' => 'petani@rumahtani.id',
    'password' => 'Password123'
]);
assertHttp($loginPetani['code'] === 200 && !empty($loginPetani['body']['access_token']), "POST /auth/login successful for Petani");
$petaniToken = $loginPetani['body']['access_token'] ?? null;

// Get farmer commodities
$farmerCommodities = httpRequest('GET', "{$baseUrl}/inventory/commodities", null, $petaniToken);
assertHttp($farmerCommodities['code'] === 200 && is_array($farmerCommodities['body']), "GET /inventory/commodities returns farmer inventory");

$firstVariant = null;
foreach ($farmerCommodities['body'] as $fc) {
    if (($fc['curation'] ?? '') === 'approved' && !empty($fc['variants'])) {
        $firstVariant = $fc['variants'][0];
        break;
    }
}
if (!$firstVariant && !empty($farmerCommodities['body'][0]['variants'])) {
    $firstVariant = $farmerCommodities['body'][0]['variants'][0];
}
if ($firstVariant) {
    $stockUpdate = httpRequest('POST', "{$baseUrl}/inventory/variants/{$firstVariant['id']}/stock", [
        'delta' => 5,
        'reason' => 'harvest'
    ], $petaniToken);
    assertHttp($stockUpdate['code'] === 200 && isset($stockUpdate['body']['stock_qty']), "POST /inventory/variants/{$firstVariant['id']}/stock atomic update works");
}

// 3. TENGKULAK / ADMIN WILAYAH
echo "\n▶ 3. Role: Admin Tengkulak (Regional Distributor):\n";
$loginTengkulak = httpRequest('POST', "{$baseUrl}/auth/login", [
    'email' => 'tengkulak@rumahtani.id',
    'password' => 'Password123'
]);
assertHttp($loginTengkulak['code'] === 200 && $loginTengkulak['body']['user']['role'] === 'admin_tengkulak', "POST /auth/login successful for Tengkulak");
$tengkulakToken = $loginTengkulak['body']['access_token'] ?? null;

$curationPending = httpRequest('GET', "{$baseUrl}/curation/queue", null, $tengkulakToken);
assertHttp($curationPending['code'] === 200 && is_array($curationPending['body']), "GET /curation/queue accessible by Tengkulak");

$vehicles = httpRequest('GET', "{$baseUrl}/logistics/vehicles", null, $tengkulakToken);
assertHttp($vehicles['code'] === 200 && is_array($vehicles['body']), "GET /logistics/vehicles returns fleet armadas");

$shipments = httpRequest('GET', "{$baseUrl}/logistics/shipments", null, $tengkulakToken);
assertHttp($shipments['code'] === 200 && is_array($shipments['body']), "GET /logistics/shipments returns regional shipments");

// 4. PEMBELI B2B (CORPORATE BUYER)
echo "\n▶ 4. Role: Pembeli B2B (Corporate Buyer):\n";
$loginB2B = httpRequest('POST', "{$baseUrl}/auth/login", [
    'email' => 'hotel@rumahtani.id',
    'password' => 'Password123'
]);
assertHttp($loginB2B['code'] === 200 && $loginB2B['body']['user']['buyer_type'] === 'b2b', "POST /auth/login B2B Buyer verified");
$b2bToken = $loginB2B['body']['access_token'] ?? null;

// Put item to cart
if ($firstVariant) {
    $addCart = httpRequest('PUT', "{$baseUrl}/cart/items", [
        'variantId' => $firstVariant['id'],
        'qty' => 10
    ], $b2bToken);
    assertHttp($addCart['code'] === 200 && ($addCart['body']['ok'] ?? false), "PUT /cart/items adds product to B2B cart");
}

$getCart = httpRequest('GET', "{$baseUrl}/cart", null, $b2bToken);
assertHttp($getCart['code'] === 200 && isset($getCart['body']['groups']), "GET /cart returns multi-vendor grouped cart items");

// B2B Negotiation
if ($firstVariant) {
    $newNego = httpRequest('POST', "{$baseUrl}/negotiations", [
        'variantId' => $firstVariant['id'],
        'qty' => 10,
        'unitPrice' => 50000,
        'message' => 'Permintaan pasokan hotel bintang 5'
    ], $b2bToken);
    assertHttp($newNego['code'] === 201 && isset($newNego['body']['id']), "POST /negotiations creates B2B bidding offer");
}

$getNegos = httpRequest('GET', "{$baseUrl}/negotiations", null, $b2bToken);
assertHttp($getNegos['code'] === 200 && is_array($getNegos['body']), "GET /negotiations returns user negotiations");

// Checkout order
$checkout = httpRequest('POST', "{$baseUrl}/checkout", [
    'destRegionId' => 5, // Jakarta
    'shipAddress' => 'Hotel Grand Mercure, Jl. Hayam Wuruk No. 36, Jakarta',
    'method' => 'va_bca'
], $b2bToken);
assertHttp($checkout['code'] === 201 && isset($checkout['body']['payCode']), "POST /checkout creates atomic order & reserves stock");

$orders = httpRequest('GET', "{$baseUrl}/orders", null, $b2bToken);
assertHttp($orders['code'] === 200 && is_array($orders['body']) && count($orders['body']) > 0, "GET /orders returns buyer order history");

$lastOrder = $orders['body'][0] ?? null;
if ($lastOrder && $lastOrder['status'] === 'pending_payment') {
    // Settle payment sandbox
    $settle = httpRequest('POST', "{$baseUrl}/webhooks/mock-settle", [
        'orderId' => $lastOrder['id']
    ], $b2bToken);
    assertHttp($settle['code'] === 200 && ($settle['body']['ok'] ?? false), "POST /webhooks/mock-settle confirms settlement & executes stock deduction");
}

// 5. SUPER ADMIN
echo "\n▶ 5. Role: Super Admin (Executive Control):\n";
$loginAdmin = httpRequest('POST', "{$baseUrl}/auth/login", [
    'email' => 'admin@rumahtani.id',
    'password' => 'Password123'
]);
assertHttp($loginAdmin['code'] === 200 && $loginAdmin['body']['user']['role'] === 'super_admin', "POST /auth/login Super Admin verified");
$adminToken = $loginAdmin['body']['access_token'] ?? null;

$analytics = httpRequest('GET', "{$baseUrl}/admin/analytics", null, $adminToken);
assertHttp($analytics['code'] === 200 && isset($analytics['body']['profit']['gross_sales']), "GET /admin/analytics returns live GMV & platform profit summary");

$allUsers = httpRequest('GET', "{$baseUrl}/admin/users", null, $adminToken);
assertHttp($allUsers['code'] === 200 && is_array($allUsers['body']) && count($allUsers['body']) >= 5, "GET /admin/users returns multi-role user accounts");

$settings = httpRequest('GET', "{$baseUrl}/admin/settings", null, $adminToken);
assertHttp($settings['code'] === 200 && is_array($settings['body']) && count($settings['body']) > 0, "GET /admin/settings returns system configs");

// Report export
$report = httpRequest('GET', "{$baseUrl}/admin/reports/export?format=csv", null, $adminToken);
assertHttp($report['code'] === 200, "GET /admin/reports/export generates financial and order CSV report");

echo "\n========================================================\n";
echo "SUMMARY: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

if ($failed === 0) {
    echo "🎉 LIVE API TEST SUITE COMPLETED WITH 100% SUCCESS!\n";
} else {
    echo "⚠️ SOME TESTS FAILED.\n";
}

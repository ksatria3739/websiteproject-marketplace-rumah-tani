<?php

/**
 * RBAC & L3 Support Verification Test
 * 
 * Verifies:
 * 1. Admin Support (`support@rumahtani.id`) login.
 * 2. Admin Support access to commodities inventory across farmers.
 * 3. Admin Support stock adjustment on behalf of farmer (L3 Support).
 * 4. Admin Support user role management:
 *    - Allowed to update role to: pembeli, petani, admin_tengkulak.
 *    - Forbidden (403) from promoting to super_admin or admin.
 * 5. Admin Tengkulak (`tengkulak@rumahtani.id`):
 *    - Forbidden (403) from updating user roles (PATCH /api/admin/users/{id}/role).
 *    - Forbidden (403) from direct stock adjustment on behalf of farmer.
 * 6. Super Admin (`admin@rumahtani.id`):
 *    - Allowed to manage all roles, including promoting to admin.
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
echo "🛡️  ADMIN SUPPORT L3 & SUPER ADMIN ROLE MANAGEMENT TEST\n";
echo "========================================================\n\n";

// 1. Authenticate Actors
echo "▶ 1. Authenticating test users:\n";

$superRes = httpRequest('POST', "{$baseUrl}/auth/login", [
    'email' => 'admin@rumahtani.id',
    'password' => 'Password123'
]);
assertTest($superRes['code'] === 200, "Super Admin login succeeds");
$superToken = $superRes['body']['access_token'] ?? $superRes['body']['token'] ?? null;

$supportRes = httpRequest('POST', "{$baseUrl}/auth/login", [
    'email' => 'support@rumahtani.id',
    'password' => 'Password123'
]);
assertTest($supportRes['code'] === 200, "Admin Support L3 login succeeds");
$supportToken = $supportRes['body']['access_token'] ?? $supportRes['body']['token'] ?? null;

$tengkulakRes = httpRequest('POST', "{$baseUrl}/auth/login", [
    'email' => 'tengkulak@rumahtani.id',
    'password' => 'Password123'
]);
assertTest($tengkulakRes['code'] === 200, "Admin Tengkulak login succeeds");
$tengkulakToken = $tengkulakRes['body']['access_token'] ?? $tengkulakRes['body']['token'] ?? null;

$petaniRes = httpRequest('POST', "{$baseUrl}/auth/login", [
    'email' => 'petani@rumahtani.id',
    'password' => 'Password123'
]);
assertTest($petaniRes['code'] === 200, "Petani login succeeds");
$petaniToken = $petaniRes['body']['access_token'] ?? $petaniRes['body']['token'] ?? null;

// 2. Admin Support L3: View Commodities & Stock Adjustment
echo "\n▶ 2. Admin Support L3 Stock Management (L3 Support):\n";

$commoditiesRes = httpRequest('GET', "{$baseUrl}/inventory/commodities", null, $supportToken);
assertTest($commoditiesRes['code'] === 200 && is_array($commoditiesRes['body']), "Admin Support can list all farmer commodities");

$firstCommodity = $commoditiesRes['body'][0] ?? null;
$firstVariant = $firstCommodity['variants'][0] ?? null;
$variantId = $firstVariant['id'] ?? null;
$initialStock = $firstVariant['physical_stock'] ?? 0;

assertTest($variantId !== null, "Found test commodity variant (ID: {$variantId}, Initial: {$initialStock} kg)");

if ($variantId) {
    // Admin Support adds stock with reason: 'l3_support'
    $adjustRes = httpRequest('POST', "{$baseUrl}/inventory/variants/{$variantId}/stock", [
        'delta' => 15,
        'reason' => 'l3_support',
        'notes' => 'Penambahan darurat dari meja Support L3'
    ], $supportToken);
    
    assertTest(
        $adjustRes['code'] === 200 && ($adjustRes['body']['movement']['reason'] ?? '') === 'l3_support',
        "Admin Support successfully added +15 kg stock with reason 'l3_support'"
    );

    // Rollback delta to keep state clean
    $revertRes = httpRequest('POST', "{$baseUrl}/inventory/variants/{$variantId}/stock", [
        'delta' => -15,
        'reason' => 'l3_support',
        'notes' => 'Revert test adjustment'
    ], $supportToken);
    assertTest($revertRes['code'] === 200, "Revert test adjustment succeeds");
}

// 3. Admin Support L3: Role Management Boundary Enforcement
echo "\n▶ 3. Admin Support Role Management Bounds Check:\n";

// Fetch user list
$usersRes = httpRequest('GET', "{$baseUrl}/admin/users", null, $supportToken);
assertTest($usersRes['code'] === 200 && is_array($usersRes['body']), "Admin Support can list users for management");

// Find a regular buyer user to test role modifications
$testTargetUser = null;
foreach ($usersRes['body'] as $u) {
    if ($u['email'] === 'umum@rumahtani.id') {
        $testTargetUser = $u;
        break;
    }
}
$targetUserId = $testTargetUser['id'] ?? 5;

// A. Support Admin changing target to 'petani' -> SHOULD SUCCEED
$promotePetani = httpRequest('PATCH', "{$baseUrl}/admin/users/{$targetUserId}/role", [
    'role' => 'petani'
], $supportToken);
assertTest($promotePetani['code'] === 200, "Admin Support can promote user to 'petani'");

// B. Support Admin changing target to 'admin_tengkulak' -> SHOULD SUCCEED
$promoteTengkulak = httpRequest('PATCH', "{$baseUrl}/admin/users/{$targetUserId}/role", [
    'role' => 'admin_tengkulak'
], $supportToken);
assertTest($promoteTengkulak['code'] === 200, "Admin Support can promote user to 'admin_tengkulak'");

// C. Support Admin trying to promote target to 'admin' -> MUST BE FORBIDDEN (403)
$promoteAdminForbidden = httpRequest('PATCH', "{$baseUrl}/admin/users/{$targetUserId}/role", [
    'role' => 'admin'
], $supportToken);
assertTest($promoteAdminForbidden['code'] === 403, "Admin Support CANNOT promote user to 'admin' (HTTP 403)");

// D. Support Admin trying to promote target to 'super_admin' -> MUST BE FORBIDDEN (403)
$promoteSuperForbidden = httpRequest('PATCH', "{$baseUrl}/admin/users/{$targetUserId}/role", [
    'role' => 'super_admin'
], $supportToken);
assertTest($promoteSuperForbidden['code'] === 403, "Admin Support CANNOT promote user to 'super_admin' (HTTP 403)");

// Revert user back to pembeli
$revertUser = httpRequest('PATCH', "{$baseUrl}/admin/users/{$targetUserId}/role", [
    'role' => 'pembeli'
], $supportToken);
assertTest($revertUser['code'] === 200, "Admin Support restored user back to 'pembeli'");

// 4. Admin Tengkulak Boundary Check (Must NOT have access to user role management)
echo "\n▶ 4. Admin Tengkulak Boundary Check (Strict Isolation):\n";

$tengkulakRoleAttempt = httpRequest('PATCH', "{$baseUrl}/admin/users/{$targetUserId}/role", [
    'role' => 'petani'
], $tengkulakToken);
assertTest($tengkulakRoleAttempt['code'] === 403, "Admin Tengkulak is strictly BLOCKED from PATCH /admin/users/{id}/role (HTTP 403)");

$tengkulakStockAttempt = httpRequest('POST', "{$baseUrl}/inventory/variants/{$variantId}/stock", [
    'delta' => 10,
    'reason' => 'l3_support'
], $tengkulakToken);
assertTest($tengkulakStockAttempt['code'] === 403, "Admin Tengkulak is BLOCKED from farmer stock adjustment (HTTP 403)");

// 5. Super Admin Global Authority Check
echo "\n▶ 5. Super Admin Global Role Management Authority:\n";

// Super Admin CAN promote to 'admin'
$superPromoteAdmin = httpRequest('PATCH', "{$baseUrl}/admin/users/{$targetUserId}/role", [
    'role' => 'admin'
], $superToken);
assertTest($superPromoteAdmin['code'] === 200, "Super Admin CAN promote user to 'admin'");

// Revert user back to pembeli
$superRevert = httpRequest('PATCH', "{$baseUrl}/admin/users/{$targetUserId}/role", [
    'role' => 'pembeli'
], $superToken);
assertTest($superRevert['code'] === 200, "Super Admin restored user back to 'pembeli'");

echo "\n========================================================\n";
echo "📊 RESULTS: {$passed} Passed, {$failed} Failed\n";
echo "========================================================\n";

if ($failed > 0) {
    exit(1);
}
exit(0);

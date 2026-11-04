<?php
// ⚠️ CORS and Content-Type headers MUST be set first, before any output
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
header('Access-Control-Allow-Headers: Origin, Content-Type, Authorization, X-Requested-With, Accept');
header('Access-Control-Max-Age: 86400');
header('Access-Control-Allow-Credentials: false');
header('Content-Type: application/json; charset=UTF-8');

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'CORS preflight OK']);
    exit;
}

/**
 * POST /api/customer-groups-create.php
 * Create a new customer group
 * Body: { name, description?, type?, memberIds?, smartRules?, pricingRules?,
 *         defaultDiscount?, tags?, color?, isActive?, createdBy? }
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/group-helpers.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    $body = json_decode(file_get_contents('php://input'), true);
    
    if (!$body || empty($body['name'])) {
        sendJSON(['success' => false, 'error' => 'Group name is required'], 400);
    }
    
    $name = $body['name'];
    $description = $body['description'] ?? null;
    $type = ($body['type'] ?? 'manual') === 'smart' ? 'smart' : 'manual';
    $memberIds = json_encode(array_values($body['memberIds'] ?? $body['member_ids'] ?? []));
    $smartRules = isset($body['smartRules']) || isset($body['smart_rules'])
        ? json_encode($body['smartRules'] ?? $body['smart_rules'])
        : null;
    $pricingRules = json_encode(array_values($body['pricingRules'] ?? $body['pricing_rules'] ?? []));
    $defaultDiscount = isset($body['defaultDiscount']) || isset($body['default_discount'])
        ? json_encode($body['defaultDiscount'] ?? $body['default_discount'])
        : null;
    $tags = json_encode(array_values($body['tags'] ?? []));
    $color = $body['color'] ?? null;
    $isActive = isset($body['isActive']) ? ($body['isActive'] ? 1 : 0) : (isset($body['is_active']) ? ($body['is_active'] ? 1 : 0) : 1);
    $createdBy = $body['createdBy'] ?? $body['created_by'] ?? 'admin';
    
    $conn = getDB();
    
    $sql = "INSERT INTO customer_groups 
            (name, description, type, member_ids, smart_rules, pricing_rules, default_discount, tags, color, is_active, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        sendJSON(['success' => false, 'error' => 'Query prepare failed'], 500);
    }
    
    $stmt->bind_param(
        'sssssssssis',
        $name, $description, $type, $memberIds, $smartRules, $pricingRules,
        $defaultDiscount, $tags, $color, $isActive, $createdBy
    );
    
    if (!$stmt->execute()) {
        sendJSON(['success' => false, 'error' => 'Failed to create group: ' . $stmt->error], 500);
    }
    
    $groupId = $stmt->insert_id;
    $stmt->close();
    
    $row = fetchGroupRow($conn, $groupId);
    $conn->close();
    
    sendJSON([
        'success' => true,
        'message' => 'Group created successfully',
        'data' => formatGroupRow($row)
    ], 201);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

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
 * PUT /api/customer-groups-update.php
 * Update a customer group
 * Body: { id, ...fields to update }
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/group-helpers.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    $body = json_decode(file_get_contents('php://input'), true);
    
    $id = (int) ($body['id'] ?? 0);
    if (!$id) {
        sendJSON(['success' => false, 'error' => 'Group ID is required'], 400);
    }
    
    $conn = getDB();
    
    if (!fetchGroupRow($conn, $id)) {
        sendJSON(['success' => false, 'error' => 'Group not found'], 404);
    }
    
    $updateFields = [];
    $types = '';
    $values = [];
    
    // Scalar fields: apiKey => [column, bindType]
    $scalarMap = [
        'name' => ['name', 's'],
        'description' => ['description', 's'],
        'type' => ['type', 's'],
        'color' => ['color', 's'],
        'createdBy' => ['created_by', 's'],
        'created_by' => ['created_by', 's'],
    ];
    
    foreach ($scalarMap as $key => [$column, $bindType]) {
        if (array_key_exists($key, $body)) {
            $updateFields[] = "$column = ?";
            $types .= $bindType;
            $values[] = $body[$key];
        }
    }
    
    if (isset($body['type']) && !in_array($body['type'], ['manual', 'smart'], true)) {
        sendJSON(['success' => false, 'error' => 'Invalid group type'], 400);
    }
    
    // JSON fields: apiKey => column
    $jsonMap = [
        'memberIds' => 'member_ids',
        'member_ids' => 'member_ids',
        'smartRules' => 'smart_rules',
        'smart_rules' => 'smart_rules',
        'pricingRules' => 'pricing_rules',
        'pricing_rules' => 'pricing_rules',
        'defaultDiscount' => 'default_discount',
        'default_discount' => 'default_discount',
        'tags' => 'tags',
    ];
    
    $handledJson = [];
    foreach ($jsonMap as $key => $column) {
        if (array_key_exists($key, $body) && !isset($handledJson[$column])) {
            $updateFields[] = "$column = ?";
            $types .= 's';
            $values[] = $body[$key] === null ? null : json_encode($body[$key]);
            $handledJson[$column] = true;
        }
    }
    
    if (isset($body['isActive']) || isset($body['is_active'])) {
        $isActive = isset($body['isActive']) ? $body['isActive'] : $body['is_active'];
        $updateFields[] = "is_active = ?";
        $types .= 'i';
        $values[] = $isActive ? 1 : 0;
    }
    
    if (empty($updateFields)) {
        sendJSON(['success' => false, 'error' => 'No fields to update'], 400);
    }
    
    $updateFields[] = "updated_at = NOW()";
    
    $sql = "UPDATE customer_groups SET " . implode(', ', $updateFields) . " WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        sendJSON(['success' => false, 'error' => 'Query prepare failed'], 500);
    }
    
    $types .= 'i';
    $values[] = $id;
    $stmt->bind_param($types, ...$values);
    
    if (!$stmt->execute()) {
        sendJSON(['success' => false, 'error' => 'Failed to update group'], 500);
    }
    $stmt->close();
    
    $row = fetchGroupRow($conn, $id);
    $conn->close();
    
    sendJSON([
        'success' => true,
        'message' => 'Group updated successfully',
        'data' => formatGroupRow($row)
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

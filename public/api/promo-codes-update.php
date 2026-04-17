<?php
/**
 * PUT /api/promo-codes-update.php
 * Update a promo code (Admin only)
 */

require_once __DIR__ . '/../../config.php';

// Additional CORS headers for this endpoint
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    $body = json_decode(file_get_contents('php://input'), true);
    
    if (!$body) {
        sendJSON(['success' => false, 'error' => 'Invalid JSON body'], 400);
    }
    
    $id = $body['id'] ?? null;
    
    if (!$id) {
        sendJSON(['success' => false, 'error' => 'Promo code ID is required'], 400);
    }
    
    $conn = getDB();
    
    // Check if code exists
    $checkSql = "SELECT id FROM promo_codes WHERE id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param('i', $id);
    $checkStmt->execute();
    
    if ($checkStmt->get_result()->num_rows === 0) {
        sendJSON(['success' => false, 'error' => 'Promo code not found'], 404);
    }
    $checkStmt->close();
    
    // Build update query dynamically
    $updates = [];
    $types = '';
    $values = [];
    
    if (isset($body['description'])) {
        $updates[] = "description = ?";
        $types .= 's';
        $values[] = $body['description'];
    }
    
    if (isset($body['discountValue'])) {
        $updates[] = "discount_value = ?";
        $types .= 'd';
        $values[] = $body['discountValue'];
    }
    
    if (isset($body['validUntil'])) {
        $updates[] = "valid_until = ?";
        $types .= 's';
        $values[] = $body['validUntil'];
    }
    
    if (isset($body['isActive'])) {
        $updates[] = "is_active = ?";
        $types .= 'i';
        $values[] = $body['isActive'] ? 1 : 0;
    }
    
    if (isset($body['maxUses'])) {
        $updates[] = "max_uses = ?";
        $types .= 'i';
        $values[] = $body['maxUses'];
    }
    
    if (isset($body['minBookingAmount'])) {
        $updates[] = "min_booking_amount = ?";
        $types .= 'd';
        $values[] = $body['minBookingAmount'];
    }
    
    if (isset($body['maxDiscountAmount'])) {
        $updates[] = "max_discount_amount = ?";
        $types .= 'd';
        $values[] = $body['maxDiscountAmount'];
    }
    
    if (empty($updates)) {
        sendJSON(['success' => false, 'error' => 'No fields to update'], 400);
    }
    
    $sql = "UPDATE promo_codes SET " . implode(', ', $updates) . " WHERE id = ?";
    $types .= 'i';
    $values[] = $id;
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        sendJSON(['success' => false, 'error' => 'Query prepare failed'], 500);
    }
    
    $stmt->bind_param($types, ...$values);
    
    if (!$stmt->execute()) {
        sendJSON(['success' => false, 'error' => 'Failed to update promo code'], 400);
    }
    
    $stmt->close();
    
    // Fetch the updated code to return it
    $selectSql = "SELECT id, code, description, discount_type, discount_value, 
                         min_booking_amount, max_discount_amount, max_uses, current_uses,
                         valid_from, valid_until, is_active, created_at, updated_at
                  FROM promo_codes WHERE id = ?";
    $selectStmt = $conn->prepare($selectSql);
    $selectStmt->bind_param('i', $id);
    $selectStmt->execute();
    
    $result = $selectStmt->get_result();
    $updatedCode = $result->fetch_assoc();
    $selectStmt->close();
    $conn->close();
    
    sendJSON([
        'success' => true,
        'message' => 'Promo code updated successfully',
        'data' => $updatedCode
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

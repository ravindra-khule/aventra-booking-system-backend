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
 * POST /api/promo-codes-create.php
 * Create a new promo code (Admin only)
 */

require_once __DIR__ . '/../../config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    $body = json_decode(file_get_contents('php://input'), true);
    
    if (!$body) {
        sendJSON(['success' => false, 'error' => 'Invalid JSON body'], 400);
    }
    
    // Validate required fields
    $code = $body['code'] ?? null;
    $description = $body['description'] ?? '';
    $discountType = $body['discountType'] ?? 'percentage';
    $discountValue = $body['discountValue'] ?? 0;
    $validFrom = $body['validFrom'] ?? date('Y-m-d');
    $validUntil = $body['validUntil'] ?? date('Y-m-d', strtotime('+30 days'));
    $minBookingAmount = $body['minBookingAmount'] ?? 0;
    $maxDiscountAmount = $body['maxDiscountAmount'] ?? null;
    $maxUses = $body['maxUses'] ?? null;
    
    if (!$code || !$discountValue) {
        sendJSON(['success' => false, 'error' => 'Code and discount value are required'], 400);
    }
    
    // Validate discount type
    if (!in_array($discountType, ['percentage', 'fixed'])) {
        sendJSON(['success' => false, 'error' => 'Invalid discount type'], 400);
    }
    
    $conn = getDB();
    
    // Check if code already exists
    $checkSql = "SELECT id FROM promo_codes WHERE code = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param('s', $code);
    $checkStmt->execute();
    
    if ($checkStmt->get_result()->num_rows > 0) {
        sendJSON(['success' => false, 'error' => 'Promo code already exists'], 400);
    }
    $checkStmt->close();
    
    // Insert new promo code
    $sql = "INSERT INTO promo_codes 
            (code, description, discount_type, discount_value, max_uses, max_discount_amount,
             valid_from, valid_until, min_booking_amount, is_active) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        sendJSON(['success' => false, 'error' => 'Query prepare failed'], 500);
    }
    
    // Type string: s=string, d=double, i=integer
    // code(s), description(s), discount_type(s), discount_value(d), max_uses(i), max_discount_amount(d), valid_from(s), valid_until(s), min_booking_amount(d)
    $stmt->bind_param('sssdidssd', 
        $code, 
        $description, 
        $discountType, 
        $discountValue, 
        $maxUses,
        $maxDiscountAmount,
        $validFrom, 
        $validUntil, 
        $minBookingAmount
    );
    
    if (!$stmt->execute()) {
        sendJSON(['success' => false, 'error' => 'Failed to create promo code: ' . $stmt->error], 400);
    }
    
    $id = $stmt->insert_id;
    $stmt->close();
    
    $conn->close();
    
    sendJSON([
        'success' => true,
        'data' => [
            'id' => (int) $id,
            'code' => $code,
            'description' => $description,
            'discount_type' => $discountType,
            'discount_value' => (float) $discountValue,
            'max_uses' => $maxUses ? (int) $maxUses : null,
            'max_discount_amount' => $maxDiscountAmount ? (float) $maxDiscountAmount : null,
            'valid_from' => $validFrom,
            'valid_until' => $validUntil,
            'min_booking_amount' => (float) $minBookingAmount,
            'current_uses' => 0,
            'is_active' => true,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

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
 * GET /api/promo-codes-list.php
 * List all active promo codes
 */

require_once __DIR__ . '/../../config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    $conn = getDB();
    
    $sql = "SELECT 
                id,
                code,
                description,
                discount_type,
                discount_value,
                max_uses,
                current_uses,
                valid_from,
                valid_until,
                min_booking_amount,
                max_discount_amount,
                is_active,
                created_at,
                updated_at
            FROM promo_codes 
            WHERE (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
            ORDER BY is_active DESC, created_at DESC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        sendJSON(['success' => false, 'error' => 'Query failed: ' . $conn->error], 500);
    }
    
    $codes = [];
    while ($row = $result->fetch_assoc()) {
        $codes[] = [
            'id' => (int) $row['id'],
            'code' => $row['code'],
            'description' => $row['description'],
            'discount_type' => $row['discount_type'],
            'discount_value' => (float) $row['discount_value'],
            'max_uses' => $row['max_uses'] ? (int) $row['max_uses'] : null,
            'current_uses' => (int) $row['current_uses'],
            'valid_from' => $row['valid_from'],
            'valid_until' => $row['valid_until'],
            'min_booking_amount' => (float) $row['min_booking_amount'],
            'max_discount_amount' => $row['max_discount_amount'] ? (float) $row['max_discount_amount'] : null,
            'is_active' => (bool) $row['is_active'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
    
    $conn->close();
    
    sendJSON([
        'success' => true,
        'data' => $codes
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

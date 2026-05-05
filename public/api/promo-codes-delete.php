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
 * DELETE /api/promo-codes-delete.php
 * Delete a promo code (Admin only)
 */

require_once __DIR__ . '/../../config.php';

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $body = json_decode(file_get_contents('php://input'), true);
    
    if (!$body) {
        sendJSON(['success' => false, 'error' => 'Invalid JSON body'], 400);
    }
    
    // Support both DELETE and POST with _method=DELETE
    if ($method === 'POST' && isset($body['_method'])) {
        $method = $body['_method'];
    }
    
    if ($method !== 'DELETE' && $method !== 'POST') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    $id = $body['id'] ?? null;
    
    if (!$id) {
        sendJSON(['success' => false, 'error' => 'Promo code ID is required'], 400);
    }
    
    $conn = getDB();
    
    // Soft delete - mark as deleted
    $sql = "UPDATE promo_codes SET deleted_at = NOW(), is_active = 0 WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        sendJSON(['success' => false, 'error' => 'Query prepare failed'], 500);
    }
    
    $stmt->bind_param('i', $id);
    
    if (!$stmt->execute()) {
        sendJSON(['success' => false, 'error' => 'Failed to delete promo code'], 400);
    }
    
    $stmt->close();
    $conn->close();
    
    sendJSON([
        'success' => true,
        'message' => 'Promo code deleted successfully'
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

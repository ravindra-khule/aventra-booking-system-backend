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
 * DELETE /api/bookings-delete.php
 * Delete a booking (soft delete - mark as deleted)
 */

require_once __DIR__ . '/../../config.php';

try {
    // Get the request method
    $method = $_SERVER['REQUEST_METHOD'];
    
    // Get JSON body once
    $body = json_decode(file_get_contents('php://input'), true);
    
    if (!$body) {
        sendJSON(['success' => false, 'error' => 'Invalid JSON body'], 400);
    }
    
    // Support both DELETE and POST with _method=DELETE for compatibility
    if ($method === 'POST' && isset($body['_method'])) {
        $method = $body['_method'];
    }
    
    if ($method !== 'DELETE' && $method !== 'POST') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    $id = $body['id'] ?? null;
    
    if (!$id) {
        sendJSON(['success' => false, 'error' => 'Booking ID is required'], 400);
    }
    
    $conn = getDB();
    
    // Check if booking exists
    $checkSql = "SELECT id FROM bookings WHERE id = ? AND deleted_at IS NULL";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param('i', $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        sendJSON(['success' => false, 'error' => 'Booking not found'], 404);
    }
    $checkStmt->close();
    
    // Soft delete the booking (mark as deleted)
    $deleteSql = "UPDATE bookings SET deleted_at = NOW(), status = 'cancelled', updated_at = NOW() WHERE id = ?";
    $deleteStmt = $conn->prepare($deleteSql);
    
    if (!$deleteStmt) {
        sendJSON(['success' => false, 'error' => 'Query prepare failed'], 500);
    }
    
    $deleteStmt->bind_param('i', $id);
    
    if (!$deleteStmt->execute()) {
        sendJSON(['success' => false, 'error' => 'Failed to delete booking'], 500);
    }
    
    $deleteStmt->close();
    $conn->close();
    
    sendJSON([
        'success' => true,
        'message' => 'Booking deleted successfully'
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

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
 * DELETE /api/customer-groups-delete.php
 * Soft delete a customer group
 * Body: { id }
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/group-helpers.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    $body = json_decode(file_get_contents('php://input'), true);
    
    $id = (int) ($body['id'] ?? $_GET['id'] ?? 0);
    if (!$id) {
        sendJSON(['success' => false, 'error' => 'Group ID is required'], 400);
    }
    
    $conn = getDB();
    
    if (!fetchGroupRow($conn, $id)) {
        sendJSON(['success' => false, 'error' => 'Group not found'], 404);
    }
    
    $stmt = $conn->prepare("UPDATE customer_groups SET deleted_at = NOW() WHERE id = ?");
    $stmt->bind_param('i', $id);
    
    if (!$stmt->execute()) {
        sendJSON(['success' => false, 'error' => 'Failed to delete group'], 500);
    }
    $stmt->close();
    $conn->close();
    
    sendJSON([
        'success' => true,
        'message' => 'Group deleted successfully'
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

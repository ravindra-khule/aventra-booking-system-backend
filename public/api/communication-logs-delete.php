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
 * DELETE /api/communication-logs-delete.php
 * Soft delete a communication log
 * Body: { id }
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/communication-helpers.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    $body = json_decode(file_get_contents('php://input'), true);
    
    $id = (int) ($body['id'] ?? $_GET['id'] ?? 0);
    if (!$id) {
        sendJSON(['success' => false, 'error' => 'Log ID is required'], 400);
    }
    
    $conn = getDB();
    
    if (!fetchCommunicationRow($conn, $id)) {
        sendJSON(['success' => false, 'error' => 'Communication log not found'], 404);
    }
    
    $stmt = $conn->prepare("UPDATE communication_logs SET deleted_at = NOW() WHERE id = ?");
    $stmt->bind_param('i', $id);
    
    if (!$stmt->execute()) {
        sendJSON(['success' => false, 'error' => 'Failed to delete communication log'], 500);
    }
    $stmt->close();
    $conn->close();
    
    sendJSON([
        'success' => true,
        'message' => 'Communication log deleted successfully'
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

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
 * GET /api/communication-logs-list.php
 * Get communication logs
 * Query params: customer_id (optional), limit (optional, default 500)
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/communication-helpers.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    $conn = getDB();
    
    $customerId = isset($_GET['customer_id']) ? (int) $_GET['customer_id'] : 0;
    $limit = isset($_GET['limit']) ? min((int) $_GET['limit'], 2000) : 500;
    
    if ($customerId > 0) {
        $stmt = $conn->prepare(
            "SELECT * FROM communication_logs 
             WHERE deleted_at IS NULL AND customer_id = ? 
             ORDER BY sent_at DESC, created_at DESC 
             LIMIT ?"
        );
        $stmt->bind_param('ii', $customerId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
    } else {
        $stmt = $conn->prepare(
            "SELECT * FROM communication_logs 
             WHERE deleted_at IS NULL 
             ORDER BY sent_at DESC, created_at DESC 
             LIMIT ?"
        );
        $stmt->bind_param('i', $limit);
        $stmt->execute();
        $result = $stmt->get_result();
    }
    
    $communications = [];
    while ($row = $result->fetch_assoc()) {
        $communications[] = formatCommunicationRow($row);
    }
    $stmt->close();
    $conn->close();
    
    sendJSON([
        'success' => true,
        'data' => $communications,
        'total' => count($communications)
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

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
 * GET /api/customer-groups-get.php?id={id}
 * Get a single customer group
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/group-helpers.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    $id = (int) ($_GET['id'] ?? 0);
    if (!$id) {
        sendJSON(['success' => false, 'error' => 'Group ID is required'], 400);
    }
    
    $conn = getDB();
    $row = fetchGroupRow($conn, $id);
    $conn->close();
    
    if (!$row) {
        sendJSON(['success' => false, 'error' => 'Group not found'], 404);
    }
    
    sendJSON([
        'success' => true,
        'data' => formatGroupRow($row)
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

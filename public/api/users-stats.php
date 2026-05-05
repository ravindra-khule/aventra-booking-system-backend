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
 * GET /api/users-stats.php
 * Get user statistics (total, active, inactive, suspended)
 */

require_once __DIR__ . '/../../config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    $conn = getDB();
    
    // Get total users
    $totalResult = $conn->query("SELECT COUNT(*) as count FROM users WHERE deleted_at IS NULL");
    $totalRow = $totalResult->fetch_assoc();
    $total = (int) $totalRow['count'];
    
    // Get active users
    $activeResult = $conn->query("SELECT COUNT(*) as count FROM users WHERE deleted_at IS NULL AND status = 'ACTIVE'");
    $activeRow = $activeResult->fetch_assoc();
    $active = (int) $activeRow['count'];
    
    // Get inactive users
    $inactiveResult = $conn->query("SELECT COUNT(*) as count FROM users WHERE deleted_at IS NULL AND status = 'INACTIVE'");
    $inactiveRow = $inactiveResult->fetch_assoc();
    $inactive = (int) $inactiveRow['count'];
    
    // Get suspended users
    $suspendedResult = $conn->query("SELECT COUNT(*) as count FROM users WHERE deleted_at IS NULL AND status = 'SUSPENDED'");
    $suspendedRow = $suspendedResult->fetch_assoc();
    $suspended = (int) $suspendedRow['count'];
    
    // Get pending users
    $pendingResult = $conn->query("SELECT COUNT(*) as count FROM users WHERE deleted_at IS NULL AND status = 'PENDING'");
    $pendingRow = $pendingResult->fetch_assoc();
    $pending = (int) $pendingRow['count'];
    
    $conn->close();
    
    sendJSON([
        'success' => true,
        'data' => [
            'total' => $total,
            'active' => $active,
            'inactive' => $inactive,
            'suspended' => $suspended,
            'pending' => $pending
        ]
    ]);
    
} catch (Exception $e) {
    sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
}
?>

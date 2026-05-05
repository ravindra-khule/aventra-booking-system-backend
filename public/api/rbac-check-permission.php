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
 * GET /api/rbac/check-permission
 * Check if a user has a specific permission
 */

require_once __DIR__ . '/../../config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    $userId = $_GET['user_id'] ?? null;
    $permission = $_GET['permission'] ?? null;
    
    if (!$userId || !$permission) {
        sendJSON(['success' => false, 'error' => 'User ID and permission required'], 400);
    }
    
    $conn = getDB();
    
    // Get user's role
    $userSql = "SELECT role_id FROM users WHERE id = ?";
    $userStmt = $conn->prepare($userSql);
    $userStmt->bind_param('i', $userId);
    $userStmt->execute();
    $userResult = $userStmt->get_result();
    
    if ($userResult->num_rows === 0) {
        sendJSON([
            'success' => true,
            'has_permission' => false,
            'reason' => 'User not found'
        ]);
    }
    
    $user = $userResult->fetch_assoc();
    $userStmt->close();
    
    if (!$user['role_id']) {
        sendJSON([
            'success' => true,
            'has_permission' => false,
            'reason' => 'User has no role assigned'
        ]);
    }
    
    // Check if role has permission
    $permSql = "SELECT COUNT(*) as has_permission FROM role_permissions rp
                INNER JOIN permissions p ON rp.permission_id = p.id
                WHERE rp.role_id = ? AND p.name = ?";
    
    $permStmt = $conn->prepare($permSql);
    $permStmt->bind_param('is', $user['role_id'], $permission);
    $permStmt->execute();
    $permResult = $permStmt->get_result()->fetch_assoc();
    $permStmt->close();
    
    $conn->close();
    
    $hasPermission = $permResult['has_permission'] > 0;
    
    sendJSON([
        'success' => true,
        'user_id' => (int) $userId,
        'permission' => $permission,
        'has_permission' => $hasPermission
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

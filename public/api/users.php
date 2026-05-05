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
 * GET /api/users.php
 * Get all users (admin only)
 */

require_once __DIR__ . '/../../config.php';

try {
    // Only allow GET requests
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    // TODO: In production, verify token and check if user is admin
    // For now, allowing all access. Add auth middleware later:
    // $token = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    // if (!$token || !verifyToken($token)) {
    //     sendJSON(['success' => false, 'error' => 'Unauthorized'], 401);
    // }
    
    $conn = getDB();
    
    // Get all users from database
    $sql = "SELECT 
                id,
                name,
                email,
                role,
                status,
                created_at,
                last_login,
                updated_at
            FROM users 
            ORDER BY id DESC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        sendJSON(['success' => false, 'error' => 'Query failed: ' . $conn->error], 500);
    }
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => (string) $row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'role' => $row['role'],
            'status' => $row['status'],
            'createdAt' => $row['created_at'],
            'lastLogin' => $row['last_login'],
            'updatedAt' => $row['updated_at']
        ];
    }
    
    $conn->close();
    
    sendJSON([
        'success' => true,
        'data' => $users,
        'total' => count($users)
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

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
 * GET /api/auth/me.php
 * Get current authenticated user and verify token
 * 
 * Headers required:
 * Authorization: Bearer <jwt_token>
 * 
 * Response on success:
 * {
 *   "success": true,
 *   "data": {
 *     "id": "1",
 *     "name": "John Doe",
 *     "email": "user@example.com",
 *     "role": "ADMIN",
 *     "status": "ACTIVE"
 *   }
 * }
 * 
 * Response on failure (401):
 * {
 *   "success": false,
 *   "error": "Invalid or expired token"
 * }
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/JWTHandler.php';
require_once __DIR__ . '/../../lib/AuthMiddleware.php';

try {
    // Only allow GET and POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'GET' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    // Verify token using middleware
    $user = AuthMiddleware::getCurrentUser();
    
    if (!$user) {
        sendJSON(['success' => false, 'error' => 'Invalid or expired token'], 401);
    }
    
    // Fetch fresh user data from database
    $conn = getDB();
    
    $userId = $user['user_id'];
    $sql = "SELECT id, name, email, role, status FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        sendJSON(['success' => false, 'error' => 'Query prepare failed'], 500);
    }
    
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendJSON(['success' => false, 'error' => 'User not found'], 404);
    }
    
    $userData = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    // Check if user is still active
    if (strtoupper($userData['status']) !== 'ACTIVE') {
        sendJSON(['success' => false, 'error' => 'User account is no longer active'], 403);
    }
    
    sendJSON([
        'success' => true,
        'data' => [
            'id' => (string) $userData['id'],
            'name' => $userData['name'],
            'email' => $userData['email'],
            'role' => strtoupper($userData['role']),
            'status' => strtoupper($userData['status'])
        ]
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

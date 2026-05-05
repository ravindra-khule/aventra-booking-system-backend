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
 * POST /api/logout.php
 * Logout user (invalidate JWT token)
 * 
 * Headers required:
 * Authorization: Bearer <jwt_token>
 * 
 * Response:
 * {
 *   "success": true,
 *   "message": "Logged out successfully"
 * }
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../lib/JWTHandler.php';
require_once __DIR__ . '/../lib/AuthMiddleware.php';

try {
    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    // Get token from Authorization header
    $token = JWTHandler::getTokenFromHeader();
    
    if (!$token) {
        sendJSON(['success' => false, 'error' => 'No authorization token provided'], 401);
    }
    
    // Verify token is valid
    $payload = JWTHandler::verifyToken($token);
    
    if (!$payload) {
        sendJSON(['success' => false, 'error' => 'Invalid or expired token'], 401);
    }
    
    // Add token to blacklist
    $expiresAt = $payload['exp'] ?? (time() + 24 * 3600);
    AuthMiddleware::blacklistToken($token, $expiresAt);
    
    // Optional: Log user activity
    try {
        $conn = getDB();
        
        // Update last activity (optional)
        $userId = $payload['user_id'];
        $updateSql = "UPDATE users SET last_login = NOW() WHERE id = ?";
        $updateStmt = $conn->prepare($updateSql);
        if ($updateStmt) {
            $updateStmt->bind_param('i', $userId);
            $updateStmt->execute();
            $updateStmt->close();
        }
        
        $conn->close();
    } catch (Exception $e) {
        // Continue with logout even if logging fails
        error_log("Error logging user activity: " . $e->getMessage());
    }
    
    sendJSON([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

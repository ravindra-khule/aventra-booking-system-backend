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
 * POST /api/users-2fa.php
 * Toggle 2FA (two-factor authentication) for a user
 */

require_once __DIR__ . '/../../config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    $body = json_decode(file_get_contents('php://input'), true);
    
    if (!$body) {
        sendJSON(['success' => false, 'error' => 'Invalid JSON body'], 400);
    }
    
    $userId = $body['id'] ?? null;
    $enabled = $body['enabled'] ?? null;
    
    if (!$userId || $enabled === null) {
        sendJSON(['success' => false, 'error' => 'User ID and enabled flag are required'], 400);
    }
    
    $conn = getDB();
    
    // Check if user exists
    $checkStmt = $conn->prepare("SELECT id, two_factor_enabled FROM users WHERE id = ? AND deleted_at IS NULL");
    $checkStmt->bind_param('i', $userId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        sendJSON(['success' => false, 'error' => 'User not found'], 404);
    }
    
    $row = $checkResult->fetch_assoc();
    $oldStatus = (bool) $row['two_factor_enabled'];
    $checkStmt->close();
    
    // Update 2FA status
    $twoFactorEnabled = $enabled ? 1 : 0;
    $updateStmt = $conn->prepare("UPDATE users SET two_factor_enabled = ?, updated_at = NOW() WHERE id = ?");
    $updateStmt->bind_param('ii', $twoFactorEnabled, $userId);
    
    if (!$updateStmt->execute()) {
        sendJSON(['success' => false, 'error' => 'Failed to update 2FA status'], 500);
    }
    $updateStmt->close();
    $conn->close();
    
    sendJSON([
        'success' => true,
        'message' => 'Two-factor authentication ' . ($enabled ? 'enabled' : 'disabled'),
        'data' => [
            'id' => (string) $userId,
            'twoFactorEnabled' => $enabled,
            'previousStatus' => $oldStatus
        ]
    ]);
    
} catch (Exception $e) {
    sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
}
?>

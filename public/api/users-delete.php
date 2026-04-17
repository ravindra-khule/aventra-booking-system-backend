<?php
/**
 * DELETE /api/users-delete.php
 * Delete a user (soft delete)
 */

require_once __DIR__ . '/../../config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: DELETE, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

try {
    if (!in_array($_SERVER['REQUEST_METHOD'], ['DELETE', 'POST'])) {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    $body = json_decode(file_get_contents('php://input'), true);
    
    if (!$body) {
        sendJSON(['success' => false, 'error' => 'Invalid JSON body'], 400);
    }
    
    $userId = $body['id'] ?? null;
    
    if (!$userId) {
        sendJSON(['success' => false, 'error' => 'User ID is required'], 400);
    }
    
    $conn = getDB();
    
    // Check if user exists
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND deleted_at IS NULL");
    $checkStmt->bind_param('i', $userId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        sendJSON(['success' => false, 'error' => 'User not found'], 404);
    }
    $checkStmt->close();
    
    // Soft delete (set deleted_at timestamp)
    $deleteStmt = $conn->prepare("UPDATE users SET deleted_at = NOW() WHERE id = ?");
    $deleteStmt->bind_param('i', $userId);
    
    if (!$deleteStmt->execute()) {
        sendJSON(['success' => false, 'error' => 'Failed to delete user'], 500);
    }
    $deleteStmt->close();
    $conn->close();
    
    sendJSON([
        'success' => true,
        'message' => 'User deleted successfully'
    ]);
    
} catch (Exception $e) {
    sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
}
?>

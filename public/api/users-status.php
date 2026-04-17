<?php
/**
 * POST /api/users-status.php
 * Update user status (ACTIVE, INACTIVE, SUSPENDED, PENDING)
 */

require_once __DIR__ . '/../../config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    $body = json_decode(file_get_contents('php://input'), true);
    
    if (!$body) {
        sendJSON(['success' => false, 'error' => 'Invalid JSON body'], 400);
    }
    
    $userId = $body['id'] ?? null;
    $status = $body['status'] ?? null;
    
    if (!$userId || !$status) {
        sendJSON(['success' => false, 'error' => 'User ID and status are required'], 400);
    }
    
    // Validate status
    $validStatuses = ['ACTIVE', 'INACTIVE', 'SUSPENDED', 'PENDING'];
    if (!in_array($status, $validStatuses)) {
        sendJSON(['success' => false, 'error' => 'Invalid status value'], 400);
    }
    
    $conn = getDB();
    
    // Check if user exists
    $checkStmt = $conn->prepare("SELECT id, status FROM users WHERE id = ? AND deleted_at IS NULL");
    $checkStmt->bind_param('i', $userId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        sendJSON(['success' => false, 'error' => 'User not found'], 404);
    }
    
    $row = $checkResult->fetch_assoc();
    $oldStatus = $row['status'];
    $checkStmt->close();
    
    // Update status
    $updateStmt = $conn->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
    $updateStmt->bind_param('si', $status, $userId);
    
    if (!$updateStmt->execute()) {
        sendJSON(['success' => false, 'error' => 'Failed to update user status'], 500);
    }
    $updateStmt->close();
    $conn->close();
    
    sendJSON([
        'success' => true,
        'message' => 'User status updated successfully',
        'data' => [
            'id' => (string) $userId,
            'oldStatus' => $oldStatus,
            'newStatus' => $status
        ]
    ]);
    
} catch (Exception $e) {
    sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
}
?>

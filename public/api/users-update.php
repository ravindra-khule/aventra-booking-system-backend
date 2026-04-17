<?php
/**
 * PUT/POST /api/users-update.php
 * Update user information
 */

require_once __DIR__ . '/../../config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, PUT, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

try {
    if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'POST'])) {
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
    
    // Build update query based on provided fields
    $updateFields = [];
    $params = [];
    $types = '';
    
    if (isset($body['name'])) {
        $updateFields[] = "name = ?";
        $params[] = $body['name'];
        $types .= 's';
    }
    
    if (isset($body['email'])) {
        // Check if email is already taken by another user
        $emailStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? AND deleted_at IS NULL");
        $emailStmt->bind_param('si', $body['email'], $userId);
        $emailStmt->execute();
        $emailResult = $emailStmt->get_result();
        
        if ($emailResult->num_rows > 0) {
            sendJSON(['success' => false, 'error' => 'Email already taken'], 400);
        }
        $emailStmt->close();
        
        $updateFields[] = "email = ?";
        $params[] = $body['email'];
        $types .= 's';
    }
    
    if (isset($body['phone'])) {
        $updateFields[] = "phone = ?";
        $params[] = $body['phone'];
        $types .= 's';
    }
    
    if (isset($body['role'])) {
        $updateFields[] = "role = ?";
        $params[] = $body['role'];
        $types .= 's';
    }
    
    if (isset($body['password'])) {
        $hashedPassword = password_hash($body['password'], PASSWORD_BCRYPT);
        $updateFields[] = "password = ?";
        $params[] = $hashedPassword;
        $types .= 's';
    }
    
    if (!empty($updateFields)) {
        $updateFields[] = "updated_at = NOW()";
        
        $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
        $params[] = $userId;
        $types .= 'i';
        
        $updateStmt = $conn->prepare($sql);
        
        if (!$updateStmt) {
            sendJSON(['success' => false, 'error' => 'Query prepare failed'], 500);
        }
        
        $updateStmt->bind_param($types, ...$params);
        
        if (!$updateStmt->execute()) {
            sendJSON(['success' => false, 'error' => 'Failed to update user'], 500);
        }
        $updateStmt->close();
    }
    
    $conn->close();
    
    sendJSON([
        'success' => true,
        'message' => 'User updated successfully'
    ]);
    
} catch (Exception $e) {
    sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
}
?>

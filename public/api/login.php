<?php
/**
 * POST /api/login.php
 * Authenticate user with email and password
 * Returns user data with token
 */

require_once __DIR__ . '/../../config.php';

try {
    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    // Get JSON body
    $body = json_decode(file_get_contents('php://input'), true);
    
    if (!$body) {
        sendJSON(['success' => false, 'error' => 'Invalid JSON body'], 400);
    }
    
    $email = $body['email'] ?? null;
    $password = $body['password'] ?? null;
    
    if (!$email || !$password) {
        sendJSON(['success' => false, 'error' => 'Email and password are required'], 400);
    }
    
    $conn = getDB();
    
    // Find user by email
    $sql = "SELECT id, name, email, password, role, status FROM users WHERE email = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        sendJSON(['success' => false, 'error' => 'Query prepare failed'], 500);
    }
    
    $stmt->bind_param('s', $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendJSON(['success' => false, 'error' => 'Email or password incorrect'], 401);
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    // Verify password
    if (!password_verify($password, $user['password'])) {
        sendJSON(['success' => false, 'error' => 'Email or password incorrect'], 401);
    }
    
    // Check if user is active
    if ($user['status'] !== 'active') {
        sendJSON(['success' => false, 'error' => 'User account is ' . $user['status']], 403);
    }
    
    // Generate simple token (in production, use JWT)
    $token = bin2hex(random_bytes(32));
    
    // Store token (for simplicity, in production use Redis or JWT)
    // For now, we'll return the token and user stores it in localStorage
    
    // Update last login
    $updateSql = "UPDATE users SET last_login = NOW() WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param('i', $user['id']);
    $updateStmt->execute();
    $updateStmt->close();
    
    $conn->close();
    
    sendJSON([
        'success' => true,
        'data' => [
            'id' => (string) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'token' => $token
        ]
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

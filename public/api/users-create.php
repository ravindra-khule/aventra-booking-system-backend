<?php
/**
 * POST /api/users-create.php
 * Create a new user
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
    
    $name = $body['name'] ?? null;
    $email = $body['email'] ?? null;
    $password = $body['password'] ?? null;
    $role = $body['role'] ?? 'USER';
    $status = $body['status'] ?? 'ACTIVE';
    $phone = $body['phone'] ?? null;
    
    // Validation
    if (!$name || !$email || !$password) {
        sendJSON(['success' => false, 'error' => 'Name, email, and password are required'], 400);
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJSON(['success' => false, 'error' => 'Invalid email address'], 400);
    }
    
    $conn = getDB();
    
    // Check if email already exists
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
    $checkStmt->bind_param('s', $email);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        sendJSON(['success' => false, 'error' => 'Email already exists'], 400);
    }
    $checkStmt->close();
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    
    // Insert user
    $insertStmt = $conn->prepare(
        "INSERT INTO users (name, email, password, role, status, phone, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())"
    );
    
    $insertStmt->bind_param('ssssss', $name, $email, $hashedPassword, $role, $status, $phone);
    
    if (!$insertStmt->execute()) {
        sendJSON(['success' => false, 'error' => 'Failed to create user: ' . $insertStmt->error], 500);
    }
    
    $userId = $insertStmt->insert_id;
    $insertStmt->close();
    $conn->close();
    
    sendJSON([
        'success' => true,
        'message' => 'User created successfully',
        'data' => [
            'id' => (string) $userId,
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'status' => $status
        ]
    ]);
    
} catch (Exception $e) {
    sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
}
?>

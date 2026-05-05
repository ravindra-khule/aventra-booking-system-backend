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
 * POST /api/register.php
 * Create a new user account (admin only in real scenario)
 * Or public Registration
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
    
    $name = $body['name'] ?? null;
    $email = $body['email'] ?? null;
    $password = $body['password'] ?? null;
    $role = $body['role'] ?? 'user'; // Default role is 'user'
    
    // Validate
    if (!$name || !$email || !$password) {
        sendJSON(['success' => false, 'error' => 'Name, email, and password are required'], 400);
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJSON(['success' => false, 'error' => 'Invalid email format'], 400);
    }
    
    // Validate password strength (min 6 chars)
    if (strlen($password) < 6) {
        sendJSON(['success' => false, 'error' => 'Password must be at least 6 characters'], 400);
    }
    
    // Validate role
    $allowed_roles = ['admin', 'staff', 'user'];
    if (!in_array($role, $allowed_roles)) {
        $role = 'user';
    }
    
    $conn = getDB();
    
    // Check if email already exists
    $checkSql = "SELECT id FROM users WHERE email = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param('s', $email);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        sendJSON(['success' => false, 'error' => 'Email already registered'], 409);
    }
    $checkStmt->close();
    
    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    
    // Create new user
    $sql = "INSERT INTO users (name, email, password, role, status) VALUES (?, ?, ?, ?, 'active')";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        sendJSON(['success' => false, 'error' => 'Query prepare failed'], 500);
    }
    
    $stmt->bind_param('ssss', $name, $email, $hashedPassword, $role);
    
    if (!$stmt->execute()) {
        sendJSON(['success' => false, 'error' => 'Failed to create user: ' . $stmt->error], 500);
    }
    
    $userId = $stmt->insert_id;
    $stmt->close();
    
    // Generate token
    $token = bin2hex(random_bytes(32));
    
    $conn->close();
    
    sendJSON([
        'success' => true,
        'message' => 'User registered successfully',
        'data' => [
            'id' => (string) $userId,
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'token' => $token
        ]
    ], 201);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

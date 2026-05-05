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
 * POST /api/login.php
 * Authenticate user with email and password
 * Returns user data with JWT token
 * 
 * Request body:
 * {
 *   "email": "user@example.com",
 *   "password": "password123"
 * }
 * 
 * Response on success:
 * {
 *   "success": true,
 *   "data": {
 *     "id": "1",
 *     "name": "John Doe",
 *     "email": "user@example.com",
 *     "role": "ADMIN",
 *     "status": "ACTIVE",
 *     "token": "eyJhbGc...",
 *     "expiresIn": 86400
 *   }
 * }
 */

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../lib/JWTHandler.php';

try {
    debugLog('=== LOGIN REQUEST STARTED ===');
    debugLog('Request Method: ' . $_SERVER['REQUEST_METHOD']);
    debugLog('Request IP: ' . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR']));
    
    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        debugLog('ERROR: Invalid request method - ' . $_SERVER['REQUEST_METHOD']);
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    // Get JSON body
    $rawInput = file_get_contents('php://input');
    debugLog('Raw request input received, length: ' . strlen($rawInput) . ' bytes');
    
    $body = json_decode($rawInput, true);
    
    if (!$body && !empty($rawInput)) {
        $jsonError = json_last_error_msg();
        debugLog('ERROR: Invalid JSON body - ' . $jsonError);
        sendJSON(['success' => false, 'error' => 'Invalid JSON body: ' . $jsonError], 400);
    }
    
    debugLog('Request Body Parsed', [
        'email' => $body['email'] ?? 'N/A',
        'has_password' => isset($body['password']) ? 'YES' : 'NO'
    ]);
    
    $email = $body['email'] ?? null;
    $password = $body['password'] ?? null;
    
    if (!$email || !$password) {
        debugLog('ERROR: Missing required fields', [
            'email_provided' => $email ? 'YES' : 'NO',
            'password_provided' => $password ? 'YES' : 'NO'
        ]);
        sendJSON(['success' => false, 'error' => 'Email and password are required'], 400);
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        debugLog('ERROR: Invalid email format - ' . $email);
        sendJSON(['success' => false, 'error' => 'Invalid email format'], 400);
    }
    
    debugLog('Attempting to connect to database...');
    $conn = getDB();
    debugLog('✓ Database connected successfully');
    
    // Find user by email
    debugLog('Searching for user with email: ' . $email);
    $sql = "SELECT id, name, email, password, role, status FROM users WHERE email = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        $error = $conn->error;
        debugLog('ERROR: Query prepare failed - ' . $error);
        sendJSON(['success' => false, 'error' => 'Query prepare failed: ' . $error], 500);
    }
    
    $stmt->bind_param('s', $email);
    
    if (!$stmt->execute()) {
        $error = $stmt->error;
        debugLog('ERROR: Query execute failed - ' . $error);
        $stmt->close();
        sendJSON(['success' => false, 'error' => 'Query execute failed: ' . $error], 500);
    }
    
    $result = $stmt->get_result();
    debugLog('Database query executed successfully. Rows found: ' . $result->num_rows);
    
    if ($result->num_rows === 0) {
        debugLog('ERROR: User not found - Email: ' . $email);
        $stmt->close();
        $conn->close();
        sendJSON(['success' => false, 'error' => 'Email or password incorrect'], 401);
    }
    
    $user = $result->fetch_assoc();
    $stmt->close();
    
    debugLog('✓ User found in database', [
        'id' => $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => $user['role'],
        'status' => $user['status']
    ]);
    
    // Verify password
    debugLog('Verifying password for user: ' . $email);
    $passwordMatch = password_verify($password, $user['password']);
    
    if (!$passwordMatch) {
        debugLog('ERROR: Password verification failed for user: ' . $email);
        $conn->close();
        sendJSON(['success' => false, 'error' => 'Email or password incorrect'], 401);
    }
    
    debugLog('✓ Password verified successfully');
    
    // Check if user is active
    $status = strtoupper($user['status']);
    debugLog('Checking user status: ' . $status);
    
    if ($status !== 'ACTIVE') {
        $message = match($status) {
            'INACTIVE' => 'User account is inactive. Please contact administrator.',
            'SUSPENDED' => 'User account is suspended. Please contact administrator.',
            'PENDING' => 'User account is pending activation.',
            default => 'User account is ' . $status
        };
        debugLog('ERROR: User account is not active - Status: ' . $status);
        $conn->close();
        sendJSON(['success' => false, 'error' => $message], 403);
    }
    
    debugLog('✓ User account is ACTIVE');
    
    // Generate JWT token (24 hours expiration)
    $tokenExpiration = 24 * 3600; // 24 hours in seconds
    
    $payload = [
        'user_id' => (int) $user['id'],
        'id' => (string) $user['id'],
        'name' => $user['name'],
        'email' => $user['email'],
        'role' => strtoupper($user['role']),
        'status' => strtoupper($user['status']),
        'type' => 'access_token'
    ];
    
    debugLog('Generating JWT token with payload:', [
        'user_id' => $payload['user_id'],
        'email' => $payload['email'],
        'role' => $payload['role']
    ]);
    
    try {
        $token = JWTHandler::generateToken($payload, $tokenExpiration);
        debugLog('✓ JWT Token generated successfully');
    } catch (Exception $tokenError) {
        debugLog('ERROR: JWT token generation failed - ' . $tokenError->getMessage());
        $conn->close();
        sendJSON(['success' => false, 'error' => 'Token generation failed: ' . $tokenError->getMessage()], 500);
    }
    
    // Update last login timestamp
    debugLog('Updating last login timestamp for user ID: ' . $user['id']);
    $updateSql = "UPDATE users SET last_login = NOW() WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    
    if ($updateStmt) {
        $updateStmt->bind_param('i', $user['id']);
        if ($updateStmt->execute()) {
            debugLog('✓ Last login timestamp updated');
        } else {
            debugLog('⚠️  Warning: Could not update last login timestamp - ' . $updateStmt->error);
        }
        $updateStmt->close();
    } else {
        debugLog('⚠️  Warning: Could not prepare update statement - ' . $conn->error);
    }
    
    $conn->close();
    
    debugLog('=== LOGIN SUCCESSFUL ===', [
        'user_id' => $user['id'],
        'email' => $user['email'],
        'role' => strtoupper($user['role']),
        'status' => strtoupper($user['status'])
    ]);
    
    sendJSON([
        'success' => true,
        'message' => 'Login successful',
        'data' => [
            'id' => (string) $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => strtoupper($user['role']),
            'status' => strtoupper($user['status']),
            'token' => $token,
            'expiresIn' => $tokenExpiration
        ]
    ]);
    
} catch (Exception $e) {
    debugLog('EXCEPTION CAUGHT', [
        'message' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
    
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

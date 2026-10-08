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
 * POST /api/users-invite.php
 * Invite a new user with email (creates pending user)
 */

require_once __DIR__ . '/../../config.php';
$__mailPath = __DIR__ . '/../../lib/MailService.php';
if (!is_file($__mailPath)) {
    sendJSON(['success' => false, 'error' => 'MailService library is not installed on the server'], 500);
}
require_once $__mailPath;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    $body = json_decode(file_get_contents('php://input'), true);
    
    if (!$body) {
        sendJSON(['success' => false, 'error' => 'Invalid JSON body'], 400);
    }
    
    $email = $body['email'] ?? null;
    $role = $body['role'] ?? 'USER';
    
    // Validation
    if (!$email) {
        sendJSON(['success' => false, 'error' => 'Email is required'], 400);
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
        sendJSON(['success' => false, 'error' => 'Email already registered'], 400);
    }
    $checkStmt->close();
    
    // Generate invitation token (random 32 character string)
    $invitationToken = bin2hex(random_bytes(16));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+7 days'));
    
    // Create pending user with PENDING status
    $insertStmt = $conn->prepare(
        "INSERT INTO users (email, role, status, invitation_token, invitation_expires_at, created_at, updated_at)
         VALUES (?, ?, 'PENDING', ?, ?, NOW(), NOW())"
    );
    
    $insertStmt->bind_param('sss', $email, $role, $expiresAt);
    
    if (!$insertStmt->execute()) {
        sendJSON(['success' => false, 'error' => 'Failed to create invitation: ' . $insertStmt->error], 500);
    }
    
    $userId = $insertStmt->insert_id;
    $insertStmt->close();
    
    // Send invitation email via SMTP (non-fatal - invitation exists either way)
    $frontendUrl = getenv('FRONTEND_URL') ?: 'https://booking.prismadot.com';
    $inviteLink = rtrim($frontendUrl, '/') . '/#/accept-invitation?token=' . $invitationToken;

    $inviteHtml = '<html><body style="font-family:Arial,sans-serif;line-height:1.6;">'
        . '<h2>You\'ve been invited!</h2>'
        . '<p>You have been invited to join the Aventra Booking System as <strong>' . htmlspecialchars($role) . '</strong>.</p>'
        . '<p><a href="' . htmlspecialchars($inviteLink) . '" '
        . 'style="display:inline-block;padding:12px 24px;background:#2563eb;color:#fff;text-decoration:none;border-radius:6px;">'
        . 'Accept Invitation</a></p>'
        . '<p>Or copy this link: ' . htmlspecialchars($inviteLink) . '</p>'
        . '<p>This invitation expires on ' . $expiresAt . '.</p>'
        . '</body></html>';

    $emailResult = sendSmtpEmail(
        $conn,
        $email,
        'You\'ve been invited to Aventra Booking System',
        $inviteHtml,
        "You've been invited to join the Aventra Booking System. Accept your invitation: $inviteLink (expires $expiresAt)"
    );

    $conn->close();

    sendJSON([
        'success' => true,
        'message' => $emailResult['success']
            ? 'Invitation sent successfully'
            : 'Invitation created, but the email could not be sent: ' . ($emailResult['error'] ?? 'SMTP not configured'),
        'data' => [
            'id' => (string) $userId,
            'email' => $email,
            'role' => $role,
            'status' => 'PENDING',
            'invitationToken' => $invitationToken,
            'expiresAt' => $expiresAt,
            'emailSent' => $emailResult['success']
        ]
    ]);
    
} catch (Throwable $e) {
    sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
}
?>

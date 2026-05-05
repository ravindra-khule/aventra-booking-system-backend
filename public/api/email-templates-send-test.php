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
 * POST /api/email-templates-send-test.php
 * Send a test email from a template
 */

require_once __DIR__ . '/../../config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }

    $body = json_decode(file_get_contents('php://input'), true);

    if (!$body || !isset($body['templateId']) || !isset($body['language']) || !isset($body['testEmail'])) {
        sendJSON(['success' => false, 'error' => 'templateId, language, and testEmail are required'], 400);
    }

    $conn = getDB();
    $templateId = $body['templateId'];
    $language = $body['language'];
    $testEmail = $body['testEmail'];

    // Get template content
    $contentSql = "SELECT subject, html_content FROM email_template_content WHERE template_id = ? AND language = ?";
    $contentStmt = $conn->prepare($contentSql);
    $contentStmt->bind_param("ss", $templateId, $language);
    $contentStmt->execute();
    $contentResult = $contentStmt->get_result();

    if ($contentResult->num_rows === 0) {
        sendJSON(['success' => false, 'error' => 'Template content not found for selected language'], 404);
    }

    $contentRow = $contentResult->fetch_assoc();
    $subject = $contentRow['subject'];
    $htmlContent = $contentRow['html_content'];

    // Replace placeholders with sample data if provided
    if (isset($body['placeholders']) && is_array($body['placeholders'])) {
        foreach ($body['placeholders'] as $placeholder => $value) {
            $htmlContent = str_replace("{{$placeholder}}", $value, $htmlContent);
            $subject = str_replace("{{$placeholder}}", $value, $subject);
        }
    }

    // Send test email
    $headers = [
        'Content-Type: text/html; charset=UTF-8',
        'From: test@aventrabooking.com'
    ];

    $result = mail($testEmail, $subject, $htmlContent, implode("\r\n", $headers));

    if ($result) {
        // Log test email sent
        $logSql = "INSERT INTO email_templates_audit_log (template_id, action, details, created_by, created_date)
                   VALUES (?, 'TEST_EMAIL_SENT', ?, ?, ?)";

        $logStmt = $conn->prepare($logSql);
        $now = date('Y-m-d H:i:s');
        $details = json_encode(['email' => $testEmail, 'language' => $language]);
        $createdBy = $body['createdBy'] ?? 'system';

        $logStmt->bind_param("ssss", $templateId, $details, $createdBy, $now);
        $logStmt->execute();

        sendJSON([
            'success' => true,
            'data' => [
                'message' => 'Test email sent successfully',
                'email' => $testEmail
            ]
        ]);
    } else {
        sendJSON([
            'success' => false,
            'error' => 'Failed to send test email'
        ], 500);
    }

} catch (Exception $e) {
    debugLog('Email Template Send Test Error', ['error' => $e->getMessage()]);
    sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
}
?>

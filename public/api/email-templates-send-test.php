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

    if (!$body || !isset($body['templateId']) || !isset($body['language']) || !isset($body['testEmail'])) {
        sendJSON(['success' => false, 'error' => 'templateId, language, and testEmail are required'], 400);
    }

    $conn = getDB();
    $templateId = $body['templateId'];
    $language = $body['language'];
    $testEmail = $body['testEmail'];

    // Get template content
    $contentSql = "SELECT subject, html_content, text_content FROM email_template_content WHERE template_id = ? AND language = ?";
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
    $textContent = $contentRow['text_content'] ?? null;

    // Replace placeholders with sample data if provided
    if (isset($body['placeholders']) && is_array($body['placeholders'])) {
        foreach ($body['placeholders'] as $placeholder => $value) {
            $htmlContent = str_replace('{{' . $placeholder . '}}', (string)$value, $htmlContent);
            $subject = str_replace('{{' . $placeholder . '}}', (string)$value, $subject);
        }
    }

    // Send test email via configured SMTP (Hostinger)
    $sendResult = sendSmtpEmail($conn, $testEmail, $subject, $htmlContent, $textContent);
    $result = $sendResult['success'];

    $result = @mail($testEmail, $subject, $htmlContent, implode("\r\n", $headers));

    $now = date('Y-m-d H:i:s');
    $createdBy = $body['createdBy'] ?? 'system';

    if ($result) {
        // Log test email sent
        $logSql = "INSERT INTO email_templates_audit_log (template_id, action, details, created_by, created_date)
                   VALUES (?, 'TEST_EMAIL_SENT', ?, ?, ?)";

        $logStmt = $conn->prepare($logSql);
        $details = json_encode(['email' => $testEmail, 'language' => $language]);

        $logStmt->bind_param("ssss", $templateId, $details, $createdBy, $now);
        $logStmt->execute();

        sendJSON([
            'success' => true,
            'data' => [
                'message' => 'Test email sent successfully',
                'email' => $testEmail,
                'simulated' => false
            ]
        ]);
    }

    // Mail transport unavailable (e.g. local Docker without MTA) - save the
    // rendered email to disk so it can still be inspected, and log it.
    $emailsDir = __DIR__ . '/../../storage/emails';
    if (!is_dir($emailsDir)) {
        @mkdir($emailsDir, 0755, true);
    }

    $fileName = 'test-' . preg_replace('/[^a-zA-Z0-9_-]/', '-', $templateId) . '-' . date('Ymd-His') . '.html';
    @file_put_contents(
        $emailsDir . '/' . $fileName,
        "<!-- To: $testEmail -->\n<!-- Subject: $subject -->\n" . $htmlContent
    );

    $logSql = "INSERT INTO email_templates_audit_log (template_id, action, details, created_by, created_date)
               VALUES (?, 'TEST_EMAIL_RENDERED', ?, ?, ?)";

    $logStmt = $conn->prepare($logSql);
    $details = json_encode(['email' => $testEmail, 'language' => $language, 'simulated' => true, 'file' => $fileName]);

    $logStmt->bind_param("ssss", $templateId, $details, $createdBy, $now);
    $logStmt->execute();

    sendJSON([
        'success' => true,
        'data' => [
            'message' => "Mail transport unavailable in this environment - rendered email saved to storage/emails/$fileName",
            'email' => $testEmail,
            'simulated' => true,
            'savedTo' => "storage/emails/$fileName"
        ]
    ]);

} catch (Exception $e) {
    debugLog('Email Template Send Test Error', ['error' => $e->getMessage()]);
    sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
}
?>

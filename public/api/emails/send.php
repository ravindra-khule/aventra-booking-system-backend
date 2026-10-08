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
 * POST /api/emails/send   (rewritten to /api/emails/send.php by .htaccess)
 *
 * Sends an email via Hostinger SMTP.
 * Body: { to, subject, templateName, data, language? }
 *
 * If templateName matches an email template, its subject/html/text content
 * is rendered with {{placeholder}} values from `data`. Otherwise the given
 * subject is used and a simple HTML body is generated from `data`.
 */

require_once __DIR__ . '/../../../config.php';
$__mailPath = __DIR__ . '/../../../lib/MailService.php';
if (!is_file($__mailPath)) {
    sendJSON(['success' => false, 'error' => 'MailService library is not installed on the server'], 500);
}
require_once $__mailPath;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }

    $body = json_decode(file_get_contents('php://input'), true);

    if (!$body || empty($body['to'])) {
        sendJSON(['success' => false, 'error' => 'Field "to" is required'], 400);
    }

    $to = trim($body['to']);
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        sendJSON(['success' => false, 'error' => 'Invalid recipient email'], 400);
    }

    $subject = $body['subject'] ?? '';
    $templateName = $body['templateName'] ?? '';
    $data = $body['data'] ?? [];
    $language = $body['language'] ?? 'en';

    $conn = getDB();

    // Resolve template: "booking-confirmation" -> "template-booking-confirmation",
    // fall back to exact id or name match.
    $content = null;
    if ($templateName !== '') {
        $candidates = array_values(array_unique([
            'template-' . $templateName,
            $templateName,
        ]));

        $stmt = $conn->prepare(
            "SELECT c.subject, c.html_content, c.text_content, c.language
             FROM email_template_content c
             JOIN email_templates t ON t.id = c.template_id
             WHERE c.template_id = ?
             ORDER BY (c.language = ?) DESC, c.language ASC
             LIMIT 1"
        );

        foreach ($candidates as $candidate) {
            $stmt->bind_param('ss', $candidate, $language);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows > 0) {
                $content = $res->fetch_assoc();
                $templateId = $candidate;
                break;
            }
        }

        // Try matching by template name as a last resort
        if (!$content) {
            $stmt2 = $conn->prepare(
                "SELECT c.subject, c.html_content, c.text_content, c.language, t.id AS tid
                 FROM email_template_content c
                 JOIN email_templates t ON t.id = c.template_id
                 WHERE LOWER(REPLACE(t.name, ' ', '-')) = LOWER(?)
                 ORDER BY (c.language = ?) DESC, c.language ASC
                 LIMIT 1"
            );
            $stmt2->bind_param('ss', $templateName, $language);
            $stmt2->execute();
            $res2 = $stmt2->get_result();
            if ($res2->num_rows > 0) {
                $content = $res2->fetch_assoc();
                $templateId = $content['tid'];
            }
            $stmt2->close();
        }
    }

    if ($content) {
        $subject = renderEmailTemplate($content['subject'], $data);
        $html = renderEmailTemplate($content['html_content'], $data);
        $text = isset($content['text_content']) ? renderEmailTemplate($content['text_content'], $data) : null;
        if ($subject === '') {
            $subject = $body['subject'] ?? 'Notification';
        }
    } else {
        // No template - generate a simple HTML body from the data payload
        $rows = '';
        foreach ((array) $data as $k => $v) {
            if (is_scalar($v)) {
                $label = htmlspecialchars(ucwords(preg_replace('/([a-z])([A-Z])/', '$1 $2', (string) $k)));
                $rows .= '<tr><td style="padding:6px 12px;border:1px solid #ddd;font-weight:bold;">'
                    . $label . '</td><td style="padding:6px 12px;border:1px solid #ddd;">'
                    . htmlspecialchars((string) $v) . '</td></tr>';
            }
        }
        $html = '<html><body style="font-family:Arial,sans-serif;">'
            . '<h2>' . htmlspecialchars($subject ?: 'Notification') . '</h2>'
            . ($rows ? '<table style="border-collapse:collapse;">' . $rows . '</table>' : '')
            . '</body></html>';
        $text = null;
    }

    $result = sendSmtpEmail($conn, $to, $subject, $html, $text);

    if (!$result['success']) {
        $code = !empty($result['configured']) ? 502 : 500;
        sendJSON([
            'success' => false,
            'error' => $result['error'] ?? 'Failed to send email',
        ], $code);
    }

    // Track usage / audit (non-fatal)
    if (!empty($templateId)) {
        @$conn->query("UPDATE email_templates SET usage_count = usage_count + 1 WHERE id = '" . $conn->real_escape_string($templateId) . "'");

        $logStmt = $conn->prepare(
            "INSERT INTO email_templates_audit_log (template_id, action, details, created_by, created_date)
             VALUES (?, 'EMAIL_SENT', ?, 'api', ?)"
        );
        if ($logStmt) {
            $details = json_encode(['email' => $to, 'templateName' => $templateName]);
            $now = date('Y-m-d H:i:s');
            $logStmt->bind_param('sss', $templateId, $details, $now);
            @$logStmt->execute();
            $logStmt->close();
        }
    }

    $messageId = 'smtp-' . date('YmdHis') . '-' . substr(md5($to . microtime()), 0, 8);

    sendJSON([
        'success' => true,
        'messageId' => $messageId,
        'data' => [
            'message' => 'Email sent successfully',
            'messageId' => $messageId,
            'email' => $to,
        ],
    ]);

} catch (Throwable $e) {
    debugLog('Email Send Error', ['error' => $e->getMessage()]);
    sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
}
?>

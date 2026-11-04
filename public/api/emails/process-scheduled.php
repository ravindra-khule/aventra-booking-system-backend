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
 * GET/POST /api/emails/process-scheduled
 *
 * Sends all due rows from the scheduled_emails queue via SMTP.
 * Set up a Hostinger cron job hitting this URL every hour, e.g.:
 *   https://booking.prismadot.com/api/emails/process-scheduled.php
 * Optional: append ?key=YOUR_CRON_KEY and define CRON_KEY env var to protect it.
 */

require_once __DIR__ . '/../../../config.php';
$__mailPath = __DIR__ . '/../../../lib/MailService.php';
if (!is_file($__mailPath)) {
    sendJSON(['success' => false, 'error' => 'MailService library is not installed on the server'], 500);
}
require_once $__mailPath;

try {
    $cronKey = getenv('CRON_KEY');
    if ($cronKey && ($_GET['key'] ?? '') !== $cronKey) {
        sendJSON(['success' => false, 'error' => 'Unauthorized'], 401);
    }

    $conn = getDB();

    $exists = $conn->query("SHOW TABLES LIKE 'scheduled_emails'");
    if (!$exists || $exists->num_rows === 0) {
        sendJSON(['success' => true, 'data' => ['processed' => 0, 'message' => 'No scheduled_emails table']]);
    }

    $result = $conn->query(
        "SELECT * FROM scheduled_emails
         WHERE status = 'PENDING' AND scheduled_for <= NOW()
         ORDER BY scheduled_for ASC
         LIMIT 50"
    );

    $processed = 0;
    $failed = 0;
    $errors = [];

    while ($row = $result->fetch_assoc()) {
        $id = (int) $row['id'];
        $data = $row['payload'] ? json_decode($row['payload'], true) : [];
        $to = $row['to_email'] ?: ($data['guestEmail'] ?? ($data['to'] ?? null));
        $subject = $row['subject'] ?: 'Booking Reminder';

        if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $conn->query("UPDATE scheduled_emails SET status = 'FAILED', error = 'missing recipient' WHERE id = $id");
            $failed++;
            continue;
        }

        // Render template content if available
        $html = null;
        $text = null;
        if (!empty($row['template_name'])) {
            $tstmt = $conn->prepare(
                "SELECT c.subject, c.html_content, c.text_content
                 FROM email_template_content c
                 WHERE c.template_id = ?
                 ORDER BY (c.language = 'en') DESC LIMIT 1"
            );
            $templateId = 'template-' . $row['template_name'];
            $tstmt->bind_param('s', $templateId);
            $tstmt->execute();
            $tres = $tstmt->get_result();
            if ($tres->num_rows > 0) {
                $content = $tres->fetch_assoc();
                $subject = $row['subject'] ?: renderEmailTemplate($content['subject'], $data);
                $html = renderEmailTemplate($content['html_content'], $data);
                $text = $content['text_content'] ? renderEmailTemplate($content['text_content'], $data) : null;
            }
            $tstmt->close();
        }

        if ($html === null) {
            $html = '<html><body><p>' . nl2br(htmlspecialchars(json_encode($data))) . '</p></body></html>';
        }

        $send = sendSmtpEmail($conn, $to, $subject, $html, $text);

        if ($send['success']) {
            $conn->query("UPDATE scheduled_emails SET status = 'SENT', sent_at = NOW() WHERE id = $id");
            $processed++;
        } else {
            $err = $conn->real_escape_string($send['error'] ?? 'unknown');
            $conn->query("UPDATE scheduled_emails SET status = 'FAILED', error = '$err' WHERE id = $id");
            $failed++;
            $errors[] = ['id' => $id, 'to' => $to, 'error' => $send['error'] ?? 'unknown'];
        }
    }

    sendJSON([
        'success' => true,
        'data' => [
            'sent' => $processed,
            'failed' => $failed,
            'errors' => $errors,
        ],
    ]);

} catch (Throwable $e) {
    debugLog('Process Scheduled Emails Error', ['error' => $e->getMessage()]);
    sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
}
?>

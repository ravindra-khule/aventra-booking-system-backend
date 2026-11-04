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
 * POST /api/emails/schedule-reminder
 *
 * Stores a reminder email to be sent later by the cron script
 * (public/api/emails/process-scheduled.php).
 * Body: { bookingId, scheduledFor, reminderType, to?, subject?, templateName?, data? }
 */

require_once __DIR__ . '/../../../config.php';
$__notifierPath = __DIR__ . '/../../../lib/BookingNotifier.php';
if (!is_file($__notifierPath)) {
    sendJSON(['success' => false, 'error' => 'BookingNotifier library is not installed on the server'], 500);
}
require_once $__notifierPath;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }

    $body = json_decode(file_get_contents('php://input'), true);

    if (!$body || empty($body['bookingId'])) {
        sendJSON(['success' => false, 'error' => 'bookingId is required'], 400);
    }

    $conn = getDB();
    ensureScheduledEmailsTable($conn);

    $scheduledFor = $body['scheduledFor'] ?? null;
    if (is_numeric($scheduledFor)) {
        $scheduledFor = date('Y-m-d H:i:s', $scheduledFor / 1000); // JS epoch ms
    } elseif ($scheduledFor) {
        $scheduledFor = date('Y-m-d H:i:s', strtotime($scheduledFor));
    } else {
        $scheduledFor = date('Y-m-d H:i:s');
    }

    $bookingId = (string) $body['bookingId'];
    $reminderType = $body['reminderType'] ?? 'generic';

    // Template by reminder type: payment reminders vs booking reminders
    $templateName = $body['templateName']
        ?? (stripos($reminderType, 'payment') !== false ? 'payment-reminder' : 'reminder-24h');

    // Resolve recipients + payload from the booking when not supplied
    $settings = getNotificationSettings($conn);
    $booking = getBookingForNotify($conn, (int) $bookingId);

    $data = isset($body['data']) && is_array($body['data']) ? $body['data'] : [];
    if ($booking) {
        $data = buildBookingEmailData($booking, $settings, $data);
    }

    $emails = [];
    if (!empty($body['to'])) {
        $emails[] = $body['to'];
    } else {
        if (notifySettingEnabled($settings['notify_customer_enabled']) && !empty($booking['customer_email'])) {
            $emails[] = $booking['customer_email'];
        }
        if (notifySettingEnabled($settings['notify_client_enabled']) && !empty($settings['client_notification_email'])) {
            $emails[] = trim($settings['client_notification_email']);
        }
        $emails = array_values(array_unique($emails));
    }

    if (empty($emails)) {
        sendJSON(['success' => false, 'error' => 'No recipient could be resolved for this reminder'], 400);
    }

    $subject = $body['subject'] ?? ('Reminder: ' . ($data['tourName'] ?? 'Booking'));
    $payload = json_encode($data);

    $stmt = $conn->prepare(
        "INSERT INTO scheduled_emails (booking_id, to_email, subject, template_name, payload, reminder_type, scheduled_for)
         VALUES (?, ?, ?, ?, ?, ?, ?)"
    );
    if (!$stmt) {
        sendJSON(['success' => false, 'error' => 'scheduled_emails table is not available'], 500);
    }

    $inserted = 0;
    foreach ($emails as $email) {
        $stmt->bind_param('sssssss', $bookingId, $email, $subject, $templateName, $payload, $reminderType, $scheduledFor);
        if ($stmt->execute()) {
            $inserted++;
        }
    }
    $stmt->close();

    if ($inserted === 0) {
        sendJSON(['success' => false, 'error' => 'Failed to schedule email'], 500);
    }

    sendJSON([
        'success' => true,
        'data' => [
            'bookingId' => $bookingId,
            'scheduledFor' => $scheduledFor,
            'recipients' => count($emails),
            'message' => 'Reminder scheduled. Run api/emails/process-scheduled.php via cron to send due emails.',
        ],
    ]);

} catch (Throwable $e) {
    debugLog('Schedule Reminder Error', ['error' => $e->getMessage()]);
    sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
}
?>

<?php
/**
 * BookingNotifier - Booking lifecycle email notifications
 *
 * Covers the 12 notification scenarios:
 *   booking_created / booking_pending   - new booking received (awaiting approval)
 *   booking_approved                    - booking confirmed
 *   booking_rejected                    - booking rejected
 *   booking_cancelled_user              - customer cancelled
 *   booking_cancelled_client            - business/admin cancelled
 *   booking_rescheduled                 - departure date changed
 *   booking_reminder                    - scheduled reminder before departure
 *   booking_completed                   - tour completed (+ review request)
 *   payment_success                     - payment received
 *   payment_failed                      - payment failed
 *   payment_refunded                    - refund processed
 *
 * Recipients per event: customer (booking customer_email), client
 * (Notification.client_notification_email -> Company.email fallback),
 * admin (Notification.admin_notification_email).
 *
 * Recipient switches (settings, category 'Notification'):
 *   notify_customer_enabled  (default 1)
 *   notify_client_enabled    (default 1)
 *   notify_admin_enabled     (default 0 - optional per spec)
 *   booking_confirmation_enabled, cancellation_email_enabled,
 *   payment_reminder_enabled, booking_reminder_days, booking_reminder_hours
 */

require_once __DIR__ . '/MailService.php';

const BOOKING_EVENT_MAP = [
    'booking_created'          => ['template' => 'template-booking-received',         'roles' => ['customer', 'client', 'admin'], 'flag' => 'booking_confirmation_enabled'],
    'booking_pending'          => ['template' => 'template-booking-received',         'roles' => ['customer', 'client', 'admin'], 'flag' => 'booking_confirmation_enabled'],
    'booking_approved'         => ['template' => 'template-booking-confirmation',     'roles' => ['customer', 'client', 'admin'], 'flag' => 'booking_confirmation_enabled'],
    'booking_rejected'         => ['template' => 'template-booking-rejected',         'roles' => ['customer', 'admin'],           'flag' => 'cancellation_email_enabled'],
    'booking_cancelled_user'   => ['template' => 'template-booking-cancellation',     'roles' => ['customer', 'client', 'admin'], 'flag' => 'cancellation_email_enabled'],
    'booking_cancelled_client' => ['template' => 'template-booking-cancelled-client', 'roles' => ['customer', 'client', 'admin'], 'flag' => 'cancellation_email_enabled'],
    'booking_rescheduled'      => ['template' => 'template-booking-rescheduled',      'roles' => ['customer', 'client', 'admin'], 'flag' => null],
    'booking_reminder'         => ['template' => 'template-reminder-24h',             'roles' => ['customer', 'client'],          'flag' => null],
    'booking_completed'        => ['template' => 'template-booking-completed',        'roles' => ['customer', 'client', 'admin'], 'flag' => null],
    'payment_success'          => ['template' => 'template-payment-received',         'roles' => ['customer', 'client', 'admin'], 'flag' => null],
    'payment_failed'           => ['template' => 'template-payment-failed',           'roles' => ['customer', 'admin'],           'flag' => null],
    'payment_refunded'         => ['template' => 'template-payment-refunded',         'roles' => ['customer', 'client', 'admin'], 'flag' => null],
    'payment_reminder'         => ['template' => 'template-payment-reminder',         'roles' => ['customer', 'client'],          'flag' => 'payment_reminder_enabled'],
];

/**
 * Read Notification/Company settings once.
 */
function getNotificationSettings($conn) {
    $s = [
        'notify_customer_enabled' => '1',
        'notify_client_enabled'   => '1',
        'notify_admin_enabled'    => '0',
        'client_notification_email' => '',
        'admin_notification_email'  => '',
        'booking_confirmation_enabled' => '1',
        'cancellation_email_enabled'   => '1',
        'payment_reminder_enabled'     => '1',
        'booking_reminder_days'   => '7,3,1',
        'booking_reminder_hours'  => '24',
        'disabled_events'         => '',
        'company_name'  => 'Aventra Booking',
        'company_email' => '',
        'language'      => 'en',
    ];

    $res = @$conn->query("SELECT category, `key`, `value` FROM settings WHERE category IN ('Notification', 'Company', 'System', 'Email')");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $k = ($row['category'] === 'Company' ? 'company_' : '') . $row['key'];
            if ($row['category'] === 'System' && $row['key'] === 'language') $k = 'language';
            if ($row['category'] === 'Email' && $row['key'] === 'smtp_username') $k = 'smtp_user';
            if ($row['value'] !== null && $row['value'] !== '') {
                $s[$k] = $row['value'];
            }
        }
        $res->free();
    }

    // Client email fallback: dedicated setting -> company email -> SMTP mailbox
    if (empty($s['client_notification_email'])) {
        $s['client_notification_email'] = $s['company_email'] !== '' ? $s['company_email'] : ($s['smtp_user'] ?? '');
    }

    return $s;
}

function notifySettingEnabled($value) {
    return !in_array(strtolower(trim((string) $value)), ['0', 'false', 'no', 'off'], true);
}

/**
 * Load a booking with its tour info.
 */
function getBookingForNotify($conn, $bookingId) {
    $stmt = $conn->prepare(
        "SELECT b.*, t.title AS tour_name, t.location AS tour_location, t.currency AS tour_currency
         FROM bookings b
         LEFT JOIN tours t ON t.id = b.tour_id
         WHERE b.id = ?"
    );
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    $booking = $result->num_rows > 0 ? $result->fetch_assoc() : null;
    $stmt->close();
    return $booking;
}

/**
 * Build placeholder data for templates.
 */
function buildBookingEmailData($booking, $settings, $extra = []) {
    $frontendUrl = rtrim(getenv('FRONTEND_URL') ?: 'https://booking.prismadot.com', '/');
    $ref = $booking['booking_reference'] ?? $booking['id'];

    $data = [
        'bookingId'       => $booking['id'],
        'bookingReference'=> $ref,
        'customerName'    => $booking['customer_name'] ?? '',
        'customerEmail'   => $booking['customer_email'] ?? '',
        'customerPhone'   => $booking['customer_phone'] ?? '',
        'guestName'       => $booking['customer_name'] ?? '',
        'guestEmail'      => $booking['customer_email'] ?? '',
        'guestCount'      => $booking['number_of_people'] ?? '',
        'tourName'        => $booking['tour_name'] ?? 'Your Tour',
        'tourLocation'    => $booking['tour_location'] ?? '',
        'tourDate'        => $booking['departure_date'] ?? '',
        'tourTime'        => $booking['departure_date'] ?? '',
        'returnDate'      => $booking['return_date'] ?? '',
        'tripDate'        => $booking['departure_date'] ?? '',
        'totalPrice'      => $booking['total_price'] ?? '',
        'totalAmount'     => $booking['total_price'] ?? '',
        'paidAmount'      => $booking['deposit_paid'] ?? '',
        'depositPaid'     => $booking['deposit_paid'] ?? '',
        'balanceDue'      => $booking['balance_due'] ?? '',
        'remainingAmount' => $booking['balance_due'] ?? '',
        'currency'        => $booking['tour_currency'] ?? 'SEK',
        'status'          => $booking['status'] ?? '',
        'paymentStatus'   => $booking['payment_status'] ?? '',
        'specialRequests' => $booking['special_requirements'] ?? '',
        'notes'           => $booking['notes'] ?? '',
        'businessName'    => $settings['company_name'],
        'bookingLink'     => $frontendUrl . '/#/my-bookings',
        'bookingDetailsUrl' => $frontendUrl . '/#/my-bookings',
        'paymentLink'     => $frontendUrl . '/#/my-bookings?pay=' . urlencode((string) $booking['id']),
        'reviewLink'      => $frontendUrl . '/#/my-bookings?review=' . urlencode((string) $booking['id']),
        // event-specific extras
        'previousDate'    => $extra['previousDate'] ?? '',
        'newDate'         => $extra['newDate'] ?? ($booking['departure_date'] ?? ''),
        'reason'          => $extra['reason'] ?? '',
        'amount'          => $extra['amount'] ?? ($booking['total_price'] ?? ''),
        'transactionId'   => $extra['transactionId'] ?? '',
        'paymentDate'     => $extra['paymentDate'] ?? date('Y-m-d'),
        'refundAmount'    => $extra['refundAmount'] ?? ($booking['deposit_paid'] ?? ''),
    ];

    // Allow caller-supplied data to override
    foreach ($extra as $k => $v) {
        if (is_scalar($v) || $v === null) {
            $data[$k] = $v;
        }
    }
    return $data;
}

/**
 * Load template content (preferred language, fallback any).
 */
function getTemplateContentForNotify($conn, $templateId, $language = 'en') {
    $stmt = $conn->prepare(
        "SELECT subject, html_content, text_content FROM email_template_content
         WHERE template_id = ?
         ORDER BY (language = ?) DESC, language ASC
         LIMIT 1"
    );
    if (!$stmt) {
        return null; // e.g. email_template_content table missing -> fall back to generic mail
    }
    $stmt->bind_param('ss', $templateId, $language);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->num_rows > 0 ? $res->fetch_assoc() : null;
    $stmt->close();
    return $row;
}

/**
 * Send booking event notifications.
 *
 * @param mysqli $conn
 * @param string $event     One of the keys in BOOKING_EVENT_MAP
 * @param int    $bookingId
 * @param array  $extra     Extra placeholder data (previousDate, reason, amount, ...)
 * @return array{sent:int, skipped:int, failed:int, results:array}
 */
function notifyBookingEvent($conn, $event, $bookingId, $extra = []) {
    $summary = ['sent' => 0, 'skipped' => 0, 'failed' => 0, 'results' => []];

    if (!isset(BOOKING_EVENT_MAP[$event])) {
        $summary['results'][] = ['error' => "Unknown event: $event"];
        return $summary;
    }

    $settings = getNotificationSettings($conn);
    $cfg = BOOKING_EVENT_MAP[$event];

    // Globally disabled events (Notification.disabled_events, comma separated)
    $disabled = array_map('trim', explode(',', strtolower((string) ($settings['disabled_events'] ?? ''))));
    if (in_array(strtolower($event), $disabled, true)) {
        $summary['results'][] = ['skipped' => "event '$event' disabled by settings"];
        return $summary;
    }

    if ($cfg['flag'] && !notifySettingEnabled($settings[$cfg['flag']] ?? '1')) {
        $summary['results'][] = ['skipped' => "disabled by setting {$cfg['flag']}"];
        return $summary;
    }

    $booking = getBookingForNotify($conn, $bookingId);
    if (!$booking) {
        $summary['results'][] = ['error' => 'Booking not found'];
        return $summary;
    }

    // Resolve recipients by role
    $recipients = [];
    if (in_array('customer', $cfg['roles']) && notifySettingEnabled($settings['notify_customer_enabled'])) {
        if (!empty($booking['customer_email'])) {
            $recipients[$booking['customer_email']] = 'customer';
        }
    }
    if (in_array('client', $cfg['roles']) && notifySettingEnabled($settings['notify_client_enabled'])) {
        $clientEmail = trim($settings['client_notification_email']);
        if ($clientEmail !== '') {
            $recipients[$clientEmail] = 'client';
        }
    }
    if (in_array('admin', $cfg['roles']) && notifySettingEnabled($settings['notify_admin_enabled'])) {
        $adminEmail = trim($settings['admin_notification_email']);
        if ($adminEmail !== '') {
            $recipients[$adminEmail] = 'admin';
        }
    }

    if (empty($recipients)) {
        $summary['results'][] = ['skipped' => 'no recipients resolved'];
        return $summary;
    }

    $template = getTemplateContentForNotify($conn, $cfg['template'], $settings['language'] ?: 'en');
    $data = buildBookingEmailData($booking, $settings, $extra);

    foreach ($recipients as $email => $role) {
        if ($template) {
            $subject = renderEmailTemplate($template['subject'], $data);
            $html = renderEmailTemplate($template['html_content'], $data);
            $text = $template['text_content'] ? renderEmailTemplate($template['text_content'], $data) : null;
        } else {
            // No template - generic notification
            $subject = "Booking {$data['bookingReference']} - {$event}";
            $html = '<html><body><h2>' . htmlspecialchars($subject) . '</h2>'
                . '<p>Tour: ' . htmlspecialchars($data['tourName']) . '<br>'
                . 'Customer: ' . htmlspecialchars($data['customerName']) . '<br>'
                . 'Date: ' . htmlspecialchars($data['tourDate']) . '<br>'
                . 'Status: ' . htmlspecialchars($data['status']) . '</p></body></html>';
            $text = null;
        }

        $send = sendSmtpEmail($conn, $email, $subject, $html, $text);

        if ($send['success']) {
            $summary['sent']++;
            $summary['results'][] = ['to' => $email, 'role' => $role, 'sent' => true];
        } else {
            $summary['failed']++;
            $summary['results'][] = ['to' => $email, 'role' => $role, 'sent' => false, 'error' => $send['error'] ?? 'unknown'];
        }
    }

    if (function_exists('debugLog')) {
        debugLog('BOOKING_NOTIFY', [
            'event' => $event,
            'booking' => $bookingId,
            'sent' => $summary['sent'],
            'failed' => $summary['failed'],
        ]);
    }

    return $summary;
}

/**
 * Ensure the scheduled_emails queue table exists.
 */
function ensureScheduledEmailsTable($conn) {
    return $conn->query(
        "CREATE TABLE IF NOT EXISTS scheduled_emails (
            id INT AUTO_INCREMENT PRIMARY KEY,
            booking_id VARCHAR(64) NOT NULL,
            to_email VARCHAR(255) NULL,
            subject VARCHAR(255) NULL,
            template_name VARCHAR(100) NULL,
            payload LONGTEXT NULL,
            reminder_type VARCHAR(50) NOT NULL DEFAULT 'generic',
            scheduled_for DATETIME NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'PENDING',
            error TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            sent_at DATETIME NULL,
            INDEX idx_due (status, scheduled_for)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );
}

/**
 * Schedule reminder emails for a booking based on
 * Notification.booking_reminder_days and .booking_reminder_hours.
 * Sends to customer + client.
 */
function scheduleBookingReminders($conn, $bookingId) {
    ensureScheduledEmailsTable($conn);

    $settings = getNotificationSettings($conn);
    $disabled = array_map('trim', explode(',', strtolower((string) ($settings['disabled_events'] ?? ''))));
    if (in_array('booking_reminder', $disabled, true)) {
        return;
    }

    $booking = getBookingForNotify($conn, $bookingId);
    if (!$booking || empty($booking['departure_date'])) {
        return;
    }
    $departure = strtotime($booking['departure_date']);
    if (!$departure) {
        return;
    }

    // Clear pending reminders for this booking (e.g. after reschedule)
    $del = $conn->prepare("DELETE FROM scheduled_emails WHERE booking_id = ? AND reminder_type = 'booking_reminder' AND status = 'PENDING'");
    if (!$del) {
        return;
    }
    $bid = (string) $bookingId;
    $del->bind_param('s', $bid);
    $del->execute();
    $del->close();

    $offsets = []; // keyed by seconds to dedupe (e.g. "1 day" == "24 hours")
    foreach (explode(',', (string) $settings['booking_reminder_days']) as $d) {
        $d = (int) trim($d);
        if ($d > 0) {
            $offsets[$d * 86400] = ['seconds' => $d * 86400, 'template' => $d > 1 ? 'reminder-3d' : 'reminder-24h'];
        }
    }
    foreach (explode(',', (string) $settings['booking_reminder_hours']) as $h) {
        $h = (int) trim($h);
        if ($h > 0 && !isset($offsets[$h * 3600])) {
            $offsets[$h * 3600] = ['seconds' => $h * 3600, 'template' => 'reminder-24h'];
        }
    }

    // Recipients: customer + client (deduped - they may share the address)
    $emails = [];
    if (notifySettingEnabled($settings['notify_customer_enabled']) && !empty($booking['customer_email'])) {
        $emails[] = strtolower(trim($booking['customer_email']));
    }
    if (notifySettingEnabled($settings['notify_client_enabled']) && !empty($settings['client_notification_email'])) {
        $emails[] = strtolower(trim($settings['client_notification_email']));
    }
    $emails = array_values(array_unique($emails));

    $data = buildBookingEmailData($booking, $settings, []);

    $ins = $conn->prepare(
        "INSERT INTO scheduled_emails (booking_id, to_email, subject, template_name, payload, reminder_type, scheduled_for)
         VALUES (?, ?, ?, ?, ?, 'booking_reminder', ?)"
    );
    if (!$ins) {
        return;
    }

    foreach ($offsets as $off) {
        $sendAt = date('Y-m-d H:i:s', $departure - $off['seconds']);
        if (strtotime($sendAt) <= time()) {
            continue; // already in the past
        }
        foreach ($emails as $email) {
            $payload = json_encode($data);
            $subject = 'Reminder: ' . $data['tourName'];
            $ins->bind_param('ssssss', $bid, $email, $subject, $off['template'], $payload, $sendAt);
            $ins->execute();
        }
    }
    $ins->close();
}

/**
 * Cancel pending reminder emails for a booking (cancelled/rejected bookings).
 */
function cancelBookingReminders($conn, $bookingId) {
    ensureScheduledEmailsTable($conn);
    $stmt = $conn->prepare("DELETE FROM scheduled_emails WHERE booking_id = ? AND reminder_type = 'booking_reminder' AND status = 'PENDING'");
    if (!$stmt) {
        return;
    }
    $bid = (string) $bookingId;
    $stmt->bind_param('s', $bid);
    $stmt->execute();
    $stmt->close();
}
?>

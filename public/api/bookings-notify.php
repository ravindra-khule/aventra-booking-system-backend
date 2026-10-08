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
 * POST /api/bookings-notify.php
 *
 * Trigger booking lifecycle notification emails.
 * Body: { bookingId, event, data? }
 *
 * Events: booking_created, booking_pending, booking_approved,
 *         booking_rejected, booking_cancelled_user, booking_cancelled_client,
 *         booking_rescheduled, booking_reminder, booking_completed,
 *         payment_success, payment_failed, payment_refunded
 *
 * `data` may carry extra placeholders: previousDate, newDate, reason,
 * amount, transactionId, paymentDate, refundAmount, ...
 */

require_once __DIR__ . '/../../config.php';
$__notifierPath = __DIR__ . '/../../lib/BookingNotifier.php';
if (!is_file($__notifierPath)) {
    sendJSON(['success' => false, 'error' => 'BookingNotifier library is not installed on the server'], 500);
}
require_once $__notifierPath;

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }

    $body = json_decode(file_get_contents('php://input'), true);

    $bookingId = (int) ($body['bookingId'] ?? $body['booking_id'] ?? 0);
    $event = $body['event'] ?? '';
    $extra = isset($body['data']) && is_array($body['data']) ? $body['data'] : [];

    if (!$bookingId || !$event) {
        sendJSON(['success' => false, 'error' => 'bookingId and event are required'], 400);
    }

    $conn = getDB();

    $summary = notifyBookingEvent($conn, $event, $bookingId, $extra);

    // Keep reminders in sync with lifecycle
    if (in_array($event, ['booking_created', 'booking_approved'], true)) {
        scheduleBookingReminders($conn, $bookingId);
    }
    if (in_array($event, ['booking_cancelled_user', 'booking_cancelled_client', 'booking_rejected'], true)) {
        cancelBookingReminders($conn, $bookingId);
    }

    $conn->close();

    sendJSON([
        'success' => true,
        'data' => [
            'event' => $event,
            'bookingId' => $bookingId,
            'sent' => $summary['sent'],
            'failed' => $summary['failed'],
            'results' => $summary['results'],
        ],
    ]);

} catch (Throwable $e) {
    debugLog('Booking Notify Error', ['error' => $e->getMessage()]);
    sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
}
?>

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
 * PUT /api/bookings.php
 * Update booking status (admin only)
 */

require_once __DIR__ . '/../../config.php';
// Email notifications are best-effort: a missing notifier must never break bookings.
$__notifierPath = __DIR__ . '/../../lib/BookingNotifier.php';
if (is_file($__notifierPath)) {
    require_once $__notifierPath;
}

try {
    // Only allow PUT/POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    // Get JSON body
    $body = json_decode(file_get_contents('php://input'), true);
    
    if (!$body) {
        sendJSON(['success' => false, 'error' => 'Invalid JSON body'], 400);
    }
    
    $bookingId = $body['id'] ?? null;
    $status = $body['status'] ?? null;
    $paymentStatus = $body['paymentStatus'] ?? null;
    $notes = $body['notes'] ?? null;
    $departureDate = $body['departureDate'] ?? null;          // reschedule
    $cancelledBy = strtolower(trim($body['cancelledBy'] ?? 'client')); // 'customer' | 'client'
    $rejected = !empty($body['rejected']);                  // client rejects a booking
    
    if (!$bookingId) {
        sendJSON(['success' => false, 'error' => 'Booking ID is required'], 400);
    }
    
    // A rejection is stored as a cancellation
    if ($rejected && !$status) {
        $status = 'cancelled';
    }
    
    // Validate status
    $validStatuses = ['pending', 'confirmed', 'completed', 'cancelled', 'refunded'];
    if ($status && !in_array($status, $validStatuses)) {
        sendJSON(['success' => false, 'error' => 'Invalid status'], 400);
    }
    
    // Validate payment status
    $validPaymentStatuses = ['pending', 'partial', 'paid', 'refunded'];
    if ($paymentStatus && !in_array($paymentStatus, $validPaymentStatuses)) {
        sendJSON(['success' => false, 'error' => 'Invalid payment status'], 400);
    }
    
    $conn = getDB();
    
    // Check if booking exists
    $checkSql = "SELECT id, status, payment_status, tour_id, number_of_people, departure_date FROM bookings WHERE id = ? AND deleted_at IS NULL";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param('i', $bookingId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        sendJSON(['success' => false, 'error' => 'Booking not found'], 404);
    }
    
    $existingBooking = $checkResult->fetch_assoc();
    $checkStmt->close();
    
    // Prepare update query
    $updateFields = [];
    $types = '';
    $values = [];
    
    if ($status) {
        $updateFields[] = "status = ?";
        $types .= 's';
        $values[] = $status;
    }
    
    if ($paymentStatus) {
        $updateFields[] = "payment_status = ?";
        $types .= 's';
        $values[] = $paymentStatus;
    }
    
    if ($notes !== null) {
        $updateFields[] = "notes = ?";
        $types .= 's';
        $values[] = $notes;
    }
    
    // Reschedule: new departure date
    if ($departureDate !== null && $departureDate !== '') {
        $parsed = strtotime($departureDate);
        if ($parsed === false) {
            sendJSON(['success' => false, 'error' => 'Invalid departureDate'], 400);
        }
        $departureDate = date('Y-m-d', $parsed);
        $updateFields[] = "departure_date = ?";
        $types .= 's';
        $values[] = $departureDate;
    }
    
    // Handle cancellation
    if ($status === 'cancelled' && $existingBooking['status'] !== 'cancelled') {
        $updateFields[] = "cancelled_at = NOW()";
    }
    
    // Always update the timestamp
    $updateFields[] = "updated_at = NOW()";
    
    if (empty($updateFields)) {
        sendJSON(['success' => false, 'error' => 'No fields to update'], 400);
    }
    
    // Build update query
    $updateSql = "UPDATE bookings SET " . implode(', ', $updateFields) . " WHERE id = ?";
    
    $updateStmt = $conn->prepare($updateSql);
    
    if (!$updateStmt) {
        sendJSON(['success' => false, 'error' => 'Query prepare failed'], 500);
    }
    
    // Bind parameters
    $types .= 'i';
    $values[] = $bookingId;
    
    $updateStmt->bind_param($types, ...$values);
    
    if (!$updateStmt->execute()) {
        sendJSON(['success' => false, 'error' => 'Failed to update booking'], 500);
    }
    
    $updateStmt->close();
    
    // Restore tour spots when a booking is newly cancelled or refunded
    $freedStatuses = ['cancelled', 'refunded'];
    if ($status && in_array($status, $freedStatuses) && !in_array($existingBooking['status'], $freedStatuses)) {
        $restoreSql = "UPDATE tours SET available_spots = LEAST(max_capacity, available_spots + ?) WHERE id = ?";
        $restoreStmt = $conn->prepare($restoreSql);
        $restoreStmt->bind_param('ii', $existingBooking['number_of_people'], $existingBooking['tour_id']);
        $restoreStmt->execute();
        $restoreStmt->close();
    }
    
    $conn->commit();
    
    // ---- Lifecycle notifications (non-fatal) ----
    $emailsSent = 0;
    try {
        if (!function_exists('notifyBookingEvent')) {
            throw new Exception('BookingNotifier not available');
        }
        $oldStatus = $existingBooking['status'];
        $oldPayment = $existingBooking['payment_status'];
        $oldDeparture = $existingBooking['departure_date'];
        $events = [];
        $extra = ['reason' => $notes ?? ''];

        if ($status && $status !== $oldStatus) {
            if ($status === 'confirmed') {
                $events[] = 'booking_approved';
            } elseif ($status === 'completed') {
                $events[] = 'booking_completed';
            } elseif ($status === 'cancelled') {
                $events[] = $rejected
                    ? 'booking_rejected'
                    : ($cancelledBy === 'customer' ? 'booking_cancelled_user' : 'booking_cancelled_client');
            } elseif ($status === 'refunded') {
                $events[] = 'payment_refunded';
            }
        }

        if ($paymentStatus && $paymentStatus !== $oldPayment) {
            if ($paymentStatus === 'paid') {
                $events[] = 'payment_success';
            } elseif ($paymentStatus === 'refunded' && $status !== 'refunded') {
                $events[] = 'payment_refunded';
            }
        }

        $rescheduled = ($departureDate !== null && $departureDate !== '' && $departureDate !== $oldDeparture);
        if ($rescheduled) {
            $events[] = 'booking_rescheduled';
            $extra['previousDate'] = $oldDeparture;
            $extra['newDate'] = $departureDate;
        }

        foreach (array_unique($events) as $event) {
            $result = notifyBookingEvent($conn, $event, (int) $bookingId, $extra);
            $emailsSent += $result['sent'];
        }

        // Keep the reminder queue in sync with the lifecycle
        if ($rescheduled || in_array('booking_approved', $events, true)) {
            scheduleBookingReminders($conn, (int) $bookingId);
        }
        if ($status === 'cancelled' || $status === 'refunded') {
            cancelBookingReminders($conn, (int) $bookingId);
        }
    } catch (Throwable $notifyError) {
        debugLog('Booking Notify Error (update)', ['error' => $notifyError->getMessage()]);
    }
    
    // Get updated booking
    $getSql = "SELECT 
                    id, booking_reference, tour_id, number_of_people, 
                    total_price, status, payment_status, customer_name, booking_date
                FROM bookings WHERE id = ?";
    
    $getStmt = $conn->prepare($getSql);
    $getStmt->bind_param('i', $bookingId);
    $getStmt->execute();
    $getResult = $getStmt->get_result();
    $updatedBooking = $getResult->fetch_assoc();
    $getStmt->close();
    
    $conn->close();
    
    sendJSON([
        'success' => true,
        'message' => 'Booking updated successfully',
        'data' => [
            'id' => (string) $updatedBooking['id'],
            'bookingReference' => $updatedBooking['booking_reference'],
            'status' => $updatedBooking['status'],
            'paymentStatus' => $updatedBooking['payment_status'],
            'emailsSent' => $emailsSent
        ]
    ]);
    
} catch (Throwable $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

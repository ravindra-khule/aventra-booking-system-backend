<?php
/**
 * PUT /api/bookings.php
 * Update booking status (admin only)
 */

require_once __DIR__ . '/../../config.php';

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
    
    if (!$bookingId) {
        sendJSON(['success' => false, 'error' => 'Booking ID is required'], 400);
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
    $checkSql = "SELECT id, status FROM bookings WHERE id = ?";
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
            'paymentStatus' => $updatedBooking['payment_status']
        ]
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

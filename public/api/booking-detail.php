<?php
/**
 * GET /api/booking-detail.php?id=1
 * Get single booking details
 */

require_once __DIR__ . '/../../config.php';

try {
    // Only allow GET requests
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    $bookingId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    if (!$bookingId) {
        sendJSON(['success' => false, 'error' => 'Booking ID is required'], 400);
    }
    
    $conn = getDB();
    
    // Get booking with tour details
    $sql = "SELECT 
                b.*,
                t.id as tour_id,
                t.title,
                t.location,
                t.country,
                t.duration_days,
                t.next_date,
                t.image_url,
                u.name as user_name,
                u.email as user_email
            FROM bookings b
            JOIN tours t ON b.tour_id = t.id
            LEFT JOIN users u ON b.user_id = u.id
            WHERE b.id = ? AND b.deleted_at IS NULL
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        sendJSON(['success' => false, 'error' => 'Query prepare failed'], 500);
    }
    
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendJSON(['success' => false, 'error' => 'Booking not found'], 404);
    }
    
    $booking = $result->fetch_assoc();
    $stmt->close();
    
    // Get booking items (participants, addons)
    $itemsSql = "SELECT * FROM booking_items WHERE booking_id = ?";
    $itemsStmt = $conn->prepare($itemsSql);
    $itemsStmt->bind_param('i', $bookingId);
    $itemsStmt->execute();
    $itemsResult = $itemsStmt->get_result();
    
    $items = [];
    while ($item = $itemsResult->fetch_assoc()) {
        $items[] = [
            'id' => (string) $item['id'],
            'type' => $item['item_type'],
            'description' => $item['item_description'],
            'quantity' => (int) $item['quantity'],
            'unitPrice' => (float) $item['unit_price'],
            'subtotal' => (float) $item['subtotal']
        ];
    }
    $itemsStmt->close();
    
    $conn->close();
    
    sendJSON([
        'success' => true,
        'data' => [
            'id' => (string) $booking['id'],
            'bookingReference' => $booking['booking_reference'],
            'userId' => (string) $booking['user_id'],
            'tourId' => (string) $booking['tour_id'],
            'tourTitle' => $booking['title'],
            'tourLocation' => $booking['location'],
            'tourCountry' => $booking['country'],
            'tourDurationDays' => (int) $booking['duration_days'],
            'tourNextDate' => $booking['next_date'],
            'tourImageUrl' => $booking['image_url'],
            'numberOfPeople' => (int) $booking['number_of_people'],
            'totalPrice' => (float) $booking['total_price'],
            'depositPaid' => (float) $booking['deposit_paid'],
            'balanceDue' => (float) $booking['balance_due'],
            'status' => $booking['status'],
            'paymentStatus' => $booking['payment_status'],
            'customerName' => $booking['customer_name'],
            'customerEmail' => $booking['customer_email'],
            'customerPhone' => $booking['customer_phone'],
            'specialRequirements' => $booking['special_requirements'],
            'notes' => $booking['notes'],
            'bookingDate' => $booking['booking_date'],
            'departureDate' => $booking['departure_date'],
            'returnDate' => $booking['return_date'],
            'items' => $items,
            'userName' => $booking['user_name'],
            'userEmail' => $booking['user_email']
        ]
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

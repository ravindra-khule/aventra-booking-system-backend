<?php
/**
 * POST /api/bookings.php
 * Create a new tour booking
 */

require_once __DIR__ . '/../../config.php';

try {
    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    // Get JSON body
    $body = json_decode(file_get_contents('php://input'), true);
    
    if (!$body) {
        sendJSON(['success' => false, 'error' => 'Invalid JSON body'], 400);
    }
    
    // Validate required fields
    $userId = $body['userId'] ?? null;
    $tourId = $body['tourId'] ?? null;
    $numberOfPeople = $body['numberOfPeople'] ?? null;
    $customerName = $body['customerName'] ?? null;
    $customerEmail = $body['customerEmail'] ?? null;
    $customerPhone = $body['customerPhone'] ?? null;
    
    if (!$userId || !$tourId || !$numberOfPeople || !$customerName || !$customerEmail) {
        sendJSON(['success' => false, 'error' => 'Missing required fields'], 400);
    }
    
    if ($numberOfPeople < 1) {
        sendJSON(['success' => false, 'error' => 'Number of people must be at least 1'], 400);
    }
    
    $conn = getDB();
    
    // Get tour details
    $tourSql = "SELECT id, price, available_spots FROM tours WHERE id = ?";
    $tourStmt = $conn->prepare($tourSql);
    
    if (!$tourStmt) {
        sendJSON(['success' => false, 'error' => 'Tour query prepare failed'], 500);
    }
    
    $tourStmt->bind_param('i', $tourId);
    $tourStmt->execute();
    $tourResult = $tourStmt->get_result();
    
    if ($tourResult->num_rows === 0) {
        sendJSON(['success' => false, 'error' => 'Tour not found'], 404);
    }
    
    $tour = $tourResult->fetch_assoc();
    $tourStmt->close();
    
    // Check availability
    if ($tour['available_spots'] < $numberOfPeople) {
        sendJSON(['success' => false, 'error' => 'Not enough available spots'], 400);
    }
    
    // Calculate price
    $totalPrice = $tour['price'] * $numberOfPeople;
    $depositPaid = $totalPrice * 0.2; // 20% deposit
    $balanceDue = $totalPrice - $depositPaid;
    
    // Generate reference number
    $bookingReference = 'BK' . date('YmdHis') . rand(1000, 9999);
    
    // Create booking
    $sql = "INSERT INTO bookings (
        user_id, tour_id, booking_reference, number_of_people, 
        total_price, deposit_paid, balance_due, 
        customer_name, customer_email, customer_phone, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        sendJSON(['success' => false, 'error' => 'Query prepare failed'], 500);
    }
    
    $stmt->bind_param('iisidddsss', $userId, $tourId, $bookingReference, $numberOfPeople, 
                      $totalPrice, $depositPaid, $balanceDue, $customerName, $customerEmail, $customerPhone);
    
    if (!$stmt->execute()) {
        sendJSON(['success' => false, 'error' => 'Failed to create booking'], 500);
    }
    
    $bookingId = $stmt->insert_id;
    $stmt->close();
    
    // Update tour available spots
    $updateSql = "UPDATE tours SET available_spots = available_spots - ? WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param('ii', $numberOfPeople, $tourId);
    $updateStmt->execute();
    $updateStmt->close();
    
    $conn->close();
    
    sendJSON([
        'success' => true,
        'message' => 'Booking created successfully',
        'data' => [
            'id' => (string) $bookingId,
            'bookingReference' => $bookingReference,
            'tourId' => (string) $tourId,
            'numberOfPeople' => (int) $numberOfPeople,
            'totalPrice' => (float) $totalPrice,
            'depositPaid' => (float) $depositPaid,
            'balanceDue' => (float) $balanceDue,
            'status' => 'pending',
            'paymentStatus' => 'pending'
        ]
    ], 201);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

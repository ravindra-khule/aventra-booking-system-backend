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
 * GET /api/bookings.php
 * Get user's bookings or all bookings (admin)
 */

require_once __DIR__ . '/../../config.php';

try {
    // Only allow GET requests
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    $userId = $_GET['userId'] ?? null;
    $isAdmin = isset($_GET['admin']) && $_GET['admin'] === 'true' ? true : false;
    
    if (!$userId && !$isAdmin) {
        sendJSON(['success' => false, 'error' => 'userId or admin flag required'], 400);
    }
    
    $conn = getDB();
    
    // Get bookings
    if ($isAdmin) {
        // Admin: Get all bookings with tour info
        $sql = "SELECT 
                    b.id,
                    b.booking_reference,
                    b.user_id,
                    b.tour_id,
                    b.number_of_people,
                    b.total_price,
                    b.deposit_paid,
                    b.balance_due,
                    b.status,
                    b.payment_status,
                    b.customer_name,
                    b.customer_email,
                    b.customer_phone,
                    b.booking_date,
                    t.title as tour_title,
                    t.location,
                    t.next_date
                FROM bookings b
                JOIN tours t ON b.tour_id = t.id
                WHERE b.deleted_at IS NULL
                ORDER BY b.booking_date DESC";
    } else {
        // User: Get their bookings
        $sql = "SELECT 
                    b.id,
                    b.booking_reference,
                    b.user_id,
                    b.tour_id,
                    b.number_of_people,
                    b.total_price,
                    b.deposit_paid,
                    b.balance_due,
                    b.status,
                    b.payment_status,
                    b.customer_name,
                    b.customer_email,
                    b.customer_phone,
                    b.booking_date,
                    t.title as tour_title,
                    t.location,
                    t.next_date
                FROM bookings b
                JOIN tours t ON b.tour_id = t.id
                WHERE b.user_id = ? AND b.deleted_at IS NULL
                ORDER BY b.booking_date DESC";
    }
    
    if ($isAdmin) {
        $result = $conn->query($sql);
    } else {
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
    }
    
    if (!$result) {
        sendJSON(['success' => false, 'error' => 'Query failed: ' . $conn->error], 500);
    }
    
    $bookings = [];
    while ($row = $result->fetch_assoc()) {
        $bookings[] = [
            'id' => (int) $row['id'],
            'booking_reference' => $row['booking_reference'],
            'user_id' => (int) $row['user_id'],
                        'tour_id' => (int) $row['tour_id'],
            'tour_title' => $row['tour_title'],
            'location' => $row['location'],
            'number_of_people' => (int) $row['number_of_people'],
            'total_price' => (float) $row['total_price'],
            'deposit_paid' => (float) $row['deposit_paid'],
            'balance_due' => (float) $row['balance_due'],
            'status' => $row['status'],
            'payment_status' => $row['payment_status'],
            'customer_name' => $row['customer_name'],
            'customer_email' => $row['customer_email'],
            'customer_phone' => $row['customer_phone'] ?? null,
            'booking_date' => $row['booking_date'],
            'next_date' => $row['next_date'],
            'departure_date' => null  // For compatibility
        ];
    }
    
    if (!$isAdmin && isset($stmt)) {
        $stmt->close();
    }
    
    $conn->close();
    
    sendJSON([
        'success' => true,
        'data' => $bookings,
        'total' => count($bookings)
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

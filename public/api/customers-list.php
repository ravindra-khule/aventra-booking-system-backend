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
 * GET /api/customers-list.php
 * Get all customers (admin only)
 */

require_once __DIR__ . '/../../config.php';

try {
    // Only allow GET requests
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    $conn = getDB();
    
    // Get all customers with their booking statistics
    $sql = "SELECT 
                c.id,
                c.first_name,
                c.last_name,
                c.email,
                c.phone,
                c.address,
                c.zip_code,
                c.city,
                c.country,
                c.notes,
                c.last_booking_date,
                c.created_at,
                COUNT(DISTINCT b.id) as total_bookings,
                COALESCE(SUM(b.total_price), 0) as total_spent
            FROM customers c
            LEFT JOIN bookings b ON c.id = b.customer_id AND b.deleted_at IS NULL
            WHERE c.deleted_at IS NULL
            GROUP BY c.id, c.first_name, c.last_name, c.email, c.phone, c.address, c.zip_code, c.city, c.country, c.notes, c.last_booking_date, c.created_at
            ORDER BY c.created_at DESC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        sendJSON(['success' => false, 'error' => 'Query failed: ' . $conn->error], 500);
    }
    
    $customers = [];
    while ($row = $result->fetch_assoc()) {
        $customers[] = [
            'id' => (int) $row['id'],
            'first_name' => $row['first_name'],
            'last_name' => $row['last_name'],
            'email' => $row['email'],
            'phone' => $row['phone'] ?? null,
            'address' => $row['address'] ?? null,
            'zip_code' => $row['zip_code'] ?? null,
            'city' => $row['city'] ?? null,
            'country' => $row['country'] ?? null,
            'notes' => $row['notes'] ?? null,
            'last_booking_date' => $row['last_booking_date'],
            'total_bookings' => (int) $row['total_bookings'],
            'total_spent' => (float) $row['total_spent'],
            'created_at' => $row['created_at']
        ];
    }
    
    $conn->close();
    
    sendJSON([
        'success' => true,
        'data' => $customers,
        'total' => count($customers)
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

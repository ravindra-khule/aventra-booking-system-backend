<?php
/**
 * GET /api/customers-get.php
 * Get a specific customer by ID
 */

require_once __DIR__ . '/../../config.php';

try {
    // Only allow GET requests
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    $id = $_GET['id'] ?? null;
    
    if (!$id) {
        sendJSON(['success' => false, 'error' => 'Customer ID is required'], 400);
    }
    
    $conn = getDB();
    
    // Get customer with booking statistics
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
            WHERE c.id = ? AND c.deleted_at IS NULL
            GROUP BY c.id, c.first_name, c.last_name, c.email, c.phone, c.address, c.zip_code, c.city, c.country, c.notes, c.last_booking_date, c.created_at";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        sendJSON(['success' => false, 'error' => 'Query prepare failed'], 500);
    }
    
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendJSON(['success' => false, 'error' => 'Customer not found'], 404);
    }
    
    $row = $result->fetch_assoc();
    $stmt->close();
    
    $customer = [
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
    
    $conn->close();
    
    sendJSON([
        'success' => true,
        'data' => $customer
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

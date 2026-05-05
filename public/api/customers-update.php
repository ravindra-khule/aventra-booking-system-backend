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
 * PUT /api/customers-update.php
 * Update customer information
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
    
    $id = $body['id'] ?? null;
    
    if (!$id) {
        sendJSON(['success' => false, 'error' => 'Customer ID is required'], 400);
    }
    
    $conn = getDB();
    
    // Check if customer exists
    $checkSql = "SELECT id FROM customers WHERE id = ? AND deleted_at IS NULL";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param('i', $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $checkStmt->close();
    
    if ($checkResult->num_rows === 0) {
        sendJSON(['success' => false, 'error' => 'Customer not found'], 404);
    }
    
    // Prepare update query
    $updateFields = [];
    $types = '';
    $values = [];
    
    if (isset($body['firstName'])) {
        $updateFields[] = "first_name = ?";
        $types .= 's';
        $values[] = $body['firstName'];
    }
    
    if (isset($body['lastName'])) {
        $updateFields[] = "last_name = ?";
        $types .= 's';
        $values[] = $body['lastName'];
    }
    
    if (isset($body['email'])) {
        $updateFields[] = "email = ?";
        $types .= 's';
        $values[] = $body['email'];
    }
    
    if (isset($body['phone'])) {
        $updateFields[] = "phone = ?";
        $types .= 's';
        $values[] = $body['phone'];
    }
    
    if (isset($body['address'])) {
        $updateFields[] = "address = ?";
        $types .= 's';
        $values[] = $body['address'];
    }
    
    if (isset($body['zipCode'])) {
        $updateFields[] = "zip_code = ?";
        $types .= 's';
        $values[] = $body['zipCode'];
    }
    
    if (isset($body['city'])) {
        $updateFields[] = "city = ?";
        $types .= 's';
        $values[] = $body['city'];
    }
    
    if (isset($body['country'])) {
        $updateFields[] = "country = ?";
        $types .= 's';
        $values[] = $body['country'];
    }
    
    if (isset($body['notes'])) {
        $updateFields[] = "notes = ?";
        $types .= 's';
        $values[] = $body['notes'];
    }
    
    if (isset($body['lastBookingDate'])) {
        $updateFields[] = "last_booking_date = ?";
        $types .= 's';
        $values[] = $body['lastBookingDate'];
    }
    
    // Check if any actual fields to update (at least one besides updated_at)
    if (empty($updateFields)) {
        sendJSON(['success' => false, 'error' => 'No fields to update'], 400);
    }
    
    // Always update the timestamp
    $updateFields[] = "updated_at = NOW()";
    
    // Build update query
    $updateSql = "UPDATE customers SET " . implode(', ', $updateFields) . " WHERE id = ?";
    
    $updateStmt = $conn->prepare($updateSql);
    
    if (!$updateStmt) {
        sendJSON(['success' => false, 'error' => 'Query prepare failed'], 500);
    }
    
    // Bind parameters
    $types .= 'i';
    $values[] = $id;
    
    $updateStmt->bind_param($types, ...$values);
    
    if (!$updateStmt->execute()) {
        sendJSON(['success' => false, 'error' => 'Failed to update customer'], 500);
    }
    
    $updateStmt->close();
    
    // Get updated customer
    $getSql = "SELECT 
                    id, first_name, last_name, email, phone, 
                    address, zip_code, city, country, notes, 
                    last_booking_date, created_at
                FROM customers WHERE id = ?";
    
    $getStmt = $conn->prepare($getSql);
    $getStmt->bind_param('i', $id);
    $getStmt->execute();
    $getResult = $getStmt->get_result();
    $row = $getResult->fetch_assoc();
    $getStmt->close();
    
    $conn->close();
    
    sendJSON([
        'success' => true,
        'message' => 'Customer updated successfully',
        'data' => [
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
            'created_at' => $row['created_at']
        ]
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

<?php
/**
 * POST /api/customers-create.php
 * Create a new customer
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
    $firstName = $body['firstName'] ?? null;
    $lastName = $body['lastName'] ?? null;
    $email = $body['email'] ?? null;
    $phone = $body['phone'] ?? null;
    $address = $body['address'] ?? null;
    $zipCode = $body['zipCode'] ?? null;
    $city = $body['city'] ?? null;
    $country = $body['country'] ?? null;
    $notes = $body['notes'] ?? null;
    
    // Validate required fields
    if (!$firstName || !$lastName || !$email) {
        sendJSON(['success' => false, 'error' => 'firstName, lastName, and email are required'], 400);
    }
    
    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        sendJSON(['success' => false, 'error' => 'Invalid email format'], 400);
    }
    
    $conn = getDB();
    
    // Check if customer with this email already exists
    $checkSql = "SELECT id FROM customers WHERE email = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param('s', $email);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    $checkStmt->close();
    
    if ($checkResult->num_rows > 0) {
        sendJSON(['success' => false, 'error' => 'Customer with this email already exists'], 409);
    }
    
    // Create customer
    $sql = "INSERT INTO customers (first_name, last_name, email, phone, address, zip_code, city, country, notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        sendJSON(['success' => false, 'error' => 'Query prepare failed'], 500);
    }
    
    $stmt->bind_param('sssssssss', $firstName, $lastName, $email, $phone, $address, $zipCode, $city, $country, $notes);
    
    if (!$stmt->execute()) {
        sendJSON(['success' => false, 'error' => 'Failed to create customer'], 500);
    }
    
    $customerId = $stmt->insert_id;
    $stmt->close();
    $conn->close();
    
    sendJSON([
        'success' => true,
        'message' => 'Customer created successfully',
        'data' => [
            'id' => (int) $customerId,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'email' => $email,
            'phone' => $phone,
            'address' => $address,
            'zipCode' => $zipCode,
            'city' => $city,
            'country' => $country,
            'notes' => $notes,
            'totalBookings' => 0,
            'totalSpent' => 0,
            'createdDate' => date('Y-m-d H:i:s')
        ]
    ], 201);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

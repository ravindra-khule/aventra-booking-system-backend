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
 * PUT /api/tours-update.php
 * Update an existing tour (admin only)
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
    $title = $body['title'] ?? null;
    $slug = $body['slug'] ?? null;
    $imageUrl = $body['imageUrl'] ?? null;
    $shortDescription = $body['shortDescription'] ?? null;
    $description = $body['description'] ?? null;
    $status = $body['status'] ?? null;
    $price = isset($body['price']) ? (float) $body['price'] : null;
    $depositPrice = isset($body['depositPrice']) ? (float) $body['depositPrice'] : null;
    $durationDays = isset($body['durationDays']) ? (int) $body['durationDays'] : null;
    $difficulty = $body['difficulty'] ?? null;
    $location = $body['location'] ?? null;
    $country = $body['country'] ?? null;
    $maxCapacity = isset($body['maxCapacity']) ? (int) $body['maxCapacity'] : null;
    $nextDate = $body['nextDate'] ?? null;
    
    if (!$id) {
        sendJSON(['success' => false, 'error' => 'Tour ID is required'], 400);
    }
    
    $conn = getDB();
    
    // Check if tour exists
    $checkSql = "SELECT id FROM tours WHERE id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param('i', $id);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        sendJSON(['success' => false, 'error' => 'Tour not found'], 404);
    }
    $checkStmt->close();
    
    // Build update query
    $updateFields = [];
    $types = '';
    $values = [];
    
    if ($title !== null) {
        $updateFields[] = "title = ?";
        $types .= 's';
        $values[] = $title;
    }
    
    if ($slug !== null) {
        $updateFields[] = "slug = ?";
        $types .= 's';
        $values[] = $slug;
    }
    
    if ($imageUrl !== null) {
        $updateFields[] = "image_url = ?";
        $types .= 's';
        $values[] = $imageUrl;
    }
    
    if ($shortDescription !== null) {
        $updateFields[] = "short_description = ?";
        $types .= 's';
        $values[] = $shortDescription;
    }
    
    if ($description !== null) {
        $updateFields[] = "description = ?";
        $types .= 's';
        $values[] = $description;
    }
    
    if ($status !== null) {
        $updateFields[] = "status = ?";
        $types .= 's';
        $values[] = $status;
    }
    
    if ($price !== null) {
        $updateFields[] = "price = ?";
        $types .= 'd';
        $values[] = $price;
    }
    
    if ($depositPrice !== null) {
        $updateFields[] = "deposit_price = ?";
        $types .= 'd';
        $values[] = $depositPrice;
    }
    
    if ($durationDays !== null) {
        $updateFields[] = "duration_days = ?";
        $types .= 'i';
        $values[] = $durationDays;
    }
    
    if ($difficulty !== null) {
        $updateFields[] = "difficulty = ?";
        $types .= 's';
        $values[] = $difficulty;
    }
    
    if ($location !== null) {
        $updateFields[] = "location = ?";
        $types .= 's';
        $values[] = $location;
    }
    
    if ($country !== null) {
        $updateFields[] = "country = ?";
        $types .= 's';
        $values[] = $country;
    }
    
    if ($maxCapacity !== null) {
        $updateFields[] = "max_capacity = ?";
        $types .= 'i';
        $values[] = $maxCapacity;
    }
    
    if ($nextDate !== null) {
        $updateFields[] = "next_date = ?";
        $types .= 's';
        $values[] = $nextDate;
    }
    
    if (empty($updateFields)) {
        sendJSON(['success' => false, 'error' => 'No fields to update'], 400);
    }
    
    // Build update query
    $updateSql = "UPDATE tours SET " . implode(', ', $updateFields) . " WHERE id = ?";
    
    $updateStmt = $conn->prepare($updateSql);
    
    if (!$updateStmt) {
        sendJSON(['success' => false, 'error' => 'Query prepare failed'], 500);
    }
    
    // Bind parameters
    $types .= 'i';
    $values[] = $id;
    
    $updateStmt->bind_param($types, ...$values);
    
    if (!$updateStmt->execute()) {
        sendJSON(['success' => false, 'error' => 'Failed to update tour'], 500);
    }
    
    $updateStmt->close();
    
    // Get updated tour
    $getSql = "SELECT 
        id, title, slug, image_url, short_description, description,
        status, price, deposit_price, currency, duration_days, difficulty,
        location, country, region, max_capacity, available_spots, next_date
    FROM tours WHERE id = ?";
    
    $getStmt = $conn->prepare($getSql);
    $getStmt->bind_param('i', $id);
    $getStmt->execute();
    $getResult = $getStmt->get_result();
    $updatedTour = $getResult->fetch_assoc();
    $getStmt->close();
    
    $conn->close();
    
    sendJSON([
        'success' => true,
        'message' => 'Tour updated successfully',
        'data' => [
            'id' => (string) $updatedTour['id'],
            'title' => $updatedTour['title'],
            'slug' => $updatedTour['slug'],
            'imageUrl' => $updatedTour['image_url'],
            'shortDescription' => $updatedTour['short_description'],
            'description' => $updatedTour['description'],
            'status' => $updatedTour['status'],
            'price' => (float) $updatedTour['price'],
            'depositPrice' => (float) $updatedTour['deposit_price'],
            'currency' => $updatedTour['currency'],
            'durationDays' => (int) $updatedTour['duration_days'],
            'difficulty' => $updatedTour['difficulty'],
            'location' => $updatedTour['location'],
            'country' => $updatedTour['country'],
            'maxCapacity' => (int) $updatedTour['max_capacity'],
            'availableSpots' => (int) $updatedTour['available_spots'],
            'nextDate' => $updatedTour['next_date']
        ]
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

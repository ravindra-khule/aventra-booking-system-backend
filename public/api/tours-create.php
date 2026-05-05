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
 * POST /api/tours-create.php
 * Create a new tour (admin only)
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
    $required = ['title', 'price', 'location', 'durationDays', 'maxCapacity'];
    foreach ($required as $field) {
        if (empty($body[$field])) {
            sendJSON(['success' => false, 'error' => "Missing required field: $field"], 400);
        }
    }
    
    $title = $body['title'];
    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
    // Use a more reliable placeholder service or SVG fallback
    $defaultImage = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="800" height="600"%3E%3Crect fill="%23ddd" width="800" height="600"/%3E%3Ctext x="50%" y="50%" text-anchor="middle" dy=".3em" font-size="24" font-family="sans-serif" fill="%23666"%3ETour Image%3C/text%3E%3C/svg%3E';
    $imageUrl = $body['imageUrl'] ?? $defaultImage;
    $shortDescription = $body['shortDescription'] ?? substr($body['description'] ?? '', 0, 100);
    $description = $body['description'] ?? '';
    $status = $body['status'] ?? 'active';
    $price = (float) $body['price'];
    $depositPrice = $body['depositPrice'] ?? $price * 0.2; // 20% default
    $currency = $body['currency'] ?? 'USD';
    $durationDays = (int) $body['durationDays'];
    $difficulty = $body['difficulty'] ?? 'moderate';
    $location = $body['location'];
    $country = $body['country'] ?? '';
    $region = $body['region'] ?? '';
    $maxCapacity = (int) $body['maxCapacity'];
    $availableSpots = $maxCapacity; // New tour has all spots available
    $nextDate = $body['nextDate'] ?? date('Y-m-d', strtotime('+30 days'));
    
    $conn = getDB();
    
    // Check if title already exists
    $checkSql = "SELECT id FROM tours WHERE title = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param('s', $title);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows > 0) {
        sendJSON(['success' => false, 'error' => 'Tour with this title already exists'], 400);
    }
    $checkStmt->close();
    
    // Insert new tour
    $insertSql = "INSERT INTO tours (
        title, slug, image_url, short_description, description, 
        status, price, deposit_price, currency, 
        duration_days, difficulty, location, country, region,
        max_capacity, available_spots, next_date
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $insertStmt = $conn->prepare($insertSql);
    
    if (!$insertStmt) {
        sendJSON(['success' => false, 'error' => 'Query prepare failed'], 500);
    }
    
    $insertStmt->bind_param(
        'ssssssddsissssiis',
        $title, $slug, $imageUrl, $shortDescription, $description,
        $status, $price, $depositPrice, $currency,
        $durationDays, $difficulty, $location, $country, $region,
        $maxCapacity, $availableSpots, $nextDate
    );
    
    if (!$insertStmt->execute()) {
        sendJSON(['success' => false, 'error' => 'Failed to create tour'], 500);
    }
    
    $tourId = $conn->insert_id;
    $insertStmt->close();
    
    // Fetch the created tour
    $getSql = "SELECT 
        id, title, slug, image_url, short_description, description,
        status, price, deposit_price, currency, duration_days, difficulty,
        location, country, region, max_capacity, available_spots, next_date
    FROM tours WHERE id = ?";
    
    $getStmt = $conn->prepare($getSql);
    $getStmt->bind_param('i', $tourId);
    $getStmt->execute();
    $getResult = $getStmt->get_result();
    $tour = $getResult->fetch_assoc();
    $getStmt->close();
    
    $conn->close();
    
    sendJSON([
        'success' => true,
        'message' => 'Tour created successfully',
        'data' => [
            'id' => (string) $tour['id'],
            'title' => $tour['title'],
            'slug' => $tour['slug'],
            'imageUrl' => $tour['image_url'],
            'shortDescription' => $tour['short_description'],
            'description' => $tour['description'],
            'status' => $tour['status'],
            'price' => (float) $tour['price'],
            'depositPrice' => (float) $tour['deposit_price'],
            'currency' => $tour['currency'],
            'durationDays' => (int) $tour['duration_days'],
            'difficulty' => $tour['difficulty'],
            'location' => $tour['location'],
            'country' => $tour['country'],
            'region' => $tour['region'],
            'maxCapacity' => (int) $tour['max_capacity'],
            'availableSpots' => (int) $tour['available_spots'],
            'nextDate' => $tour['next_date']
        ]
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

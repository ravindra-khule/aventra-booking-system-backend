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
 * GET /api/tours/{id}.php
 * Fetch a specific tour by ID with all details
 */

require_once __DIR__ . '/../../config.php';

try {
    // Only allow GET requests
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    // Get tour ID from query parameter
    $tourId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    
    if (!$tourId) {
        sendJSON(['success' => false, 'error' => 'Tour ID is required'], 400);
    }
    
    $conn = getDB();
    
    // Fetch the specific tour
    $sql = "SELECT 
                id,
                title,
                slug,
                image_url,
                short_description,
                description,
                location,
                country,
                region,
                next_date,
                duration_days,
                price,
                deposit_price,
                currency,
                difficulty,
                available_spots,
                max_capacity,
                status,
                created_at,
                updated_at
            FROM tours 
            WHERE id = ? AND status = 'active'
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        sendJSON(['success' => false, 'error' => 'Query prepare failed: ' . $conn->error], 500);
    }
    
    $stmt->bind_param('i', $tourId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendJSON(['success' => false, 'error' => 'Tour not found'], 404);
    }
    
    $tour = $result->fetch_assoc();
    $stmt->close();
    
    // Format response to match frontend expectations
    $response = [
        'id' => (string) $tour['id'],
        'title' => $tour['title'],
        'slug' => $tour['slug'],
        'imageUrl' => $tour['image_url'],
        'shortDescription' => $tour['short_description'],
        'description' => $tour['description'],
        'location' => $tour['location'],
        'country' => $tour['country'],
        'region' => $tour['region'] ?: $tour['location'],
        'nextDate' => $tour['next_date'],
        'durationDays' => (int) $tour['duration_days'],
        'price' => (float) $tour['price'],
        'depositPrice' => (float) $tour['deposit_price'],
        'currency' => $tour['currency'],
        'difficulty' => $tour['difficulty'],
        'availableSpots' => (int) $tour['available_spots'],
        'maxCapacity' => (int) $tour['max_capacity'],
        'status' => $tour['status'],
        'createdAt' => $tour['created_at'],
        'updatedAt' => $tour['updated_at']
    ];
    
    $conn->close();
    
    sendJSON([
        'success' => true,
        'data' => $response
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

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
 * GET /api/tours-admin-list.php
 * Fetch all tours for admin dashboard (no filters)
 */

require_once __DIR__ . '/../../config.php';

try {
    // Only allow GET requests
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    $conn = getDB();
    
    // Fetch ALL tours without filters (for admin)
    $sql = "SELECT 
                id,
                title,
                slug,
                image_url,
                short_description,
                description,
                location,
                country,
                next_date,
                duration_days,
                price,
                currency,
                difficulty,
                available_spots,
                max_capacity,
                status
            FROM tours 
            ORDER BY id DESC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        sendJSON(['success' => false, 'error' => 'Query failed: ' . $conn->error], 500);
    }
    
    $tours = [];
    while ($row = $result->fetch_assoc()) {
        $tours[] = [
            'id' => (string) $row['id'],
            'title' => $row['title'],
            'slug' => $row['slug'],
            'imageUrl' => $row['image_url'],
            'shortDescription' => $row['short_description'],
            'description' => $row['description'],
            'location' => $row['location'],
            'country' => $row['country'],
            'nextDate' => $row['next_date'],
            'durationDays' => (int) $row['duration_days'],
            'price' => (float) $row['price'],
            'currency' => $row['currency'],
            'difficulty' => $row['difficulty'],
            'availableSpots' => (int) $row['available_spots'],
            'maxCapacity' => (int) $row['max_capacity'],
            'status' => $row['status']
        ];
    }
    
    $conn->close();
    
    sendJSON([
        'success' => true,
        'data' => $tours
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

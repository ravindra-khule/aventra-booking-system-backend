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
    
    // Fetch ALL non-deleted tours with booking stats (for admin)
    $sql = "SELECT 
                t.id,
                t.title,
                t.slug,
                t.image_url,
                t.short_description,
                t.description,
                t.location,
                t.country,
                t.region,
                t.next_date,
                t.duration_days,
                t.price,
                t.deposit_price,
                t.currency,
                t.difficulty,
                t.available_spots,
                t.max_capacity,
                t.status,
                COUNT(b.id) AS total_bookings,
                COALESCE(SUM(CASE WHEN b.status IN ('confirmed', 'completed') THEN b.total_price END), 0) AS revenue
            FROM tours t
            LEFT JOIN bookings b ON b.tour_id = t.id 
                AND b.deleted_at IS NULL 
                AND b.status != 'cancelled'
            WHERE t.deleted_at IS NULL
            GROUP BY t.id
            ORDER BY t.id DESC";
    
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
            'region' => $row['region'],
            'nextDate' => $row['next_date'],
            'durationDays' => (int) $row['duration_days'],
            'price' => (float) $row['price'],
            'depositPrice' => (float) $row['deposit_price'],
            'currency' => $row['currency'],
            'difficulty' => $row['difficulty'],
            'availableSpots' => (int) $row['available_spots'],
            'maxCapacity' => (int) $row['max_capacity'],
            'status' => $row['status'],
            'totalBookings' => (int) $row['total_bookings'],
            'revenue' => (float) $row['revenue']
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

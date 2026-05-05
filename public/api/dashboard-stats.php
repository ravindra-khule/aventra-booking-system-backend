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
 * GET /api/dashboard-stats.php
 * Get dashboard statistics for admin panel
 */

require_once __DIR__ . '/../../config.php';

try {
    // Only allow GET requests
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    $conn = getDB();
    
    // Get total tours
    $toursSql = "SELECT COUNT(*) as count FROM tours WHERE status = 'active'";
    $toursResult = $conn->query($toursSql);
    $toursData = $toursResult->fetch_assoc();
    $totalTours = (int) $toursData['count'];
    
    // Get total bookings
    $bookingsSql = "SELECT COUNT(*) as count FROM bookings WHERE deleted_at IS NULL";
    $bookingsResult = $conn->query($bookingsSql);
    $bookingsData = $bookingsResult->fetch_assoc();
    $totalBookings = (int) $bookingsData['count'];
    
    // Get total users
    $usersSql = "SELECT COUNT(*) as count FROM users WHERE status = 'active'";
    $usersResult = $conn->query($usersSql);
    $usersData = $usersResult->fetch_assoc();
    $totalUsers = (int) $usersData['count'];
    
    // Get total revenue (paid bookings)
    $revenueSql = "SELECT SUM(total_price) as revenue FROM bookings 
                   WHERE payment_status = 'paid' AND deleted_at IS NULL";
    $revenueResult = $conn->query($revenueSql);
    $revenueData = $revenueResult->fetch_assoc();
    $totalRevenue = (float) ($revenueData['revenue'] ?? 0);
    
    // Get pending bookings (awaiting payment)
    $pendingSql = "SELECT COUNT(*) as count FROM bookings 
                   WHERE status = 'pending' AND deleted_at IS NULL";
    $pendingResult = $conn->query($pendingSql);
    $pendingData = $pendingResult->fetch_assoc();
    $pendingBookings = (int) $pendingData['count'];
    
    // Get confirmed bookings
    $confirmedSql = "SELECT COUNT(*) as count FROM bookings 
                     WHERE status = 'confirmed' AND deleted_at IS NULL";
    $confirmedResult = $conn->query($confirmedSql);
    $confirmedData = $confirmedResult->fetch_assoc();
    $confirmedBookings = (int) $confirmedData['count'];
    
    // Get bookings this month
    $monthSql = "SELECT COUNT(*) as count FROM bookings 
                 WHERE MONTH(booking_date) = MONTH(NOW()) 
                 AND YEAR(booking_date) = YEAR(NOW())
                 AND deleted_at IS NULL";
    $monthResult = $conn->query($monthSql);
    $monthData = $monthResult->fetch_assoc();
    $bookingsThisMonth = (int) $monthData['count'];
    
    // Get upcoming tours (departure in future)
    $upcomingSql = "SELECT 
                        id, title, next_date, max_capacity, available_spots
                    FROM tours 
                    WHERE status = 'active' 
                    AND next_date > NOW()
                    ORDER BY next_date ASC
                    LIMIT 5";
    $upcomingResult = $conn->query($upcomingSql);
    $upcomingTours = [];
    
    while ($tour = $upcomingResult->fetch_assoc()) {
        $upcomingTours[] = [
            'id' => (string) $tour['id'],
            'title' => $tour['title'],
            'departureDate' => $tour['next_date'],
            'capacity' => (int) $tour['max_capacity'],
            'spotsAvailable' => (int) $tour['available_spots'],
            'spotsBooked' => (int) ($tour['max_capacity'] - $tour['available_spots'])
        ];
    }
    
    // Get recent bookings
    $recentSql = "SELECT 
                        b.id, b.booking_reference, b.customer_name, 
                        b.total_price, b.status, b.booking_date, t.title
                    FROM bookings b
                    JOIN tours t ON b.tour_id = t.id
                    WHERE b.deleted_at IS NULL
                    ORDER BY b.booking_date DESC
                    LIMIT 10";
    $recentResult = $conn->query($recentSql);
    $recentBookings = [];
    
    while ($booking = $recentResult->fetch_assoc()) {
        $recentBookings[] = [
            'id' => (string) $booking['id'],
            'reference' => $booking['booking_reference'],
            'customerName' => $booking['customer_name'],
            'tourTitle' => $booking['title'],
            'amount' => (float) $booking['total_price'],
            'status' => $booking['status'],
            'bookingDate' => $booking['booking_date']
        ];
    }
    
    $conn->close();
    
    sendJSON([
        'success' => true,
        'data' => [
            'summary' => [
                'totalTours' => $totalTours,
                'totalBookings' => $totalBookings,
                'totalUsers' => $totalUsers,
                'totalRevenue' => $totalRevenue,
                'pendingBookings' => $pendingBookings,
                'confirmedBookings' => $confirmedBookings,
                'bookingsThisMonth' => $bookingsThisMonth
            ],
            'upcomingTours' => $upcomingTours,
            'recentBookings' => $recentBookings
        ]
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

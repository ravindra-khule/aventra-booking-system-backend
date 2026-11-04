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
    
    // Get total customers
    $customersSql = "SELECT COUNT(*) as count FROM customers WHERE deleted_at IS NULL";
    $customersResult = $conn->query($customersSql);
    $customersData = $customersResult->fetch_assoc();
    $totalCustomers = (int) $customersData['count'];
    
    // Get new customers this month and last month
    $newCustSql = "SELECT 
        SUM(CASE WHEN MONTH(created_at) = MONTH(NOW()) AND YEAR(created_at) = YEAR(NOW()) THEN 1 ELSE 0 END) as this_month,
        SUM(CASE WHEN MONTH(created_at) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH)) 
                 AND YEAR(created_at) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH)) THEN 1 ELSE 0 END) as last_month
        FROM customers WHERE deleted_at IS NULL";
    $newCustResult = $conn->query($newCustSql);
    $newCustData = $newCustResult->fetch_assoc();
    $newCustomersThisMonth = (int) ($newCustData['this_month'] ?? 0);
    $newCustomersLastMonth = (int) ($newCustData['last_month'] ?? 0);
    
    // Get total revenue (paid bookings)
    $revenueSql = "SELECT SUM(total_price) as revenue FROM bookings 
                   WHERE payment_status = 'paid' AND deleted_at IS NULL";
    $revenueResult = $conn->query($revenueSql);
    $revenueData = $revenueResult->fetch_assoc();
    $totalRevenue = (float) ($revenueData['revenue'] ?? 0);
    
    // Revenue this month and last month (paid bookings)
    $monthRevSql = "SELECT 
        SUM(CASE WHEN MONTH(booking_date) = MONTH(NOW()) AND YEAR(booking_date) = YEAR(NOW()) THEN total_price ELSE 0 END) as this_month,
        SUM(CASE WHEN MONTH(booking_date) = MONTH(DATE_SUB(NOW(), INTERVAL 1 MONTH)) 
                 AND YEAR(booking_date) = YEAR(DATE_SUB(NOW(), INTERVAL 1 MONTH)) THEN total_price ELSE 0 END) as last_month
        FROM bookings WHERE payment_status = 'paid' AND deleted_at IS NULL";
    $monthRevResult = $conn->query($monthRevSql);
    $monthRevData = $monthRevResult->fetch_assoc();
    $monthlyRevenue = (float) ($monthRevData['this_month'] ?? 0);
    $lastMonthRevenue = (float) ($monthRevData['last_month'] ?? 0);
    $revenueGrowth = $lastMonthRevenue > 0 
        ? round((($monthlyRevenue - $lastMonthRevenue) / $lastMonthRevenue) * 100, 1) 
        : ($monthlyRevenue > 0 ? 100.0 : 0.0);
    
    // Booking counts per status
    $statusSql = "SELECT status, COUNT(*) as count FROM bookings 
                  WHERE deleted_at IS NULL GROUP BY status";
    $statusResult = $conn->query($statusSql);
    $statusCounts = [
        'pending' => 0,
        'confirmed' => 0,
        'completed' => 0,
        'cancelled' => 0
    ];
    while ($row = $statusResult->fetch_assoc()) {
        $status = $row['status'] === 'refunded' ? 'cancelled' : $row['status'];
        if (isset($statusCounts[$status])) {
            $statusCounts[$status] += (int) $row['count'];
        }
    }
    $pendingBookings = $statusCounts['pending'];
    $confirmedBookings = $statusCounts['confirmed'];
    $completedBookings = $statusCounts['completed'];
    $cancelledBookings = $statusCounts['cancelled'];
    
    // Get bookings this month
    $monthSql = "SELECT COUNT(*) as count FROM bookings 
                 WHERE MONTH(booking_date) = MONTH(NOW()) 
                 AND YEAR(booking_date) = YEAR(NOW())
                 AND deleted_at IS NULL";
    $monthResult = $conn->query($monthSql);
    $monthData = $monthResult->fetch_assoc();
    $bookingsThisMonth = (int) $monthData['count'];
    
    // Pending payments (bookings not cancelled awaiting full payment) and outstanding balance
    $pendingPaySql = "SELECT COUNT(*) as count, COALESCE(SUM(balance_due), 0) as balance 
                      FROM bookings 
                      WHERE payment_status IN ('pending', 'partial') 
                      AND status NOT IN ('cancelled', 'refunded')
                      AND deleted_at IS NULL";
    $pendingPayResult = $conn->query($pendingPaySql);
    $pendingPayData = $pendingPayResult->fetch_assoc();
    $pendingPayments = (int) $pendingPayData['count'];
    $unpaidBalance = (float) $pendingPayData['balance'];
    
    // Average booking value (non-cancelled bookings)
    $avgSql = "SELECT AVG(total_price) as avg_price, COUNT(*) as count FROM bookings 
               WHERE status NOT IN ('cancelled', 'refunded') AND deleted_at IS NULL";
    $avgResult = $conn->query($avgSql);
    $avgData = $avgResult->fetch_assoc();
    $averageBookingValue = (float) ($avgData['avg_price'] ?? 0);
    $activeBookingCount = (int) ($avgData['count'] ?? 0);
    
    // Payment success rate: paid / non-cancelled bookings
    $paidSql = "SELECT COUNT(*) as count FROM bookings 
                WHERE payment_status = 'paid' AND status NOT IN ('cancelled', 'refunded') 
                AND deleted_at IS NULL";
    $paidResult = $conn->query($paidSql);
    $paidData = $paidResult->fetch_assoc();
    $paidBookings = (int) $paidData['count'];
    $paymentSuccessRate = $activeBookingCount > 0 
        ? round(($paidBookings / $activeBookingCount) * 100, 1) 
        : 0.0;
    
    // Cancellation rate: cancelled / all bookings
    $cancellationRate = $totalBookings > 0 
        ? round(($cancelledBookings / $totalBookings) * 100, 1) 
        : 0.0;
    
    // Occupancy rate across upcoming active tours
    $occupSql = "SELECT 
                    SUM(max_capacity) as capacity, 
                    SUM(max_capacity - available_spots) as booked,
                    SUM(CASE WHEN available_spots <= max_capacity * 0.2 THEN 1 ELSE 0 END) as low_avail
                 FROM tours 
                 WHERE status = 'active' AND next_date >= CURDATE() AND deleted_at IS NULL";
    $occupResult = $conn->query($occupSql);
    $occupData = $occupResult->fetch_assoc();
    $totalCapacity = (int) ($occupData['capacity'] ?? 0);
    $totalBooked = (int) ($occupData['booked'] ?? 0);
    $occupancyRate = $totalCapacity > 0 
        ? round(($totalBooked / $totalCapacity) * 100, 1) 
        : 0.0;
    $lowAvailabilityTours = (int) ($occupData['low_avail'] ?? 0);
    
    // Repeat customer rate: customers with 2+ bookings / customers with 1+ booking
    $repeatSql = "SELECT 
        SUM(CASE WHEN cnt >= 2 THEN 1 ELSE 0 END) as repeaters,
        COUNT(*) as total
        FROM (
            SELECT customer_email, COUNT(*) as cnt 
            FROM bookings 
            WHERE deleted_at IS NULL AND status NOT IN ('cancelled', 'refunded')
            GROUP BY customer_email
        ) as email_counts";
    $repeatResult = $conn->query($repeatSql);
    $repeatData = $repeatResult->fetch_assoc();
    $bookingCustomers = (int) ($repeatData['total'] ?? 0);
    $repeatCustomerRate = $bookingCustomers > 0 
        ? round(((int) $repeatData['repeaters'] / $bookingCustomers) * 100, 1) 
        : 0.0;
    
    // Average customer lifetime value
    $averageCustomerLTV = $bookingCustomers > 0 
        ? round($totalRevenue / $bookingCustomers, 0) 
        : 0.0;
    
    // Revenue and bookings per month for the last 6 months
    $revMonthSql = "SELECT 
                        DATE_FORMAT(booking_date, '%Y-%m') as ym,
                        SUM(CASE WHEN payment_status = 'paid' THEN total_price ELSE 0 END) as revenue,
                        COUNT(*) as bookings
                    FROM bookings 
                    WHERE deleted_at IS NULL
                    AND booking_date >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 5 MONTH), '%Y-%m-01')
                    GROUP BY ym";
    $revMonthResult = $conn->query($revMonthSql);
    $revMonthMap = [];
    while ($row = $revMonthResult->fetch_assoc()) {
        $revMonthMap[$row['ym']] = [
            'revenue' => (float) $row['revenue'],
            'bookings' => (int) $row['bookings']
        ];
    }
    $revenueByMonth = [];
    for ($i = 5; $i >= 0; $i--) {
        $ts = strtotime("first day of -$i months");
        $ym = date('Y-m', $ts);
        $revenueByMonth[] = [
            'month' => date('M', $ts),
            'revenue' => $revMonthMap[$ym]['revenue'] ?? 0,
            'bookings' => $revMonthMap[$ym]['bookings'] ?? 0
        ];
    }
    
    // New customers per month for the last 6 months
    $custMonthSql = "SELECT 
                        DATE_FORMAT(created_at, '%Y-%m') as ym,
                        COUNT(*) as count
                    FROM customers 
                    WHERE deleted_at IS NULL
                    AND created_at >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 5 MONTH), '%Y-%m-01')
                    GROUP BY ym";
    $custMonthResult = $conn->query($custMonthSql);
    $custMonthMap = [];
    while ($row = $custMonthResult->fetch_assoc()) {
        $custMonthMap[$row['ym']] = (int) $row['count'];
    }
    $customersByMonth = [];
    for ($i = 5; $i >= 0; $i--) {
        $ts = strtotime("first day of -$i months");
        $ym = date('Y-m', $ts);
        $customersByMonth[] = [
            'month' => date('M', $ts),
            'count' => $custMonthMap[$ym] ?? 0
        ];
    }
    
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
                'totalCustomers' => $totalCustomers,
                'totalRevenue' => $totalRevenue,
                'monthlyRevenue' => $monthlyRevenue,
                'lastMonthRevenue' => $lastMonthRevenue,
                'revenueGrowth' => $revenueGrowth,
                'pendingBookings' => $pendingBookings,
                'confirmedBookings' => $confirmedBookings,
                'completedBookings' => $completedBookings,
                'cancelledBookings' => $cancelledBookings,
                'bookingsThisMonth' => $bookingsThisMonth,
                'newCustomersThisMonth' => $newCustomersThisMonth,
                'newCustomersLastMonth' => $newCustomersLastMonth,
                'pendingPayments' => $pendingPayments,
                'unpaidBalance' => $unpaidBalance,
                'averageBookingValue' => round($averageBookingValue, 0),
                'occupancyRate' => $occupancyRate,
                'paymentSuccessRate' => $paymentSuccessRate,
                'cancellationRate' => $cancellationRate,
                'repeatCustomerRate' => $repeatCustomerRate,
                'averageCustomerLTV' => $averageCustomerLTV,
                'lowAvailabilityTours' => $lowAvailabilityTours
            ],
            'statusBreakdown' => $statusCounts,
            'revenueByMonth' => $revenueByMonth,
            'customersByMonth' => $customersByMonth,
            'upcomingTours' => $upcomingTours,
            'recentBookings' => $recentBookings,
            'generatedAt' => date('c')
        ]
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

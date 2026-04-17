<?php
/**
 * POST /api/promo-codes-validate.php
 * Validate and apply a promo code to a booking
 * Returns discount amount if valid
 */

require_once __DIR__ . '/../../config.php';

// Additional CORS headers for this endpoint
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    $body = json_decode(file_get_contents('php://input'), true);
    
    if (!$body) {
        sendJSON(['success' => false, 'error' => 'Invalid JSON body'], 400);
    }
    
    $code = $body['code'] ?? null;
    $bookingAmount = $body['bookingAmount'] ?? null;
    
    if (!$code || $bookingAmount === null) {
        sendJSON(['success' => false, 'error' => 'Code and booking amount are required'], 400);
    }
    
    $conn = getDB();
    
    // Fetch promo code
    $sql = "SELECT 
                id,
                code,
                description,
                discount_type,
                discount_value,
                max_uses,
                current_uses,
                valid_from,
                valid_until,
                min_booking_amount,
                max_discount_amount,
                is_active
            FROM promo_codes 
            WHERE code = ? 
            AND (deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00')
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        sendJSON(['success' => false, 'error' => 'Query prepare failed'], 500);
    }
    
    $stmt->bind_param('s', $code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendJSON(['success' => false, 'error' => 'Promo code not found'], 404);
    }
    
    $promo = $result->fetch_assoc();
    $stmt->close();
    
    // Validation checks
    if (!$promo['is_active']) {
        sendJSON(['success' => false, 'error' => 'Promo code is inactive'], 400);
    }
    
    // Check validity dates
    $today = date('Y-m-d');
    if ($promo['valid_from'] > $today) {
        sendJSON(['success' => false, 'error' => 'Promo code is not yet valid'], 400);
    }
    
    if ($promo['valid_until'] < $today) {
        sendJSON(['success' => false, 'error' => 'Promo code has expired'], 400);
    }
    
    // Check max uses
    if ($promo['max_uses'] !== null && $promo['current_uses'] >= $promo['max_uses']) {
        sendJSON(['success' => false, 'error' => 'Promo code has reached maximum uses'], 400);
    }
    
    // Check minimum booking amount
    if ($bookingAmount < $promo['min_booking_amount']) {
        sendJSON([
            'success' => false,
            'error' => 'Booking amount does not meet minimum requirement',
            'minRequired' => (float) $promo['min_booking_amount']
        ], 400);
    }
    
    // Calculate discount
    $discount = 0;
    
    if ($promo['discount_type'] === 'percentage') {
        $discount = ($bookingAmount * $promo['discount_value']) / 100;
    } else {
        // Fixed discount
        $discount = (float) $promo['discount_value'];
    }
    
    // Apply max discount cap if set
    if ($promo['max_discount_amount'] !== null && $discount > $promo['max_discount_amount']) {
        $discount = (float) $promo['max_discount_amount'];
    }
    
    // Ensure discount doesn't exceed booking amount
    if ($discount > $bookingAmount) {
        $discount = $bookingAmount;
    }
    
    $conn->close();
    
    sendJSON([
        'success' => true,
        'data' => [
            'promoCodeId' => (int) $promo['id'],
            'code' => $promo['code'],
            'description' => $promo['description'],
            'discountType' => $promo['discount_type'],
            'discountValue' => (float) $promo['discount_value'],
            'discountAmount' => round($discount, 2),
            'bookingAmount' => (float) $bookingAmount,
            'finalAmount' => round($bookingAmount - $discount, 2),
            'maxDiscountAmount' => $promo['max_discount_amount'] ? (float) $promo['max_discount_amount'] : null
        ]
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

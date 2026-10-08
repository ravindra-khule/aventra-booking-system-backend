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
 * POST /api/bookings.php
 * Create a new tour booking
 */

require_once __DIR__ . '/../../config.php';
// Email notifications are best-effort: a missing notifier must never break bookings.
$__notifierPath = __DIR__ . '/../../lib/BookingNotifier.php';
if (is_file($__notifierPath)) {
    require_once $__notifierPath;
}

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
    $userId = $body['userId'] ?? null;
    $tourId = $body['tourId'] ?? null;
    $numberOfPeople = $body['numberOfPeople'] ?? null;
    $customerName = $body['customerName'] ?? null;
    $customerEmail = $body['customerEmail'] ?? null;
    $customerPhone = $body['customerPhone'] ?? null;
    
    if (!$userId || !$tourId || !$numberOfPeople || !$customerName || !$customerEmail) {
        sendJSON(['success' => false, 'error' => 'Missing required fields'], 400);
    }
    
    if ($numberOfPeople < 1) {
        sendJSON(['success' => false, 'error' => 'Number of people must be at least 1'], 400);
    }
    
    $conn = getDB();
    
    try {
        // Get tour details (must be bookable: active and not deleted)
        $tourSql = "SELECT id, price, deposit_price, available_spots 
                    FROM tours 
                    WHERE id = ? AND status = 'active' AND deleted_at IS NULL";
        $tourStmt = $conn->prepare($tourSql);
        
        if (!$tourStmt) {
            throw new Exception('Tour query prepare failed');
        }
        
        $tourStmt->bind_param('i', $tourId);
        $tourStmt->execute();
        $tourResult = $tourStmt->get_result();
        
        if ($tourResult->num_rows === 0) {
            $tourStmt->close();
            sendJSON(['success' => false, 'error' => 'Tour not found or not available for booking'], 404);
        }
        
        $tour = $tourResult->fetch_assoc();
        $tourStmt->close();
        
        // Check availability
        if ($tour['available_spots'] < $numberOfPeople) {
            sendJSON(['success' => false, 'error' => 'Not enough available spots'], 400);
        }
        
        // Calculate add-ons total
        $addOnsTotal = 0;
        foreach ($addOns as $addOn) {
            $qty = max(1, (int) ($addOn['quantity'] ?? 1));
            $unitPrice = (float) ($addOn['unitPrice'] ?? 0);
            $perPerson = !empty($addOn['perPerson']);
            $addOnsTotal += $perPerson ? $unitPrice * $qty * $numberOfPeople : $unitPrice * $qty;
        }
        
        // Base price + add-ons
        $basePrice = (float) $tour['price'] * $numberOfPeople;
        $subtotal = $basePrice + $addOnsTotal;
        $discount = 0;
        $promoCodeId = null;
        
        // Validate and apply promo code server-side
        if ($promoCode) {
            $promoSql = "SELECT id, discount_type, discount_value, max_uses, current_uses,
                                valid_from, valid_until, min_booking_amount, max_discount_amount, is_active
                         FROM promo_codes
                         WHERE code = ? AND deleted_at IS NULL
                         LIMIT 1";
            $promoStmt = $conn->prepare($promoSql);
            $promoStmt->bind_param('s', $promoCode);
            $promoStmt->execute();
            $promoResult = $promoStmt->get_result();
            
            if ($promoResult->num_rows === 0) {
                $promoStmt->close();
                sendJSON(['success' => false, 'error' => 'Promo code not found'], 400);
            }
            
            $promo = $promoResult->fetch_assoc();
            $promoStmt->close();
            
            $today = date('Y-m-d');
            if (!$promo['is_active']) {
                sendJSON(['success' => false, 'error' => 'Promo code is inactive'], 400);
            }
            if ($promo['valid_from'] > $today || $promo['valid_until'] < $today) {
                sendJSON(['success' => false, 'error' => 'Promo code is not valid at this time'], 400);
            }
            if ($promo['max_uses'] !== null && $promo['current_uses'] >= $promo['max_uses']) {
                sendJSON(['success' => false, 'error' => 'Promo code has reached maximum uses'], 400);
            }
            if ($subtotal < $promo['min_booking_amount']) {
                sendJSON(['success' => false, 'error' => 'Booking amount does not meet promo code minimum'], 400);
            }
            
            if ($promo['discount_type'] === 'percentage') {
                $discount = ($subtotal * (float) $promo['discount_value']) / 100;
            } else {
                $discount = (float) $promo['discount_value'];
            }
            
            if ($promo['max_discount_amount'] !== null && $discount > $promo['max_discount_amount']) {
                $discount = (float) $promo['max_discount_amount'];
            }
            if ($discount > $subtotal) {
                $discount = $subtotal;
            }
            
            $promoCodeId = (int) $promo['id'];
        }
        
        // Final price calculation
        $totalPrice = $subtotal - $discount;
        
        if ($paymentType === 'full') {
            // Customer paid the entire booking amount
            $depositPaid = $totalPrice;
            $balanceDue = 0.0;
            $paymentStatus = 'paid';
        } else {
            // Customer paid only the deposit
            $depositPerPerson = (float) ($tour['deposit_price'] ?: $tour['price'] * 0.2);
            $discountRatio = $subtotal > 0 ? ($totalPrice / $subtotal) : 1;
            $depositPaid = round($depositPerPerson * $numberOfPeople * $discountRatio, 2);
            $balanceDue = $totalPrice - $depositPaid;
            $paymentStatus = 'partial';
        }
        
        // Generate unique reference number
        $refStmt = $conn->prepare("SELECT id FROM bookings WHERE booking_reference = ?");
        do {
            $bookingReference = 'BK' . date('YmdHis') . rand(1000, 9999);
            $refStmt->bind_param('s', $bookingReference);
            $refStmt->execute();
            $exists = $refStmt->get_result()->num_rows > 0;
        } while ($exists);
        $refStmt->close();
        
        // Find or create customer by email
        $customerId = null;
        $custStmt = $conn->prepare("SELECT id FROM customers WHERE email = ? AND deleted_at IS NULL LIMIT 1");
        $custStmt->bind_param('s', $customerEmail);
        $custStmt->execute();
        $custResult = $custStmt->get_result();
        
        if ($custRow = $custResult->fetch_assoc()) {
            $customerId = (int) $custRow['id'];
        }
        $custStmt->close();
        
        if (!$customerId) {
            $nameParts = preg_split('/\s+/', $customerName, 2);
            $firstName = $nameParts[0];
            $lastName = $nameParts[1] ?? '';
            $insCust = $conn->prepare("INSERT INTO customers (first_name, last_name, email, phone) VALUES (?, ?, ?, ?)");
            $insCust->bind_param('ssss', $firstName, $lastName, $customerEmail, $customerPhone);
            if (!$insCust->execute()) {
                $insCust->close();
                throw new Exception('Failed to create customer record');
            }
            $customerId = $conn->insert_id;
            $insCust->close();
        }
        
        // Create booking
        $sql = "INSERT INTO bookings (
            user_id, customer_id, tour_id, booking_reference, number_of_people, 
            total_price, deposit_paid, balance_due, 
            customer_name, customer_email, customer_phone, 
            special_requirements, departure_date, status, payment_status
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)";
        
        $stmt = $conn->prepare($sql);
        
        if (!$stmt) {
            throw new Exception('Query prepare failed');
        }
        
        $stmt->bind_param('iiisidddssssss', $userId, $customerId, $tourId, $bookingReference, $numberOfPeople, 
                          $totalPrice, $depositPaid, $balanceDue, $customerName, $customerEmail, $customerPhone,
                          $specialRequirements, $departureDate, $paymentStatus);
        
        if (!$stmt->execute()) {
            $stmt->close();
            throw new Exception('Failed to create booking');
        }
        
        $bookingId = $stmt->insert_id;
        $stmt->close();
        
        // Update tour available spots (atomic guard against overbooking)
        $updateSql = "UPDATE tours SET available_spots = available_spots - ? WHERE id = ? AND available_spots >= ?";
        $updateStmt = $conn->prepare($updateSql);
        $updateStmt->bind_param('iii', $numberOfPeople, $tourId, $numberOfPeople);
        $updateStmt->execute();
        
        if ($updateStmt->affected_rows === 0) {
            $updateStmt->close();
            throw new Exception('Not enough available spots');
        }
        $updateStmt->close();
        
        // Update customer's last booking date
        $custUpd = $conn->prepare("UPDATE customers SET last_booking_date = NOW() WHERE id = ?");
        $custUpd->bind_param('i', $customerId);
        $custUpd->execute();
        $custUpd->close();
        
        // Record promo code usage
        if ($promoCodeId) {
            $usageStmt = $conn->prepare("INSERT INTO promo_code_usages (promo_code_id, booking_id, user_id, discount_applied) VALUES (?, ?, ?, ?)");
            $usageStmt->bind_param('iiid', $promoCodeId, $bookingId, $userId, $discount);
            $usageStmt->execute();
            $usageStmt->close();
            
            $promoUpd = $conn->prepare("UPDATE promo_codes SET current_uses = current_uses + 1 WHERE id = ?");
            $promoUpd->bind_param('i', $promoCodeId);
            $promoUpd->execute();
            $promoUpd->close();
        }
        
        // Record booking items (travelers + add-ons)
        $itemStmt = $conn->prepare("INSERT INTO booking_items (booking_id, item_type, item_description, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?)");
        $perPersonPrice = $numberOfPeople > 0 ? round($totalPrice / $numberOfPeople, 2) : 0;
        
        foreach ($travelers as $traveler) {
            $tName = trim(($traveler['firstName'] ?? '') . ' ' . ($traveler['lastName'] ?? ''));
            if ($tName === '') continue;
            $itemType = 'participant';
            $qty = 1;
            $itemStmt->bind_param('issidd', $bookingId, $itemType, $tName, $qty, $perPersonPrice, $perPersonPrice);
            $itemStmt->execute();
        }
        
        foreach ($addOns as $addOn) {
            $itemType = 'addon';
            $aName = (string) ($addOn['name'] ?? 'Add-on');
            $qty = max(1, (int) ($addOn['quantity'] ?? 1));
            $unitPrice = (float) ($addOn['unitPrice'] ?? 0);
            $perPerson = !empty($addOn['perPerson']);
            $itemSubtotal = $perPerson ? $unitPrice * $qty * $numberOfPeople : $unitPrice * $qty;
            $itemStmt->bind_param('issidd', $bookingId, $itemType, $aName, $qty, $unitPrice, $itemSubtotal);
            $itemStmt->execute();
        }
        $itemStmt->close();
        
        $conn->commit();
        
        // Send "booking created" notifications + schedule reminders (non-fatal)
        $notifySummary = null;
        try {
            if (function_exists('notifyBookingEvent')) {
                $notifySummary = notifyBookingEvent($conn, 'booking_created', (int) $bookingId, [
                    'promoCode' => $promoCode,
                ]);
                scheduleBookingReminders($conn, (int) $bookingId);
            }
        } catch (Throwable $notifyError) {
            debugLog('Booking Notify Error (create)', ['error' => $notifyError->getMessage()]);
        }
        
        $conn->close();
        
        sendJSON([
            'success' => true,
            'message' => 'Booking created successfully',
            'data' => [
                'id' => (string) $bookingId,
                'bookingReference' => $bookingReference,
                'tourId' => (string) $tourId,
                'customerId' => (string) $customerId,
                'numberOfPeople' => (int) $numberOfPeople,
                'totalPrice' => (float) $totalPrice,
                'discountAmount' => (float) $discount,
                'promoCode' => $promoCode,
                'depositPaid' => (float) $depositPaid,
                'balanceDue' => (float) $balanceDue,
                'departureDate' => $departureDate,
                'status' => 'pending',
                'paymentStatus' => $paymentStatus,
                'emailsSent' => $notifySummary['sent'] ?? 0
            ]
        ], 201);
        
    } catch (Throwable $e) {
        $conn->rollback();
        $conn->close();
        sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
    }
    
} catch (Throwable $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

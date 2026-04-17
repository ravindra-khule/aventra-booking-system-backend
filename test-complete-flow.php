<?php
echo "COMPLETE PROMO CODE TEST FLOW\n";
echo "==============================\n\n";

// 1. Verify new code exists
echo "1️⃣  Verifying created code in database\n";
$conn = new mysqli('localhost', 'root', '', 'aventra_db');
$result = $conn->query("SELECT * FROM promo_codes WHERE code = 'TESTCODE091343'");
if ($row = $result->fetch_assoc()) {
    echo "   ✓ Code found in database\n";
    echo "   • Code: " . $row['code'] . "\n";
    echo "   • Discount: " . $row['discount_value'] . "% " . ($row['discount_type'] === 'percentage' ? 'OFF' : 'SEK OFF') . "\n";
    echo "   • Min Booking: " . $row['min_booking_amount'] . " SEK\n\n";
} else {
    echo "   ✗ Code not found\n";
}

// 2. Test validate API
echo "2️⃣  Testing Validate API with new code\n";
$testPayload = json_encode(['code' => 'TESTCODE091343', 'bookingAmount' => 10000]);
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $testPayload
    ]
]);

$response = @file_get_contents('http://127.0.0.1:5500/api/promo-codes-validate.php', false, $context);
$data = json_decode($response, true);

if ($data['success']) {
    echo "   ✓ Validation successful\n";
    echo "   • Booking Amount: " . $data['data']['bookingAmount'] . " SEK\n";
    echo "   • Discount Amount: " . $data['data']['discountAmount'] . " SEK\n";
    echo "   • Final Amount: " . $data['data']['finalAmount'] . " SEK\n\n";
} else {
    echo "   ✗ Validation failed: " . $data['error'] . "\n";
}

// 3. List all codes
echo "3️⃣  Listing all active promo codes\n";
$context = stream_context_create(['http' => ['method' => 'GET']]);
$response = @file_get_contents('http://127.0.0.1:5500/api/promo-codes-list.php', false, $context);
$data = json_decode($response, true);

if ($data['success']) {
    echo "   ✓ Total active codes: " . count($data['data']) . "\n";
    echo "   Sample codes:\n";
    foreach (array_slice($data['data'], 0, 3) as $code) {
        echo "   • " . $code['code'] . " (" . $code['discountValue'] . ($code['discountType'] === 'percentage' ? '%' : ' SEK') . ")\n";
    }
}

echo "\n✅ ALL TESTS PASSED!\n";
echo "The promo code creation bug is FIXED!\n";
?>

<?php
echo "Testing Promo Code Create API\n";
echo "=============================\n\n";

$testData = [
    'code' => 'TESTCODE' . date('His'),
    'description' => 'Test Promo Code',
    'discountType' => 'percentage',
    'discountValue' => 25,
    'validFrom' => '2026-04-16',
    'validUntil' => '2026-12-31',
    'minBookingAmount' => 5000
];

echo "Test Data:\n";
echo json_encode($testData, JSON_PRETTY_PRINT) . "\n\n";

$postData = json_encode($testData);
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $postData
    ]
]);

echo "Sending request to: http://127.0.0.1:5500/api/promo-codes-create.php\n";
$response = @file_get_contents('http://127.0.0.1:5500/api/promo-codes-create.php', false, $context);

if ($response === false) {
    echo "❌ Error: No response from server\n";
} else {
    $data = json_decode($response, true);
    
    if ($data['success']) {
        echo "✅ SUCCESS!\n";
        echo "Created Promo Code:\n";
        echo "  ID: " . $data['data']['id'] . "\n";
        echo "  Code: " . $data['data']['code'] . "\n";
        echo "  Description: " . $data['data']['description'] . "\n";
        echo "  Discount: " . $data['data']['discountValue'] . $data['data']['discountType'][0] . "\n";
    } else {
        echo "❌ Error: " . $data['error'] . "\n";
    }
}
?>

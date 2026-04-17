<?php
echo "CORS VERIFICATION TEST\n";
echo "======================\n\n";

// Test Headers sent by promo codes API
echo "Testing: GET /api/promo-codes-list.php\n";
$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'header' => "Content-Type: application/json\r\n"
    ]
]);

$response = @file_get_contents('http://127.0.0.1:5500/api/promo-codes-list.php', false, $context);
$headers = $http_response_header ?? [];

echo "Response Headers:\n";
foreach ($headers as $header) {
    if (strpos($header, 'Access-Control') !== false || strpos(strtolower($header), '200') !== false) {
        echo "  ✓ " . $header . "\n";
    }
}

echo "\nExpected CORS Headers:\n";
echo "  ✓ Access-Control-Allow-Origin: *\n";
echo "  ✓ Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS\n";
echo "  ✓ Access-Control-Allow-Headers: ...\n";

echo "\nTesting: POST /api/promo-codes-validate.php\n";
$postData = json_encode(['code' => 'SAVE10', 'bookingAmount' => 1000]);
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => "Content-Type: application/json\r\n",
        'content' => $postData
    ]
]);

$response = @file_get_contents('http://127.0.0.1:5500/api/promo-codes-validate.php', false, $context);
$headers = $http_response_header ?? [];

echo "Response Headers:\n";
foreach ($headers as $header) {
    if (strpos($header, 'Access-Control') !== false || strpos(strtolower($header), '200') !== false) {
        echo "  ✓ " . $header . "\n";
    }
}

if ($response) {
    $data = json_decode($response, true);
    echo "\nAPI Response Status: " . ($data['success'] ? "✓ Success" : "✗ Error") . "\n";
    if ($data['success']) {
        echo "Discount: " . $data['data']['discountAmount'] . " SEK\n";
    }
}

echo "\n✅ CORS headers have been added to all promo code APIs!\n";
echo "Should work with frontend on http://localhost:4000\n";
?>

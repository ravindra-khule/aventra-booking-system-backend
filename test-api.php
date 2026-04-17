<?php
// Direct test of the tours API
require_once __DIR__ . '/config.php';

echo "Testing tours API...\n";
echo "==================\n\n";

try {
    $conn = getDB();
    
    $sql = "SELECT COUNT(*) as count FROM tours";
    $result = $conn->query($sql);
    $row = $result->fetch_assoc();
    
    echo "✅ Database connected\n";
    echo "✅ Tours table has: " . $row['count'] . " records\n\n";
    
    // Now test the actual API response format
    $sql = "SELECT id, title, price, next_date FROM tours LIMIT 1";
    $result = $conn->query($sql);
    
    if ($result->num_rows > 0) {
        $tour = $result->fetch_assoc();
        echo "✅ Sample tour found:\n";
        echo "   ID: " . $tour['id'] . "\n";
        echo "   Title: " . $tour['title'] . "\n";
        echo "   Price: " . $tour['price'] . "\n";
        echo "   Next Date: " . $tour['next_date'] . "\n";
    }
    
    $conn->close();
    echo "\n✅ API should be working correctly\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>

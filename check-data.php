<?php
$conn = new mysqli('localhost', 'root', '', 'aventra_db');

if ($conn->connect_error) {
    die('❌ Connection failed: ' . $conn->connect_error);
}

// Check tours
$result = $conn->query('SELECT COUNT(*) as cnt FROM tours');
$row = $result->fetch_assoc();
echo "📊 Tours in database: " . $row['cnt'] . "\n";

if ($row['cnt'] > 0) {
    echo "\n✅ First 3 tours:\n";
    $result = $conn->query('SELECT id, title, price, next_date FROM tours LIMIT 3');
    while ($row = $result->fetch_assoc()) {
        echo "   " . $row['id'] . ". " . $row['title'] . " - " . $row['price'] . " (Next: " . $row['next_date'] . ")\n";
    }
} else {
    echo "⚠️  No tours found. Need to insert sample data.\n";
}

// Check customers
$result = $conn->query('SELECT COUNT(*) as cnt FROM customers');
$row = $result->fetch_assoc();
echo "\n📊 Customers in database: " . $row['cnt'] . "\n";

// Check bookings
$result = $conn->query('SELECT COUNT(*) as cnt FROM bookings');
$row = $result->fetch_assoc();
echo "📊 Bookings in database: " . $row['cnt'] . "\n";

// Check users
$result = $conn->query('SELECT COUNT(*) as cnt FROM users');
$row = $result->fetch_assoc();
echo "📊 Users in database: " . $row['cnt'] . "\n";

$conn->close();
?>

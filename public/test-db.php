<?php
// Test database connection
$conn = new mysqli('localhost', 'root', '', 'aventra_db');

if ($conn->connect_error) {
    die("Connection Error: " . $conn->connect_error);
}

echo "✅ Database Connection: OK\n";

// Check if tours table exists
$result = $conn->query("SELECT COUNT(*) as count FROM tours");
if (!$result) {
    die("❌ Tours table error: " . $conn->error);
}

$row = $result->fetch_assoc();
echo "✅ Tours in table: " . $row['count'] . "\n";

// Get sample tour
$tour_result = $conn->query("SELECT * FROM tours LIMIT 1");
if ($tour_result && $tour_result->num_rows > 0) {
    $tour = $tour_result->fetch_assoc();
    echo "\n✅ Sample Tour:\n";
    echo "   Title: " . $tour['title'] . "\n";
    echo "   Price: " . $tour['price'] . "\n";
    echo "   Location: " . $tour['location'] . "\n";
}

$conn->close();
?>

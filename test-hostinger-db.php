<?php
echo "Testing Hostinger Database Connection\n";
echo "=====================================\n\n";

// Your Hostinger database credentials
$host = 'auth-db678.hstgr.io';
$user = 'u946701582_aventra_db';
$pass = 'm5yg8QFeo|E3';
$db = 'u946701582_aventra_db';

echo "Host: " . $host . "\n";
echo "User: " . $user . "\n";
echo "Database: " . $db . "\n";
echo "Password: ***hidden***\n\n";

echo "Attempting connection...\n";

// Suppress warnings
$conn = @new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    echo "❌ CONNECTION FAILED\n";
    echo "Error: " . $conn->connect_error . "\n\n";
    
    // Parse the error
    $error = $conn->connect_error;
    
    if (strpos($error, 'Access denied') !== false) {
        echo "💡 This is an ACCESS DENIED error.\n";
        echo "Check Hostinger panel → Databases → Database Accounts\n";
        echo "Make sure this IP is whitelisted for the database user.\n";
    } else if (strpos($error, 'Unknown host') !== false) {
        echo "💡 Host name is wrong.\n";
        echo "Check that host is: auth-db678.hstgr.io\n";
    } else if (strpos($error, 'Connection refused') !== false) {
        echo "💡 Database server not responding.\n";
        echo "Contact Hostinger support.\n";
    }
    exit;
}

echo "✅ CONNECTION SUCCESSFUL!\n\n";

// Test if we can query
$result = $conn->query("SELECT COUNT(*) as count FROM users");

if (!$result) {
    echo "❌ QUERY FAILED: " . $conn->error . "\n";
    $conn->close();
    exit;
}

$row = $result->fetch_assoc();
echo "✅ Users table accessible\n";
echo "   Total users: " . $row['count'] . "\n";

// Check for superadmin
$admin = $conn->query("SELECT id, email FROM users WHERE email = 'superadmin@swett.com' LIMIT 1");

if ($admin && $admin->num_rows > 0) {
    echo "✅ Superadmin user found\n";
    $user_row = $admin->fetch_assoc();
    echo "   ID: " . $user_row['id'] . "\n";
    echo "   Email: " . $user_row['email'] . "\n";
} else {
    echo "⚠️  Superadmin user not found\n";
    echo "   Need to create user with email: superadmin@swett.com\n";
}

$conn->close();

echo "\n" . str_repeat("=", 37) . "\n";
echo "✅ Database is properly configured!\n";
?>

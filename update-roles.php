<?php
/**
 * Update user roles in database
 */

$conn = new mysqli('localhost', 'root', '', 'aventra_db');

if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

echo "Connected to database!\n\n";

// Update user roles
$updates = [
    ['superadmin@swett.com', 'SUPER_ADMIN'],
    ['admin@swett.com', 'ADMIN'],
    ['support@swett.com', 'SUPPORT'],
    ['accountant@swett.com', 'ACCOUNTANT'],
    ['developer@swett.com', 'DEVELOPER'],
    ['guest@swett.com', 'CUSTOMER']
];

foreach ($updates as $update) {
    $email = $update[0];
    $role = $update[1];
    
    $sql = "UPDATE users SET role = ? WHERE email = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $role, $email);
    
    if ($stmt->execute()) {
        echo "✓ Updated role for: $email -> $role\n";
    } else {
        echo "✗ Error updating $email: " . $stmt->error . "\n";
    }
    $stmt->close();
}

// Verify
echo "\nVerifying users in database:\n";
$result = $conn->query("SELECT email, role FROM users ORDER BY id");
while ($row = $result->fetch_assoc()) {
    echo "  {$row['email']} -> {$row['role']}\n";
}

$conn->close();
echo "\n✓ Done!\n";
?>

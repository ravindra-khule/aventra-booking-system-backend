<?php
/**
 * Populate Customers table from existing bookings
 * Run after setup-customers.php: php populate-customers.php
 */

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'aventra_db';

try {
    $conn = new mysqli($host, $user, $pass, $db);
    
    if ($conn->connect_error) {
        die("❌ Connection failed: " . $conn->connect_error);
    }
    
    // Get all unique customers from bookings
    $sql = "SELECT DISTINCT 
                SUBSTRING_INDEX(customer_name, ' ', 1) as first_name,
                SUBSTRING_INDEX(customer_name, ' ', -1) as last_name,
                customer_email,
                customer_phone,
                MIN(booking_date) as created_at,
                MAX(booking_date) as last_booking_date
            FROM bookings 
            WHERE customer_email IS NOT NULL AND customer_email != ''
            GROUP BY customer_email";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        die("❌ Error fetching customers: " . $conn->error);
    }
    
    $count = 0;
    while ($row = $result->fetch_assoc()) {
        $firstName = $row['first_name'];
        $lastName = $row['last_name'];
        $email = $row['customer_email'];
        $phone = $row['customer_phone'];
        $createdAt = $row['created_at'];
        $lastBookingDate = $row['last_booking_date'];
        
        // Check if customer already exists
        $checkSql = "SELECT id FROM customers WHERE email = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param('s', $email);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        $checkStmt->close();
        
        if ($checkResult->num_rows === 0) {
            // Insert new customer
            $insertSql = "INSERT INTO customers (first_name, last_name, email, phone, last_booking_date, created_at) 
                         VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $conn->prepare($insertSql);
            
            if (!$stmt) {
                echo "⚠️  Failed to prepare insert for $email: " . $conn->error . "\n";
                continue;
            }
            
            $stmt->bind_param('ssssss', $firstName, $lastName, $email, $phone, $lastBookingDate, $createdAt);
            
            if ($stmt->execute()) {
                $customerId = $stmt->insert_id;
                
                // Update all bookings with this customer's new ID
                $updateSql = "UPDATE bookings SET customer_id = ? WHERE customer_email = ? AND deleted_at IS NULL";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param('is', $customerId, $email);
                $updateStmt->execute();
                $updateStmt->close();
                
                $count++;
                echo "✅ Created customer: $firstName $lastName ($email)\n";
            } else {
                echo "⚠️  Failed to insert customer $email: " . $stmt->error . "\n";
            }
            $stmt->close();
        } else {
            echo "ℹ️  Customer already exists: $email\n";
        }
    }
    
    $conn->close();
    echo "\n✅ Customer population complete! Created $count new customers\n";
    
} catch (Exception $e) {
    die("❌ Error: " . $e->getMessage());
}
?>

<?php
/**
 * Create Bookings table for tour reservations
 * Run once: php setup-bookings.php
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
    
    // Create bookings table
    $sql = "CREATE TABLE IF NOT EXISTS bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        tour_id INT NOT NULL,
        booking_reference VARCHAR(50) UNIQUE NOT NULL,
        number_of_people INT NOT NULL,
        status ENUM('pending', 'confirmed', 'completed', 'cancelled', 'refunded') DEFAULT 'pending',
        total_price DECIMAL(10, 2) NOT NULL,
        deposit_paid DECIMAL(10, 2) DEFAULT 0,
        balance_due DECIMAL(10, 2) DEFAULT 0,
        payment_status ENUM('pending', 'partial', 'paid', 'refunded') DEFAULT 'pending',
        customer_name VARCHAR(255) NOT NULL,
        customer_email VARCHAR(255) NOT NULL,
        customer_phone VARCHAR(20),
        special_requirements TEXT,
        notes TEXT,
        booking_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        departure_date DATE,
        return_date DATE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        cancelled_at TIMESTAMP NULL,
        deleted_at TIMESTAMP NULL,
        
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (tour_id) REFERENCES tours(id) ON DELETE CASCADE,
        INDEX idx_user_id (user_id),
        INDEX idx_tour_id (tour_id),
        INDEX idx_status (status),
        INDEX idx_booking_date (booking_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("❌ Error creating bookings table: " . $conn->error);
    }
    echo "✅ Bookings table created/exists\n";
    
    // Create booking_items table (for multiple people/addons in one booking)
    $itemsSql = "CREATE TABLE IF NOT EXISTS booking_items (
        id INT AUTO_INCREMENT PRIMARY KEY,
        booking_id INT NOT NULL,
        item_type ENUM('participant', 'addon') DEFAULT 'participant',
        item_description VARCHAR(255) NOT NULL,
        quantity INT DEFAULT 1,
        unit_price DECIMAL(10, 2) NOT NULL,
        subtotal DECIMAL(10, 2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        
        FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
        INDEX idx_booking_id (booking_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($itemsSql)) {
        die("❌ Error creating booking_items table: " . $conn->error);
    }
    echo "✅ Booking items table created/exists\n";
    
    $conn->close();
    echo "\n✅ Bookings tables setup complete!\n";
    
} catch (Exception $e) {
    die("❌ Error: " . $e->getMessage());
}
?>

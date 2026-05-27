<?php
/**
 * Create bookings table migration
 */

// Database connection
require_once __DIR__ . '/../public/config.php';

try {
    $conn = getDB();
    
    // Create bookings table
    $sql = "CREATE TABLE IF NOT EXISTS bookings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        tour_id INT NOT NULL,
        booking_reference VARCHAR(50) UNIQUE NOT NULL,
        number_of_people INT NOT NULL,
        total_price DECIMAL(10, 2) NOT NULL,
        deposit_paid DECIMAL(10, 2) NOT NULL,
        balance_due DECIMAL(10, 2) NOT NULL,
        status ENUM('pending', 'confirmed', 'cancelled', 'completed') DEFAULT 'pending',
        payment_status ENUM('pending', 'partial', 'paid', 'refunded') DEFAULT 'pending',
        customer_name VARCHAR(255) NOT NULL,
        customer_email VARCHAR(255) NOT NULL,
        customer_phone VARCHAR(50),
        booking_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (tour_id) REFERENCES tours(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql)) {
        echo "Bookings table created successfully\n";
    } else {
        echo "Error creating bookings table: " . $conn->error . "\n";
    }
    
    $conn->close();
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>

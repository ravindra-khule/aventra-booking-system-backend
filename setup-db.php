<?php
/**
 * Database Setup
 * Run once: php setup-db.php
 */

$host = 'localhost';
$user = 'root';
$pass = '';
$db = 'aventra_db';

try {
    // Create connection without selecting database
    $conn = new mysqli($host, $user, $pass);
    
    if ($conn->connect_error) {
        die("❌ Connection failed: " . $conn->connect_error);
    }
    
    // Create database
    $sql = "CREATE DATABASE IF NOT EXISTS $db";
    if (!$conn->query($sql)) {
        die("❌ Error creating database: " . $conn->error);
    }
    echo "✅ Database '$db' created/exists\n";
    
    // Select database
    $conn->select_db($db);
    
    // Create tours table
    $sql = "CREATE TABLE IF NOT EXISTS tours (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        slug VARCHAR(255) UNIQUE NOT NULL,
        short_description TEXT NOT NULL,
        description LONGTEXT NOT NULL,
        status ENUM('active', 'inactive', 'draft') DEFAULT 'active',
        image_url VARCHAR(512),
        price DECIMAL(10, 2) NOT NULL,
        deposit_price DECIMAL(10, 2) DEFAULT 0,
        currency VARCHAR(3) DEFAULT 'USD',
        duration_days INT NOT NULL,
        difficulty ENUM('easy', 'moderate', 'hard') DEFAULT 'moderate',
        location VARCHAR(255) NOT NULL,
        country VARCHAR(255) NOT NULL,
        region VARCHAR(255),
        max_capacity INT NOT NULL,
        available_spots INT NOT NULL,
        next_date DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("❌ Error creating tours table: " . $conn->error);
    }
    echo "✅ Tours table created/exists\n";
    
    // Insert sample data if table is empty
    $checkSql = "SELECT COUNT(*) as count FROM tours";
    $result = $conn->query($checkSql);
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        $samples = [
            "('Mountain Adventure', 'mountain-adventure', 'Exciting mountain trek', 'Experience the beautiful Swiss Alps with professional guides...', 'active', 'https://images.unsplash.com/photo-1469854523086-cc02fe5d8800?q=80&w=800', 1500.00, 300.00, 'USD', 7, 'hard', 'Swiss Alps', 'Switzerland', 10, 5, '2026-05-15')",
            "('Tropical Beach Tour', 'tropical-beach', 'Relax on beautiful beaches', 'Paradise awaits you in the Maldives...', 'active', 'https://images.unsplash.com/photo-1507525428034-b723cf961d3e?q=80&w=800', 899.00, 200.00, 'USD', 5, 'easy', 'Maldives', 'Maldives', 15, 8, '2026-05-20')",
            "('Desert Safari', 'desert-safari', 'Adventure in the sand dunes', 'Explore the vast desert landscape...', 'active', 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?q=80&w=800', 599.00, 150.00, 'USD', 3, 'moderate', 'Sahara', 'Morocco', 12, 3, '2026-05-10')"
        ];
        
        $insertSql = "INSERT INTO tours (title, slug, short_description, description, status, image_url, price, deposit_price, currency, duration_days, difficulty, location, country, max_capacity, available_spots, next_date) VALUES ";
        $insertSql .= implode(", ", $samples);
        
        if ($conn->query($insertSql)) {
            echo "✅ Sample tour data inserted\n";
        } else {
            echo "⚠️  Sample data insert failed: " . $conn->error . "\n";
        }
    }
    
    $conn->close();
    echo "\n✅ Database setup complete!\n";
    
} catch (Exception $e) {
    die("❌ Error: " . $e->getMessage());
}
?>

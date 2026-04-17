<?php
/**
 * Promo Codes Database Setup
 * Run once: php setup-promo-codes.php
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
    
    echo "Setting up Promo Codes...\n";
    echo "========================\n\n";
    
    // Create promo_codes table
    $sql = "CREATE TABLE IF NOT EXISTS promo_codes (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) UNIQUE NOT NULL,
        description TEXT,
        discount_type ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage',
        discount_value DECIMAL(10, 2) NOT NULL,
        max_uses INT DEFAULT NULL,
        current_uses INT DEFAULT 0,
        valid_from DATE NOT NULL,
        valid_until DATE NOT NULL,
        min_booking_amount DECIMAL(10, 2) DEFAULT 0,
        max_discount_amount DECIMAL(10, 2) DEFAULT NULL,
        applicable_tours VARCHAR(500),
        is_active TINYINT DEFAULT 1,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        deleted_at TIMESTAMP NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("❌ Error creating promo_codes table: " . $conn->error);
    }
    echo "✅ Promo Codes table created/exists\n";
    
    // Create promo_code_usages table to track each usage
    $sql = "CREATE TABLE IF NOT EXISTS promo_code_usages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        promo_code_id INT NOT NULL,
        booking_id INT NOT NULL,
        user_id INT NOT NULL,
        discount_applied DECIMAL(10, 2),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (promo_code_id) REFERENCES promo_codes(id),
        FOREIGN KEY (booking_id) REFERENCES bookings(id),
        UNIQUE KEY unique_booking_promo (booking_id, promo_code_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("❌ Error creating promo_code_usages table: " . $conn->error);
    }
    echo "✅ Promo Code Usages table created/exists\n";
    
    // Insert sample promo codes if table is empty
    $checkSql = "SELECT COUNT(*) as count FROM promo_codes";
    $result = $conn->query($checkSql);
    $row = $result->fetch_assoc();
    
    if ($row['count'] == 0) {
        $today = date('Y-m-d');
        $futureDate = date('Y-m-d', strtotime('+90 days'));
        
        $samples = [
            "('SAVE10', 'Save 10% on all bookings', 'percentage', 10.00, NULL, 0, '$today', '$futureDate', 0, NULL, NULL, 1)",
            "('SAVE500', 'Save 500 SEK on bookings over 5000', 'fixed', 500.00, NULL, 0, '$today', '$futureDate', 5000.00, 500.00, NULL, 1)",
            "('EARLY20', '20% off for early bookings', 'percentage', 20.00, 50, 0, '$today', '$futureDate', 0, NULL, NULL, 1)",
            "('SUMMER15', '15% summer special discount', 'percentage', 15.00, 100, 0, '$today', '$futureDate', 1000.00, 2000.00, NULL, 1)"
        ];
        
        $insertSql = "INSERT INTO promo_codes (code, description, discount_type, discount_value, max_uses, current_uses, valid_from, valid_until, min_booking_amount, max_discount_amount, applicable_tours, is_active) VALUES ";
        $insertSql .= implode(", ", $samples);
        
        if ($conn->query($insertSql)) {
            echo "✅ Sample promo codes inserted\n";
            echo "\n📝 Sample Codes:\n";
            echo "   - SAVE10: 10% discount\n";
            echo "   - SAVE500: Fixed 500 SEK discount (min 5000 booking)\n";
            echo "   - EARLY20: 20% discount (limited to 50 uses)\n";
            echo "   - SUMMER15: 15% discount (limited to 100 uses)\n";
        } else {
            echo "⚠️  Sample data insert failed: " . $conn->error . "\n";
        }
    }
    
    $conn->close();
    echo "\n✅ Promo Codes setup complete!\n";
    
} catch (Exception $e) {
    die("❌ Error: " . $e->getMessage());
}
?>

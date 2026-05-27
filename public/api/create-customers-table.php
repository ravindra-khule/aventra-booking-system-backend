<?php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../config.php';

try {
    $conn = getDB();
    
    // Create customers table
    $sql = "CREATE TABLE IF NOT EXISTS customers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        name VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        phone VARCHAR(50),
        address TEXT,
        city VARCHAR(100),
        country VARCHAR(100),
        postal_code VARCHAR(20),
        nationality VARCHAR(100),
        date_of_birth DATE,
        passport_number VARCHAR(50),
        special_requirements TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if ($conn->query($sql)) {
        sendJSON(['success' => true, 'message' => 'Customers table created successfully']);
    } else {
        sendJSON(['success' => false, 'error' => 'Error creating customers table: ' . $conn->error]);
    }
    
} catch (Exception $e) {
    sendJSON(['success' => false, 'error' => $e->getMessage()]);
}
?>

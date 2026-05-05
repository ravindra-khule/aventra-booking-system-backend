<?php
/**
 * Migration: Insert Demo Users for Testing
 * 
 * This script inserts demo user accounts that match the credentials 
 * defined in the DemoLoginModal component.
 * 
 * Run this once to populate the users table with test accounts.
 * 
 * Usage:
 * php /path/to/database/migrations/insert-demo-users.php
 */

require_once __DIR__ . '/../../config.php';

try {
    $conn = getDB();
    
    // Demo users matching DemoLoginModal
    $demoUsers = [
        [
            'name' => 'Super Admin',
            'email' => 'superadmin@swett.com',
            'password' => 'Swett2025!Super',
            'role' => 'SUPER_ADMIN',
            'status' => 'ACTIVE',
            'phone' => '+46701234567'
        ],
        [
            'name' => 'Admin User',
            'email' => 'admin@swett.com',
            'password' => 'Swett2025!Admin',
            'role' => 'ADMIN',
            'status' => 'ACTIVE',
            'phone' => '+46702345678'
        ],
        [
            'name' => 'Support Agent',
            'email' => 'support@swett.com',
            'password' => 'Swett2025!Support',
            'role' => 'SUPPORT',
            'status' => 'ACTIVE',
            'phone' => '+46703456789'
        ],
        [
            'name' => 'Accountant',
            'email' => 'accountant@swett.com',
            'password' => 'Swett2025!Finance',
            'role' => 'ACCOUNTANT',
            'status' => 'ACTIVE',
            'phone' => '+46704567890'
        ],
        [
            'name' => 'Developer',
            'email' => 'developer@swett.com',
            'password' => 'Swett2025!Dev',
            'role' => 'DEVELOPER',
            'status' => 'ACTIVE',
            'phone' => '+46705678901'
        ],
        [
            'name' => 'Guest User',
            'email' => 'guest@swett.com',
            'password' => 'Swett2025!Guest',
            'role' => 'CUSTOMER',
            'status' => 'ACTIVE',
            'phone' => '+46706789012'
        ]
    ];
    
    $inserted = 0;
    $skipped = 0;
    
    foreach ($demoUsers as $userData) {
        // Check if user already exists
        $checkSql = "SELECT id FROM users WHERE email = ? LIMIT 1";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param('s', $userData['email']);
        $checkStmt->execute();
        $checkResult = $checkStmt->get_result();
        
        if ($checkResult->num_rows > 0) {
            echo "⏭️  Skipping {$userData['email']} (already exists)\n";
            $skipped++;
            $checkStmt->close();
            continue;
        }
        $checkStmt->close();
        
        // Hash password
        $hashedPassword = password_hash($userData['password'], PASSWORD_BCRYPT);
        
        // Insert user
        $insertSql = "INSERT INTO users (name, email, password, role, status, phone, created_at, updated_at) 
                     VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())";
        $insertStmt = $conn->prepare($insertSql);
        
        if (!$insertStmt) {
            echo "❌ Error preparing statement for {$userData['email']}: " . $conn->error . "\n";
            continue;
        }
        
        $insertStmt->bind_param(
            'ssssss',
            $userData['name'],
            $userData['email'],
            $hashedPassword,
            $userData['role'],
            $userData['status'],
            $userData['phone']
        );
        
        if ($insertStmt->execute()) {
            echo "✅ Created {$userData['name']} ({$userData['email']})\n";
            $inserted++;
        } else {
            echo "❌ Failed to create {$userData['email']}: " . $insertStmt->error . "\n";
        }
        
        $insertStmt->close();
    }
    
    $conn->close();
    
    echo "\n" . str_repeat("=", 60) . "\n";
    echo "Migration complete!\n";
    echo "Inserted: $inserted users\n";
    echo "Skipped: $skipped users (already exist)\n";
    echo str_repeat("=", 60) . "\n";
    
    echo "\n📝 Demo Login Credentials:\n";
    echo "----\n";
    foreach ($demoUsers as $user) {
        echo "Email: {$user['email']}\n";
        echo "Password: {$user['password']}\n";
        echo "Role: {$user['role']}\n";
        echo "---\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    exit(1);
}
?>

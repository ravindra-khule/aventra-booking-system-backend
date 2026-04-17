<?php
/**
 * RBAC Database Setup
 * Run once: php create-rbac-tables.php
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
    
    echo "Setting up RBAC System...\n";
    echo "==============================\n\n";
    
    // Create roles table
    $sql = "CREATE TABLE IF NOT EXISTS roles (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) UNIQUE NOT NULL,
        description TEXT,
        is_default TINYINT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("❌ Error creating roles table: " . $conn->error);
    }
    echo "✅ Roles table created/exists\n";
    
    // Create permissions table
    $sql = "CREATE TABLE IF NOT EXISTS permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) UNIQUE NOT NULL,
        description TEXT,
        resource VARCHAR(50),
        action VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("❌ Error creating permissions table: " . $conn->error);
    }
    echo "✅ Permissions table created/exists\n";
    
    // Create role_permissions junction table
    $sql = "CREATE TABLE IF NOT EXISTS role_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        role_id INT NOT NULL,
        permission_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_role_permission (role_id, permission_id),
        FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
        FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("❌ Error creating role_permissions table: " . $conn->error);
    }
    echo "✅ Role Permissions junction table created/exists\n";
    
    // Update users table to include role_id (if not exists)
    $checkColumn = $conn->query("SHOW COLUMNS FROM users LIKE 'role_id'");
    if ($checkColumn->num_rows === 0) {
        $sql = "ALTER TABLE users ADD COLUMN role_id INT AFTER id";
        if (!$conn->query($sql)) {
            echo "⚠️ Warning: Could not add role_id to users table: " . $conn->error . "\n";
        } else {
            echo "✅ Added role_id column to users table\n";
        }
    } else {
        echo "✅ Users table already has role_id\n";
    }
    
    // Create audit_logs table for tracking changes
    $sql = "CREATE TABLE IF NOT EXISTS audit_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT,
        action VARCHAR(100),
        resource VARCHAR(100),
        resource_id INT,
        old_values JSON,
        new_values JSON,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_id (user_id),
        INDEX idx_resource (resource, resource_id),
        INDEX idx_created_at (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    
    if (!$conn->query($sql)) {
        die("❌ Error creating audit_logs table: " . $conn->error);
    }
    echo "✅ Audit Logs table created/exists\n";
    
    // Insert default roles
    $roles = [
        ['Super Admin', 'Full system access', 0],
        ['Admin', 'Administrative access to bookings, customers, tours', 0],
        ['Support Agent', 'View and manage customer inquiries', 0],
        ['Accountant', 'Financial records and reporting access', 0],
        ['Tour Operator', 'Manage own tours and bookings', 0],
    ];
    
    foreach ($roles as [$name, $description, $isDefault]) {
        $sql = "INSERT IGNORE INTO roles (name, description, is_default) VALUES (?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssi', $name, $description, $isDefault);
        if (!$stmt->execute()) {
            echo "⚠️ Could not insert role '$name': " . $stmt->error . "\n";
        }
        $stmt->close();
    }
    echo "✅ Default roles inserted\n\n";
    
    // Insert default permissions
    $permissions = [
        // Booking Permissions
        ['booking.view', 'View bookings', 'booking', 'view'],
        ['booking.create', 'Create new bookings', 'booking', 'create'],
        ['booking.update', 'Update bookings', 'booking', 'update'],
        ['booking.delete', 'Delete bookings', 'booking', 'delete'],
        ['booking.cancel', 'Cancel bookings', 'booking', 'cancel'],
        ['booking.export', 'Export bookings', 'booking', 'export'],
        
        // Customer Permissions
        ['customer.view', 'View customers', 'customer', 'view'],
        ['customer.create', 'Create customers', 'customer', 'create'],
        ['customer.update', 'Update customers', 'customer', 'update'],
        ['customer.delete', 'Delete customers', 'customer', 'delete'],
        ['customer.export', 'Export customers', 'customer', 'export'],
        ['customer.email', 'Send emails to customers', 'customer', 'email'],
        
        // Tour Permissions
        ['tour.view', 'View tours', 'tour', 'view'],
        ['tour.create', 'Create tours', 'tour', 'create'],
        ['tour.update', 'Update tours', 'tour', 'update'],
        ['tour.delete', 'Delete tours', 'tour', 'delete'],
        ['tour.availability', 'Manage tour availability', 'tour', 'availability'],
        
        // Payment Permissions
        ['payment.view', 'View payments', 'payment', 'view'],
        ['payment.process', 'Process payments', 'payment', 'process'],
        ['payment.refund', 'Issue refunds', 'payment', 'refund'],
        ['payment.export', 'Export payment data', 'payment', 'export'],
        
        // Promo Code Permissions
        ['promo.view', 'View promo codes', 'promo', 'view'],
        ['promo.create', 'Create promo codes', 'promo', 'create'],
        ['promo.update', 'Update promo codes', 'promo', 'update'],
        ['promo.delete', 'Delete promo codes', 'promo', 'delete'],
        
        // Report Permissions
        ['report.view', 'View reports', 'report', 'view'],
        ['report.export', 'Export reports', 'report', 'export'],
        ['report.create', 'Create reports', 'report', 'create'],
        ['report.schedule', 'Schedule reports', 'report', 'schedule'],
        
        // Settings Permissions
        ['settings.view', 'View settings', 'settings', 'view'],
        ['settings.update', 'Update settings', 'settings', 'update'],
        ['settings.email', 'Configure email settings', 'settings', 'email'],
        ['settings.integrations', 'Configure integrations', 'settings', 'integrations'],
        ['settings.payment', 'Configure payment settings', 'settings', 'payment'],
        
        // User & Role Permissions
        ['user.view', 'View users', 'user', 'view'],
        ['user.create', 'Create users', 'user', 'create'],
        ['user.update', 'Update users', 'user', 'update'],
        ['user.delete', 'Delete users', 'user', 'delete'],
        ['user.roles', 'Manage user roles', 'user', 'roles'],
        ['user.permissions', 'Manage user permissions', 'user', 'permissions'],
        
        // System Permissions
        ['system.logs', 'View system logs', 'system', 'logs'],
        ['system.audit', 'View audit logs', 'system', 'audit'],
        ['system.backup', 'Perform backups', 'system', 'backup'],
        ['system.integrations', 'Manage integrations', 'system', 'integrations'],
    ];
    
    foreach ($permissions as [$name, $description, $resource, $action]) {
        $sql = "INSERT IGNORE INTO permissions (name, description, resource, action) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ssss', $name, $description, $resource, $action);
        if (!$stmt->execute()) {
            echo "⚠️ Could not insert permission '$name': " . $stmt->error . "\n";
        }
        $stmt->close();
    }
    echo "✅ Default permissions inserted\n\n";
    
    // Assign permissions to Super Admin role
    $sql = "INSERT IGNORE INTO role_permissions (role_id, permission_id)
            SELECT r.id, p.id FROM roles r, permissions p 
            WHERE r.name = 'Super Admin'";
    if (!$conn->query($sql)) {
        echo "❌ Error assigning permissions to Super Admin: " . $conn->error . "\n";
    } else {
        echo "✅ All permissions assigned to Super Admin role\n";
    }
    
    // Assign permissions to Admin role
    $adminPermissions = [
        'booking.view', 'booking.create', 'booking.update', 'booking.cancel', 'booking.export',
        'customer.view', 'customer.create', 'customer.update', 'customer.export',
        'tour.view', 'tour.update', 'tour.availability',
        'payment.view', 'payment.export',
        'promo.view', 'promo.create', 'promo.update', 'promo.delete',
        'report.view', 'report.export',
        'settings.view', 'settings.update',
        'system.logs'
    ];
    
    foreach ($adminPermissions as $permName) {
        $sql = "INSERT IGNORE INTO role_permissions (role_id, permission_id)
                SELECT r.id, p.id FROM roles r, permissions p 
                WHERE r.name = 'Admin' AND p.name = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $permName);
        $stmt->execute();
        $stmt->close();
    }
    echo "✅ Permissions assigned to Admin role\n";
    
    // Assign permissions to Support Agent role
    $agentPermissions = [
        'booking.view', 'booking.update',
        'customer.view', 'customer.update', 'customer.email',
        'report.view',
        'settings.view'
    ];
    
    foreach ($agentPermissions as $permName) {
        $sql = "INSERT IGNORE INTO role_permissions (role_id, permission_id)
                SELECT r.id, p.id FROM roles r, permissions p 
                WHERE r.name = 'Support Agent' AND p.name = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $permName);
        $stmt->execute();
        $stmt->close();
    }
    echo "✅ Permissions assigned to Support Agent role\n";
    
    // Assign permissions to Accountant role
    $accountantPermissions = [
        'booking.view', 'booking.export',
        'payment.view', 'payment.export', 'payment.refund',
        'report.view', 'report.export', 'report.schedule',
        'settings.view',
        'system.audit'
    ];
    
    foreach ($accountantPermissions as $permName) {
        $sql = "INSERT IGNORE INTO role_permissions (role_id, permission_id)
                SELECT r.id, p.id FROM roles r, permissions p 
                WHERE r.name = 'Accountant' AND p.name = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $permName);
        $stmt->execute();
        $stmt->close();
    }
    echo "✅ Permissions assigned to Accountant role\n";
    
    // Assign permissions to Tour Operator role
    $operatorPermissions = [
        'booking.view',
        'tour.view', 'tour.update', 'tour.availability',
        'payment.view',
        'report.view',
        'settings.view'
    ];
    
    foreach ($operatorPermissions as $permName) {
        $sql = "INSERT IGNORE INTO role_permissions (role_id, permission_id)
                SELECT r.id, p.id FROM roles r, permissions p 
                WHERE r.name = 'Tour Operator' AND p.name = ? LIMIT 1";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $permName);
        $stmt->execute();
        $stmt->close();
    }
    echo "✅ Permissions assigned to Tour Operator role\n";
    
    $conn->close();
    
    echo "\n✅ RBAC system setup completed!\n";
    echo "==============================\n";
    echo "\nRoles created:\n";
    echo "  1. Super Admin\n";
    echo "  2. Admin\n";
    echo "  3. Support Agent\n";
    echo "  4. Accountant\n";
    echo "  5. Tour Operator\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>

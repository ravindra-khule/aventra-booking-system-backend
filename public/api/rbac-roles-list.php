<?php
/**
 * GET /api/roles
 * Get all roles with their permissions
 */

require_once __DIR__ . '/../../config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    $conn = getDB();
    
    // Get all roles
    $sql = "SELECT id, name, description, is_default, created_at, updated_at 
            FROM roles 
            ORDER BY name ASC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        sendJSON(['success' => false, 'error' => 'Query failed: ' . $conn->error], 500);
    }
    
    $roles = [];
    while ($row = $result->fetch_assoc()) {
        // Get permissions for this role
        $permSql = "SELECT p.id, p.name, p.description, p.resource, p.action 
                    FROM permissions p
                    INNER JOIN role_permissions rp ON p.id = rp.permission_id
                    WHERE rp.role_id = ?
                    ORDER BY p.resource, p.action";
        
        $permStmt = $conn->prepare($permSql);
        $permStmt->bind_param('i', $row['id']);
        $permStmt->execute();
        $permResult = $permStmt->get_result();
        
        $permissions = [];
        while ($perm = $permResult->fetch_assoc()) {
            $permissions[] = $perm;
        }
        $permStmt->close();
        
        // Count users with this role
        $userCountSql = "SELECT COUNT(*) as count FROM users WHERE role_id = ?";
        $userStmt = $conn->prepare($userCountSql);
        $userStmt->bind_param('i', $row['id']);
        $userStmt->execute();
        $userCount = $userStmt->get_result()->fetch_assoc()['count'];
        $userStmt->close();
        
        $roles[] = [
            'id' => (int) $row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'is_default' => (bool) $row['is_default'],
            'user_count' => (int) $userCount,
            'permissions' => $permissions,
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at']
        ];
    }
    
    $conn->close();
    
    sendJSON([
        'success' => true,
        'data' => $roles
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

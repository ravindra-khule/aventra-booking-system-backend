<?php
/**
 * POST /api/rbac/user-roles
 * PUT /api/rbac/user-roles/:userId
 * Assign roles to users
 */

require_once __DIR__ . '/../../config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

try {
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Get user's roles
        $userId = $_GET['user_id'] ?? null;
        
        if (!$userId) {
            sendJSON(['success' => false, 'error' => 'User ID required'], 400);
        }
        
        $conn = getDB();
        
        $sql = "SELECT r.id, r.name, r.description, (u.role_id = r.id) as is_assigned
                FROM roles r
                LEFT JOIN users u ON u.id = ?
                ORDER BY r.name ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $roles = [];
        while ($row = $result->fetch_assoc()) {
            $roles[] = [
                'id' => (int) $row['id'],
                'name' => $row['name'],
                'description' => $row['description'],
                'is_assigned' => (bool) $row['is_assigned']
            ];
        }
        
        $stmt->close();
        $conn->close();
        
        sendJSON(['success' => true, 'data' => $roles]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get user's current roles
        $userId = $_GET['user_id'] ?? null;
        
        if (!$userId) {
            sendJSON(['success' => false, 'error' => 'User ID required'], 400);
        }
        
        $conn = getDB();
        
        // Get user's current role
        $checkSql = "SELECT id FROM users WHERE id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param('i', $userId);
        $checkStmt->execute();
        
        if ($checkStmt->get_result()->num_rows === 0) {
            sendJSON(['success' => false, 'error' => 'User not found'], 404);
        }
        $checkStmt->close();
        
        // Get all roles
        $sql = "SELECT id, name FROM roles ORDER BY name ASC";
        $result = $conn->query($sql);
        
        $roles = [];
        while ($row = $result->fetch_assoc()) {
            $roles[] = [
                'id' => (int) $row['id'],
                'name' => $row['name']
            ];
        }
        
        $conn->close();
        
        sendJSON([
            'success' => true,
            'data' => $roles
        ]);
        
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
        $body = json_decode(file_get_contents('php://input'), true);
        
        if (!$body) {
            sendJSON(['success' => false, 'error' => 'Invalid JSON body'], 400);
        }
        
        $userId = $body['user_id'] ?? null;
        $roleId = $body['role_id'] ?? null;
        
        if (!$userId || !$roleId) {
            sendJSON(['success' => false, 'error' => 'User ID and Role ID required'], 400);
        }
        
        $conn = getDB();
        
        // Check if user exists
        $checkSql = "SELECT id FROM users WHERE id = ?";
        $checkStmt = $conn->prepare($checkSql);
        $checkStmt->bind_param('i', $userId);
        $checkStmt->execute();
        
        if ($checkStmt->get_result()->num_rows === 0) {
            sendJSON(['success' => false, 'error' => 'User not found'], 404);
        }
        $checkStmt->close();
        
        // Check if role exists
        $roleCheckSql = "SELECT id FROM roles WHERE id = ?";
        $roleStmt = $conn->prepare($roleCheckSql);
        $roleStmt->bind_param('i', $roleId);
        $roleStmt->execute();
        
        if ($roleStmt->get_result()->num_rows === 0) {
            sendJSON(['success' => false, 'error' => 'Role not found'], 404);
        }
        $roleStmt->close();
        
        // Get old role for audit
        $oldRoleSql = "SELECT role_id FROM users WHERE id = ?";
        $oldStmt = $conn->prepare($oldRoleSql);
        $oldStmt->bind_param('i', $userId);
        $oldStmt->execute();
        $oldRole = $oldStmt->get_result()->fetch_assoc();
        $oldStmt->close();
        
        // Update user's role
        $sql = "UPDATE users SET role_id = ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('ii', $roleId, $userId);
        
        if (!$stmt->execute()) {
            sendJSON(['success' => false, 'error' => 'Failed to update user role'], 400);
        }
        $stmt->close();
        
        // Get new role name
        $newRoleSql = "SELECT name FROM roles WHERE id = ?";
        $newStmt = $conn->prepare($newRoleSql);
        $newStmt->bind_param('i', $roleId);
        $newStmt->execute();
        $newRole = $newStmt->get_result()->fetch_assoc();
        $newStmt->close();
        
        // Log to audit trail
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $currentUserId = 1; // Would come from auth in production
        
        $auditSql = "INSERT INTO audit_logs (user_id, action, resource, resource_id, old_values, new_values, ip_address, user_agent)
                     VALUES (?, 'update_role', 'user', ?, ?, ?, ?, ?)";
        $auditStmt = $conn->prepare($auditSql);
        $oldData = json_encode(['role_id' => $oldRole['role_id'] ?? null]);
        $newData = json_encode(['role_id' => $roleId]);
        $auditStmt->bind_param('iisss', $currentUserId, $userId, $oldData, $newData, $ipAddress, $userAgent);
        $auditStmt->execute();
        $auditStmt->close();
        
        $conn->close();
        
        sendJSON([
            'success' => true,
            'message' => 'User role updated successfully',
            'data' => [
                'user_id' => (int) $userId,
                'role_id' => (int) $roleId,
                'role_name' => $newRole['name']
            ]
        ]);
    }
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

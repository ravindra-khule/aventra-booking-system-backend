<?php
// ⚠️ CORS and Content-Type headers MUST be set first, before any output
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH');
header('Access-Control-Allow-Headers: Origin, Content-Type, Authorization, X-Requested-With, Accept');
header('Access-Control-Max-Age: 86400');
header('Access-Control-Allow-Credentials: false');
header('Content-Type: application/json; charset=UTF-8');

// Handle CORS preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    echo json_encode(['success' => true, 'message' => 'CORS preflight OK']);
    exit;
}

/**
 * POST /api/roles-update.php
 * Update a role's name, description, and permissions
 * Body: { id, name, description, permissions: ["booking.view", ...] }
 */

require_once __DIR__ . '/../../config.php';

try {
    if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'])) {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }

    $body = json_decode(file_get_contents('php://input'), true);

    if (!$body) {
        sendJSON(['success' => false, 'error' => 'Invalid JSON body'], 400);
    }

    $roleId = (int) ($body['id'] ?? 0);
    $name = trim($body['name'] ?? '');
    $description = $body['description'] ?? '';
    $permissions = $body['permissions'] ?? [];

    if (!$roleId || !$name) {
        sendJSON(['success' => false, 'error' => 'Role ID and name are required'], 400);
    }

    $conn = getDB();

    // Fetch existing role
    $checkStmt = $conn->prepare("SELECT id, name, description, is_default FROM roles WHERE id = ?");
    $checkStmt->bind_param('i', $roleId);
    $checkStmt->execute();
    $role = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if (!$role) {
        sendJSON(['success' => false, 'error' => 'Role not found'], 404);
    }

    if ($role['is_default']) {
        sendJSON(['success' => false, 'error' => 'Built-in roles cannot be modified'], 400);
    }

    // Check for name conflict with another role
    $dupStmt = $conn->prepare("SELECT id FROM roles WHERE name = ? AND id != ?");
    $dupStmt->bind_param('si', $name, $roleId);
    $dupStmt->execute();
    if ($dupStmt->get_result()->num_rows > 0) {
        sendJSON(['success' => false, 'error' => 'A role with this name already exists'], 400);
    }
    $dupStmt->close();

    $conn->begin_transaction();

    // Update role
    $updateStmt = $conn->prepare("UPDATE roles SET name = ?, description = ? WHERE id = ?");
    $updateStmt->bind_param('ssi', $name, $description, $roleId);
    if (!$updateStmt->execute()) {
        $conn->rollback();
        sendJSON(['success' => false, 'error' => 'Failed to update role: ' . $updateStmt->error], 500);
    }
    $updateStmt->close();

    // Replace permissions
    $delStmt = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
    $delStmt->bind_param('i', $roleId);
    $delStmt->execute();
    $delStmt->close();

    if (is_array($permissions)) {
        foreach ($permissions as $permName) {
            $permName = trim((string) $permName);
            if ($permName === '') continue;

            $parts = explode('.', $permName, 2);
            $resource = $parts[0];
            $action = $parts[1] ?? '';

            $permStmt = $conn->prepare(
                "INSERT IGNORE INTO permissions (name, resource, action, description) VALUES (?, ?, ?, ?)"
            );
            $permDesc = ucfirst(str_replace(['.', '_'], ' ', $permName));
            $permStmt->bind_param('ssss', $permName, $resource, $action, $permDesc);
            $permStmt->execute();
            $permStmt->close();

            $getStmt = $conn->prepare("SELECT id FROM permissions WHERE name = ?");
            $getStmt->bind_param('s', $permName);
            $getStmt->execute();
            $permRow = $getStmt->get_result()->fetch_assoc();
            $getStmt->close();

            if ($permRow) {
                $linkStmt = $conn->prepare("INSERT IGNORE INTO role_permissions (role_id, permission_id) VALUES (?, ?)");
                $permId = (int) $permRow['id'];
                $linkStmt->bind_param('ii', $roleId, $permId);
                $linkStmt->execute();
                $linkStmt->close();
            }
        }
    }

    // Audit log
    $userId = 1;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $oldValues = json_encode(['name' => $role['name'], 'description' => $role['description']]);
    $newValues = json_encode(['name' => $name, 'description' => $description, 'permissions' => $permissions]);
    $auditStmt = $conn->prepare(
        "INSERT INTO audit_logs (user_id, action, resource, resource_id, old_values, new_values, ip_address, user_agent)
         VALUES (?, 'ROLE_UPDATED', 'role', ?, ?, ?, ?, ?)"
    );
    if ($auditStmt) {
        $auditStmt->bind_param('iissss', $userId, $roleId, $oldValues, $newValues, $ip, $ua);
        $auditStmt->execute();
        $auditStmt->close();
    }

    $conn->commit();
    $conn->close();

    sendJSON([
        'success' => true,
        'message' => 'Role updated successfully',
        'data' => ['id' => (string) $roleId, 'name' => $name]
    ]);

} catch (Exception $e) {
    sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
}
?>

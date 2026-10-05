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
 * POST/DELETE /api/roles-delete.php
 * Delete a role
 * Body: { id }
 */

require_once __DIR__ . '/../../config.php';

try {
    if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'DELETE'])) {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }

    $body = json_decode(file_get_contents('php://input'), true);

    if (!$body) {
        sendJSON(['success' => false, 'error' => 'Invalid JSON body'], 400);
    }

    $roleId = (int) ($body['id'] ?? 0);

    if (!$roleId) {
        sendJSON(['success' => false, 'error' => 'Role ID is required'], 400);
    }

    $conn = getDB();

    $checkStmt = $conn->prepare("SELECT id, name, description, is_default FROM roles WHERE id = ?");
    $checkStmt->bind_param('i', $roleId);
    $checkStmt->execute();
    $role = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if (!$role) {
        sendJSON(['success' => false, 'error' => 'Role not found'], 404);
    }

    if ($role['is_default']) {
        sendJSON(['success' => false, 'error' => 'Built-in roles cannot be deleted'], 400);
    }

    // Check if any users are assigned this role
    $usageStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM user_roles WHERE role_id = ?");
    $usageStmt->bind_param('i', $roleId);
    $usageStmt->execute();
    $assigned = (int) $usageStmt->get_result()->fetch_assoc()['cnt'];
    $usageStmt->close();

    if ($assigned > 0) {
        sendJSON(['success' => false, 'error' => "Cannot delete: role is assigned to $assigned user(s)"], 400);
    }

    $conn->begin_transaction();

    // Remove junction rows (FK cascade may also handle this)
    $delPerms = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
    $delPerms->bind_param('i', $roleId);
    $delPerms->execute();
    $delPerms->close();

    $delStmt = $conn->prepare("DELETE FROM roles WHERE id = ?");
    $delStmt->bind_param('i', $roleId);
    if (!$delStmt->execute()) {
        $conn->rollback();
        sendJSON(['success' => false, 'error' => 'Failed to delete role'], 500);
    }
    $delStmt->close();

    // Audit log
    $userId = 1;
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $oldValues = json_encode(['name' => $role['name'], 'description' => $role['description']]);
    $auditStmt = $conn->prepare(
        "INSERT INTO audit_logs (user_id, action, resource, resource_id, old_values, ip_address, user_agent)
         VALUES (?, 'ROLE_DELETED', 'role', ?, ?, ?, ?)"
    );
    if ($auditStmt) {
        $auditStmt->bind_param('iisss', $userId, $roleId, $oldValues, $ip, $ua);
        $auditStmt->execute();
        $auditStmt->close();
    }

    $conn->commit();
    $conn->close();

    sendJSON([
        'success' => true,
        'message' => 'Role deleted successfully'
    ]);

} catch (Exception $e) {
    sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
}
?>

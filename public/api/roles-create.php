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
 * POST /api/roles-create.php
 * Create a new role with permissions
 * Body: { name, description, permissions: ["booking.view", ...] }
 */

require_once __DIR__ . '/../../config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }

    $body = json_decode(file_get_contents('php://input'), true);

    if (!$body) {
        sendJSON(['success' => false, 'error' => 'Invalid JSON body'], 400);
    }

    $name = trim($body['name'] ?? '');
    $description = $body['description'] ?? '';
    $permissions = $body['permissions'] ?? [];

    if (!$name) {
        sendJSON(['success' => false, 'error' => 'Role name is required'], 400);
    }

    $conn = getDB();

    // Check if name already exists
    $checkStmt = $conn->prepare("SELECT id FROM roles WHERE name = ?");
    $checkStmt->bind_param('s', $name);
    $checkStmt->execute();
    if ($checkStmt->get_result()->num_rows > 0) {
        sendJSON(['success' => false, 'error' => 'A role with this name already exists'], 400);
    }
    $checkStmt->close();

    $conn->begin_transaction();

    // Insert role
    $insertStmt = $conn->prepare("INSERT INTO roles (name, description, is_default) VALUES (?, ?, 0)");
    $insertStmt->bind_param('ss', $name, $description);
    if (!$insertStmt->execute()) {
        $conn->rollback();
        sendJSON(['success' => false, 'error' => 'Failed to create role: ' . $insertStmt->error], 500);
    }
    $roleId = $insertStmt->insert_id;
    $insertStmt->close();

    // Link permissions (auto-create permission rows for unknown names)
    if (is_array($permissions)) {
        foreach ($permissions as $permName) {
            $permName = trim((string) $permName);
            if ($permName === '') continue;

            $parts = explode('.', $permName, 2);
            $resource = $parts[0];
            $action = $parts[1] ?? '';

            // Ensure permission exists
            $permStmt = $conn->prepare(
                "INSERT IGNORE INTO permissions (name, resource, action, description) VALUES (?, ?, ?, ?)"
            );
            $permDesc = ucfirst(str_replace(['.', '_'], ' ', $permName));
            $permStmt->bind_param('ssss', $permName, $resource, $action, $permDesc);
            $permStmt->execute();
            $permStmt->close();

            // Get permission id
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
    $newValues = json_encode(['name' => $name, 'description' => $description, 'permissions' => $permissions]);
    $auditStmt = $conn->prepare(
        "INSERT INTO audit_logs (user_id, action, resource, resource_id, new_values, ip_address, user_agent)
         VALUES (?, 'ROLE_CREATED', 'role', ?, ?, ?, ?)"
    );
    if ($auditStmt) {
        $auditStmt->bind_param('iisss', $userId, $roleId, $newValues, $ip, $ua);
        $auditStmt->execute();
        $auditStmt->close();
    }

    $conn->commit();
    $conn->close();

    sendJSON([
        'success' => true,
        'message' => 'Role created successfully',
        'data' => ['id' => (string) $roleId, 'name' => $name]
    ]);

} catch (Exception $e) {
    sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
}
?>

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
 * GET /api/rbac-audit-logs.php?limit=50
 * Get role/permission audit logs
 */

require_once __DIR__ . '/../../config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }

    $limit = (int) ($_GET['limit'] ?? 50);
    if ($limit <= 0 || $limit > 500) $limit = 50;

    $conn = getDB();

    $sql = "SELECT a.id, a.action, a.resource, a.resource_id, a.old_values, a.new_values,
                   a.ip_address, a.created_at, u.name AS user_name
            FROM audit_logs a
            LEFT JOIN users u ON u.id = a.user_id
            ORDER BY a.id DESC
            LIMIT ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $limit);
    $stmt->execute();
    $result = $stmt->get_result();

    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $newValues = json_decode($row['new_values'] ?? 'null', true);
        $oldValues = json_decode($row['old_values'] ?? 'null', true);

        $logs[] = [
            'id' => (string) $row['id'],
            'action' => $row['action'],
            'targetType' => $row['resource'],
            'targetId' => (string) $row['resource_id'],
            'targetName' => $newValues['name'] ?? $oldValues['name'] ?? '',
            'changedBy' => $row['user_name'] ? (string) $row['user_name'] : 'system',
            'details' => $row['action'] . ' ' . ($newValues['name'] ?? $oldValues['name'] ?? $row['resource']),
            'timestamp' => $row['created_at'],
            'ipAddress' => $row['ip_address'],
        ];
    }

    $stmt->close();
    $conn->close();

    sendJSON(['success' => true, 'data' => $logs]);

} catch (Exception $e) {
    sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
}
?>

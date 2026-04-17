<?php
/**
 * PUT /api/settings-update.php
 * Update a setting
 */

require_once __DIR__ . '/../../config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

try {
    if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'POST'])) {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    $body = json_decode(file_get_contents('php://input'), true);
    
    if (!$body) {
        sendJSON(['success' => false, 'error' => 'Invalid JSON body'], 400);
    }
    
    $category = $body['category'] ?? null;
    $key = $body['key'] ?? null;
    $value = $body['value'] ?? null;
    
    if (!$category || !$key) {
        sendJSON(['success' => false, 'error' => 'Category and key are required'], 400);
    }
    
    $conn = getDB();
    
    // Get current value for audit
    $checkSql = "SELECT id, value FROM settings WHERE category = ? AND `key` = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param('ss', $category, $key);
    $checkStmt->execute();
    $result = $checkStmt->get_result();
    
    if ($result->num_rows === 0) {
        sendJSON(['success' => false, 'error' => 'Setting not found'], 404);
    }
    
    $setting = $result->fetch_assoc();
    $settingId = $setting['id'];
    $oldValue = $setting['value'];
    $checkStmt->close();
    
    // Update setting
    $updateSql = "UPDATE settings SET value = ? WHERE category = ? AND `key` = ?";
    $updateStmt = $conn->prepare($updateSql);
    
    if (!$updateStmt) {
        sendJSON(['success' => false, 'error' => 'Query prepare failed'], 500);
    }
    
    $updateStmt->bind_param('sss', $value, $category, $key);
    
    if (!$updateStmt->execute()) {
        sendJSON(['success' => false, 'error' => 'Failed to update setting'], 400);
    }
    $updateStmt->close();
    
    // Log to audit trail
    $userId = 1; // Would come from auth in production
    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    
    $auditSql = "INSERT INTO settings_audit (setting_id, category, `key`, old_value, new_value, changed_by, ip_address, user_agent)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    $auditStmt = $conn->prepare($auditSql);
    $auditStmt->bind_param('issssiss', $settingId, $category, $key, $oldValue, $value, $userId, $ipAddress, $userAgent);
    $auditStmt->execute();
    $auditStmt->close();
    
    $conn->close();
    
    sendJSON([
        'success' => true,
        'message' => 'Setting updated successfully',
        'data' => [
            'category' => $category,
            'key' => $key,
            'value' => $value
        ]
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

<?php
/**
 * GET /api/settings-get.php
 * Get a single setting
 */

require_once __DIR__ . '/../../config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    $category = $_GET['category'] ?? null;
    $key = $_GET['key'] ?? null;
    
    if (!$category || !$key) {
        sendJSON(['success' => false, 'error' => 'Category and key are required'], 400);
    }
    
    $conn = getDB();
    
    $sql = "SELECT id, category, `key`, value, type, description, is_public, updated_at 
            FROM settings 
            WHERE category = ? AND `key` = ?
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $category, $key);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        sendJSON(['success' => false, 'error' => 'Setting not found'], 404);
    }
    
    $row = $result->fetch_assoc();
    $stmt->close();
    $conn->close();
    
    $setting = [
        'id' => (int) $row['id'],
        'category' => $row['category'],
        'key' => $row['key_name'],
        'value' => $row['value'],
        'type' => $row['type'],
        'description' => $row['description'],
        'is_public' => (bool) $row['is_public'],
        'updated_at' => $row['updated_at']
    ];
    
    sendJSON([
        'success' => true,
        'data' => $setting
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

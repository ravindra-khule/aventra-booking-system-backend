<?php
/**
 * GET /api/settings-list.php
 * Get all settings or settings by category
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
    $conn = getDB();
    
    if ($category) {
        // Get settings for specific category
        $sql = "SELECT id, category, `key`, value, type, description, is_public, updated_at 
                FROM settings 
                WHERE category = ?
                ORDER BY `key` ASC";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param('s', $category);
        $stmt->execute();
        $result = $stmt->get_result();
        $stmt->close();
    } else {
        // Get all settings grouped by category
        $sql = "SELECT id, category, `key`, value, type, description, is_public, updated_at 
                FROM settings 
                ORDER BY category, `key` ASC";
        
        $result = $conn->query($sql);
    }
    
    if (!$result) {
        sendJSON(['success' => false, 'error' => 'Query failed: ' . $conn->error], 500);
    }
    
    $settings = [];
    $grouped = [];
    
    while ($row = $result->fetch_assoc()) {
        $settings[] = [
            'id' => (int) $row['id'],
            'category' => $row['category'],
            'key' => $row['key'],
            'value' => $row['value'],
            'type' => $row['type'],
            'description' => $row['description'],
            'is_public' => (bool) $row['is_public'],
            'updated_at' => $row['updated_at']
        ];
        
        // Group by category
        if (!isset($grouped[$row['category']])) {
            $grouped[$row['category']] = [];
        }
        $grouped[$row['category']][$row['key']] = $row['value'];
    }
    
    $conn->close();
    
    sendJSON([
        'success' => true,
        'data' => $settings,
        'grouped' => $grouped
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

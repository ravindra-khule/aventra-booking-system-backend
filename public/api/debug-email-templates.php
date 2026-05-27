<?php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../config.php';

try {
    $conn = getDB();
    
    // Simple test query
    $sql = "SELECT 
                id,
                name,
                description,
                category,
                status,
                version,
                is_default,
                tags,
                usage_count,
                created_by,
                created_date,
                last_modified,
                last_modified_by
            FROM email_templates 
            ORDER BY created_date DESC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        sendJSON(['success' => false, 'error' => 'Query failed: ' . $conn->error]);
    }
    
    $templates = [];
    while ($row = $result->fetch_assoc()) {
        // Parse JSON fields
        $tags = $row['tags'] ? json_decode($row['tags'], true) : [];
        
        $templates[] = [
            'id' => $row['id'],
            'name' => $row['name'],
            'description' => $row['description'],
            'category' => $row['category'],
            'status' => $row['status'],
            'version' => (int) $row['version'],
            'isDefault' => (bool) $row['is_default'],
            'tags' => $tags,
            'usageCount' => (int) $row['usage_count'],
            'createdBy' => $row['created_by'],
            'createdDate' => $row['created_date'],
            'lastModified' => $row['last_modified'],
            'lastModifiedBy' => $row['last_modified_by'],
            'content' => [] // Add empty content for now
        ];
    }
    
    sendJSON([
        'success' => true,
        'data' => $templates,
        'count' => count($templates)
    ]);
    
} catch (Exception $e) {
    sendJSON(['success' => false, 'error' => $e->getMessage()]);
}
?>

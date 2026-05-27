<?php
header('Content-Type: application/json; charset=UTF-8');

require_once __DIR__ . '/../../config.php';

try {
    $conn = getDB();
    
    // Test JSON data
    $testContent = [
        [
            'language' => 'en',
            'subject' => 'Test Subject',
            'htmlContent' => '<h1>Test HTML</h1>',
            'textContent' => 'Test text content'
        ]
    ];
    
    $contentJson = json_encode($testContent);
    echo "Generated JSON: " . $contentJson . "\n";
    echo "JSON valid: " . (json_decode($contentJson) !== null ? 'true' : 'false') . "\n";
    
    // Test inserting into versions table
    $templateId = 'test-template';
    $version = 1;
    $changeDesc = 'Test update';
    $createdBy = 'test-user';
    $now = date('Y-m-d H:i:s');
    
    $sql = "INSERT INTO email_template_versions (template_id, version, content, change_description, created_by, created_date)
            VALUES (?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ssisss", $templateId, $version, $contentJson, $changeDesc, $createdBy, $now);
    
    if ($stmt->execute()) {
        echo "Insert successful\n";
        
        // Clean up test record
        $deleteSql = "DELETE FROM email_template_versions WHERE template_id = ?";
        $deleteStmt = $conn->prepare($deleteSql);
        $deleteStmt->bind_param("s", $templateId);
        $deleteStmt->execute();
        
        sendJSON(['success' => true, 'message' => 'JSON test passed']);
    } else {
        sendJSON(['success' => false, 'error' => 'Insert failed: ' . $stmt->error]);
    }
    
} catch (Exception $e) {
    sendJSON(['success' => false, 'error' => $e->getMessage()]);
}
?>

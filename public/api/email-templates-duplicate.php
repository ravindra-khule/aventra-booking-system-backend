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
 * POST /api/email-templates-duplicate.php
 * Duplicate an email template
 */

require_once __DIR__ . '/../../config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }

    $body = json_decode(file_get_contents('php://input'), true);

    if (!$body || !isset($body['id']) || !isset($body['newName']) || !isset($body['createdBy'])) {
        sendJSON(['success' => false, 'error' => 'Template ID, newName, and createdBy are required'], 400);
    }

    $conn = getDB();
    $sourceId = $body['id'];
    $newName = $body['newName'];
    $createdBy = $body['createdBy'];
    $now = date('Y-m-d H:i:s');

    // Get source template
    $sourceSql = "SELECT * FROM email_templates WHERE id = ?";
    $sourceStmt = $conn->prepare($sourceSql);
    $sourceStmt->bind_param("s", $sourceId);
    $sourceStmt->execute();
    $sourceResult = $sourceStmt->get_result();

    if ($sourceResult->num_rows === 0) {
        sendJSON(['success' => false, 'error' => 'Template not found'], 404);
    }

    $sourceTemplate = $sourceResult->fetch_assoc();

    // Generate new ID
    $newTemplateId = 'template-' . uniqid();

    // Create duplicate template
    $createSql = "INSERT INTO email_templates (id, name, description, category, status, version, is_default, tags, usage_count, created_by, created_date)
                  VALUES (?, ?, ?, ?, ?, 1, 0, ?, 0, ?, ?)";

    $createStmt = $conn->prepare($createSql);
    $description = "Copy of " . $sourceTemplate['name'];
    $status = 'DRAFT';
    $tags = $sourceTemplate['tags'];
    $category = $sourceTemplate['category'];

    $createStmt->bind_param("ssssssss", $newTemplateId, $newName, $description, $category, $status, $tags, $createdBy, $now);

    if (!$createStmt->execute()) {
        throw new Exception("Create duplicate failed: " . $createStmt->error);
    }

    // Copy content
    $contentSql = "SELECT * FROM email_template_content WHERE template_id = ?";
    $contentStmt = $conn->prepare($contentSql);
    $contentStmt->bind_param("s", $sourceId);
    $contentStmt->execute();
    $contentResult = $contentStmt->get_result();

    while ($contentRow = $contentResult->fetch_assoc()) {
        $insertContentSql = "INSERT INTO email_template_content (template_id, language, subject, html_content, text_content)
                             VALUES (?, ?, ?, ?, ?)";
        
        $insertContentStmt = $conn->prepare($insertContentSql);
        $language = $contentRow['language'];
        $subject = $contentRow['subject'];
        $htmlContent = $contentRow['html_content'];
        $textContent = $contentRow['text_content'];
        
        $insertContentStmt->bind_param("sssss", $newTemplateId, $language, $subject, $htmlContent, $textContent);
        $insertContentStmt->execute();
    }

    // Fetch all content for the new template to create version
    $fetchContentSql = "SELECT language, subject, html_content, text_content FROM email_template_content WHERE template_id = ?";
    $fetchContentStmt = $conn->prepare($fetchContentSql);
    $fetchContentStmt->bind_param("s", $newTemplateId);
    $fetchContentStmt->execute();
    $fetchContentResult = $fetchContentStmt->get_result();

    $contentArray = [];
    while ($row = $fetchContentResult->fetch_assoc()) {
        $contentArray[] = [
            'language' => $row['language'],
            'subject' => $row['subject'],
            'htmlContent' => $row['html_content'],
            'textContent' => $row['text_content']
        ];
    }

    // Skip version creation to avoid JSON column issues (similar to update API)
    // Version history can be implemented later once JSON column issue is resolved

    sendJSON([
        'success' => true,
        'data' => [
            'id' => $newTemplateId,
            'message' => 'Template duplicated successfully'
        ]
    ], 201);

} catch (Exception $e) {
    debugLog('Email Template Duplicate Error', ['error' => $e->getMessage()]);
    sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
}
?>

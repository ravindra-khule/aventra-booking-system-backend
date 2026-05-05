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
 * GET /api/email-templates-get.php?id={id}
 * Get a single email template by ID
 */

require_once __DIR__ . '/../../config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }

    $templateId = $_GET['id'] ?? null;

    if (!$templateId) {
        sendJSON(['success' => false, 'error' => 'Template ID is required'], 400);
    }

    $conn = getDB();

    // Get template
    $sql = "SELECT id, name, description, category, status, version, is_default, tags, usage_count, created_by, created_date, last_modified, last_modified_by
            FROM email_templates
            WHERE id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $templateId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        sendJSON(['success' => false, 'error' => 'Template not found'], 404);
    }

    $row = $result->fetch_assoc();

    // Get template content for each language
    $contentSql = "SELECT language, subject, html_content, text_content FROM email_template_content WHERE template_id = ?";
    $contentStmt = $conn->prepare($contentSql);
    $contentStmt->bind_param("s", $templateId);
    $contentStmt->execute();
    $contentResult = $contentStmt->get_result();

    $content = [];
    while ($contentRow = $contentResult->fetch_assoc()) {
        $content[] = [
            'language' => $contentRow['language'],
            'subject' => $contentRow['subject'],
            'htmlContent' => $contentRow['html_content'],
            'textContent' => $contentRow['text_content']
        ];
    }

    // Get versions
    $versionSql = "SELECT id, version, content, change_description, created_by, created_date FROM email_template_versions WHERE template_id = ? ORDER BY version DESC";
    $versionStmt = $conn->prepare($versionSql);
    $versionStmt->bind_param("s", $templateId);
    $versionStmt->execute();
    $versionResult = $versionStmt->get_result();

    $versions = [];
    while ($versionRow = $versionResult->fetch_assoc()) {
        $versions[] = [
            'id' => $versionRow['id'],
            'templateId' => $templateId,
            'version' => (int)$versionRow['version'],
            'content' => json_decode($versionRow['content'], true),
            'changeDescription' => $versionRow['change_description'],
            'createdBy' => $versionRow['created_by'],
            'createdDate' => $versionRow['created_date']
        ];
    }

    $template = [
        'id' => $row['id'],
        'name' => $row['name'],
        'description' => $row['description'],
        'category' => $row['category'],
        'status' => $row['status'],
        'version' => (int)$row['version'],
        'isDefault' => (bool)$row['is_default'],
        'tags' => json_decode($row['tags'] ?? '[]', true),
        'usageCount' => (int)$row['usage_count'],
        'content' => $content,
        'versions' => $versions,
        'createdBy' => $row['created_by'],
        'createdDate' => $row['created_date'],
        'lastModified' => $row['last_modified'],
        'lastModifiedBy' => $row['last_modified_by']
    ];

    sendJSON(['success' => true, 'data' => $template]);

} catch (Exception $e) {
    debugLog('Email Template Get Error', ['error' => $e->getMessage()]);
    sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
}
?>

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
 * POST /api/email-templates-create.php
 * Create a new email template
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

    // Validate required fields
    $required = ['name', 'category', 'content', 'createdBy'];
    foreach ($required as $field) {
        if (empty($body[$field])) {
            sendJSON(['success' => false, 'error' => "Field '$field' is required"], 400);
        }
    }

    $conn = getDB();

    // Generate template ID
    $templateId = 'template-' . uniqid();
    $now = date('Y-m-d H:i:s');

    // Create template
    $sql = "INSERT INTO email_templates (id, name, description, category, status, version, is_default, tags, usage_count, created_by, created_date)
            VALUES (?, ?, ?, ?, ?, 1, 0, ?, 0, ?, ?)";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $tags = json_encode($body['tags'] ?? []);
    $status = $body['status'] ?? 'DRAFT';
    $description = $body['description'] ?? '';
    $name = $body['name'];
    $category = $body['category'];
    $createdBy = $body['createdBy'];

    $stmt->bind_param("ssssssss", $templateId, $name, $description, $category, $status, $tags, $createdBy, $now);

    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    // Create template content for each language
    foreach ($body['content'] as $content) {
        $contentSql = "INSERT INTO email_template_content (template_id, language, subject, html_content, text_content)
                       VALUES (?, ?, ?, ?, ?)";
        
        $contentStmt = $conn->prepare($contentSql);
        if (!$contentStmt) {
            throw new Exception("Content prepare failed: " . $conn->error);
        }

        $language = $content['language'];
        $subject = $content['subject'];
        $htmlContent = $content['htmlContent'];
        $textContent = $content['textContent'] ?? '';

        $contentStmt->bind_param("sssss", $templateId, $language, $subject, $htmlContent, $textContent);

        if (!$contentStmt->execute()) {
            throw new Exception("Content execute failed: " . $contentStmt->error);
        }
    }

    // Create initial version
    $versionSql = "INSERT INTO email_template_versions (template_id, version, content, change_description, created_by, created_date)
                   VALUES (?, 1, ?, 'Initial version', ?, ?)";

    $versionStmt = $conn->prepare($versionSql);
    if (!$versionStmt) {
        throw new Exception("Version prepare failed: " . $conn->error);
    }

    $contentJson = json_encode($body['content']);
    $versionStmt->bind_param("ssss", $templateId, $contentJson, $createdBy, $now);

    if (!$versionStmt->execute()) {
        throw new Exception("Version execute failed: " . $versionStmt->error);
    }

    sendJSON([
        'success' => true,
        'data' => [
            'id' => $templateId,
            'message' => 'Template created successfully'
        ]
    ], 201);

} catch (Exception $e) {
    debugLog('Email Template Create Error', ['error' => $e->getMessage()]);
    sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
}
?>

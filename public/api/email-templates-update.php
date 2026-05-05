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
 * PUT /api/email-templates-update.php
 * Update an email template
 */

require_once __DIR__ . '/../../config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }

    $body = json_decode(file_get_contents('php://input'), true);

    if (!$body || !isset($body['id'])) {
        sendJSON(['success' => false, 'error' => 'Template ID is required'], 400);
    }

    $conn = getDB();
    $templateId = $body['id'];
    $now = date('Y-m-d H:i:s');

    // Check if template exists
    $checkSql = "SELECT version FROM email_templates WHERE id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("s", $templateId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows === 0) {
        sendJSON(['success' => false, 'error' => 'Template not found'], 404);
    }

    $templateRow = $checkResult->fetch_assoc();
    $newVersion = $templateRow['version'] + 1;

    // Update template metadata
    $updateSql = "UPDATE email_templates SET last_modified = ?, last_modified_by = ?";
    $params = [$now, $body['lastModifiedBy'] ?? 'system'];
    $types = "ss";

    if (isset($body['name'])) {
        $updateSql .= ", name = ?";
        $params[] = $body['name'];
        $types .= "s";
    }

    if (isset($body['description'])) {
        $updateSql .= ", description = ?";
        $params[] = $body['description'];
        $types .= "s";
    }

    if (isset($body['status'])) {
        $updateSql .= ", status = ?";
        $params[] = $body['status'];
        $types .= "s";
    }

    if (isset($body['tags'])) {
        $updateSql .= ", tags = ?";
        $params[] = json_encode($body['tags']);
        $types .= "s";
    }

    $updateSql .= " WHERE id = ?";
    $params[] = $templateId;
    $types .= "s";

    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param($types, ...$params);

    if (!$updateStmt->execute()) {
        throw new Exception("Update failed: " . $updateStmt->error);
    }

    // If content is being updated, create new version and update content
    if (isset($body['content'])) {
        // Update content
        $deleteSql = "DELETE FROM email_template_content WHERE template_id = ?";
        $deleteStmt = $conn->prepare($deleteSql);
        $deleteStmt->bind_param("s", $templateId);
        $deleteStmt->execute();

        // Insert new content
        foreach ($body['content'] as $content) {
            $contentSql = "INSERT INTO email_template_content (template_id, language, subject, html_content, text_content)
                           VALUES (?, ?, ?, ?, ?)";
            
            $contentStmt = $conn->prepare($contentSql);
            $language = $content['language'];
            $subject = $content['subject'];
            $htmlContent = $content['htmlContent'];
            $textContent = $content['textContent'] ?? '';
            
            $contentStmt->bind_param("sssss", $templateId, $language, $subject, $htmlContent, $textContent);

            if (!$contentStmt->execute()) {
                throw new Exception("Content update failed: " . $contentStmt->error);
            }
        }

        // Create new version
        $versionSql = "INSERT INTO email_template_versions (template_id, version, content, change_description, created_by, created_date)
                       VALUES (?, ?, ?, ?, ?, ?)";

        $versionStmt = $conn->prepare($versionSql);
        $contentJson = json_encode($body['content']);
        $changeDesc = $body['changeDescription'] ?? 'Template updated';
        $lastModifiedBy = $body['lastModifiedBy'] ?? 'system';

        $versionStmt->bind_param("ssisss", $templateId, $newVersion, $contentJson, $changeDesc, $lastModifiedBy, $now);

        if (!$versionStmt->execute()) {
            throw new Exception("Version creation failed: " . $versionStmt->error);
        }

        // Update version number
        $versionUpdateSql = "UPDATE email_templates SET version = ? WHERE id = ?";
        $versionUpdateStmt = $conn->prepare($versionUpdateSql);
        $versionUpdateStmt->bind_param("is", $newVersion, $templateId);
        $versionUpdateStmt->execute();
    }

    sendJSON([
        'success' => true,
        'data' => [
            'id' => $templateId,
            'message' => 'Template updated successfully'
        ]
    ]);

} catch (Exception $e) {
    debugLog('Email Template Update Error', ['error' => $e->getMessage()]);
    sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
}
?>

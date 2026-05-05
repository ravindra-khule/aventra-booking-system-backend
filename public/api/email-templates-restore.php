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
 * POST /api/email-templates-restore.php
 * Restore a previous version of a template
 */

require_once __DIR__ . '/../../config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }

    $body = json_decode(file_get_contents('php://input'), true);

    if (!$body || !isset($body['templateId']) || !isset($body['versionNumber']) || !isset($body['restoredBy'])) {
        sendJSON(['success' => false, 'error' => 'templateId, versionNumber, and restoredBy are required'], 400);
    }

    $conn = getDB();
    $templateId = $body['templateId'];
    $versionNumber = (int)$body['versionNumber'];
    $restoredBy = $body['restoredBy'];
    $now = date('Y-m-d H:i:s');

    // Get the version to restore
    $versionSql = "SELECT content FROM email_template_versions WHERE template_id = ? AND version = ?";
    $versionStmt = $conn->prepare($versionSql);
    $versionStmt->bind_param("si", $templateId, $versionNumber);
    $versionStmt->execute();
    $versionResult = $versionStmt->get_result();

    if ($versionResult->num_rows === 0) {
        sendJSON(['success' => false, 'error' => 'Version not found'], 404);
    }

    $versionRow = $versionResult->fetch_assoc();
    $content = json_decode($versionRow['content'], true);

    // Get current version
    $currentSql = "SELECT version FROM email_templates WHERE id = ?";
    $currentStmt = $conn->prepare($currentSql);
    $currentStmt->bind_param("s", $templateId);
    $currentStmt->execute();
    $currentResult = $currentStmt->get_result();
    $currentRow = $currentResult->fetch_assoc();
    $newVersion = $currentRow['version'] + 1;

    // Delete current content
    $deleteContentSql = "DELETE FROM email_template_content WHERE template_id = ?";
    $deleteContentStmt = $conn->prepare($deleteContentSql);
    $deleteContentStmt->bind_param("s", $templateId);
    $deleteContentStmt->execute();

    // Restore content
    foreach ($content as $contentItem) {
        $insertContentSql = "INSERT INTO email_template_content (template_id, language, subject, html_content, text_content)
                             VALUES (?, ?, ?, ?, ?)";
        
        $insertContentStmt = $conn->prepare($insertContentSql);
        $language = $contentItem['language'];
        $subject = $contentItem['subject'];
        $htmlContent = $contentItem['htmlContent'];
        $textContent = $contentItem['textContent'] ?? '';
        
        $insertContentStmt->bind_param("sssss", $templateId, $language, $subject, $htmlContent, $textContent);
        $insertContentStmt->execute();
    }

    // Create new version record
    $createVersionSql = "INSERT INTO email_template_versions (template_id, version, content, change_description, created_by, created_date)
                        VALUES (?, ?, ?, ?, ?, ?)";

    $createVersionStmt = $conn->prepare($createVersionSql);
    $contentJson = json_encode($content);
    $changeDesc = "Restored to version $versionNumber";

    $createVersionStmt->bind_param("ssisss", $templateId, $newVersion, $contentJson, $changeDesc, $restoredBy, $now);

    if (!$createVersionStmt->execute()) {
        throw new Exception("Version creation failed: " . $createVersionStmt->error);
    }

    // Update template version number
    $updateSql = "UPDATE email_templates SET version = ?, last_modified = ?, last_modified_by = ? WHERE id = ?";
    $updateStmt = $conn->prepare($updateSql);
    $updateStmt->bind_param("isss", $newVersion, $now, $restoredBy, $templateId);
    $updateStmt->execute();

    sendJSON([
        'success' => true,
        'data' => [
            'id' => $templateId,
            'message' => "Successfully restored to version $versionNumber"
        ]
    ]);

} catch (Exception $e) {
    debugLog('Email Template Restore Error', ['error' => $e->getMessage()]);
    sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
}
?>

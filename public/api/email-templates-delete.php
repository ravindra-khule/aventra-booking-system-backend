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
 * DELETE /api/email-templates-delete.php
 * Delete an email template
 */

require_once __DIR__ . '/../../config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }

    $body = json_decode(file_get_contents('php://input'), true);

    if (!$body || !isset($body['id'])) {
        sendJSON(['success' => false, 'error' => 'Template ID is required'], 400);
    }

    $conn = getDB();
    $templateId = $body['id'];

    // Check if template exists
    $checkSql = "SELECT id FROM email_templates WHERE id = ?";
    $checkStmt = $conn->prepare($checkSql);
    $checkStmt->bind_param("s", $templateId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();

    if ($checkResult->num_rows === 0) {
        sendJSON(['success' => false, 'error' => 'Template not found'], 404);
    }

    // Delete template content
    $deleteContentSql = "DELETE FROM email_template_content WHERE template_id = ?";
    $deleteContentStmt = $conn->prepare($deleteContentSql);
    $deleteContentStmt->bind_param("s", $templateId);
    $deleteContentStmt->execute();

    // Delete template versions
    $deleteVersionsSql = "DELETE FROM email_template_versions WHERE template_id = ?";
    $deleteVersionsStmt = $conn->prepare($deleteVersionsSql);
    $deleteVersionsStmt->bind_param("s", $templateId);
    $deleteVersionsStmt->execute();

    // Delete template
    $deleteSql = "DELETE FROM email_templates WHERE id = ?";
    $deleteStmt = $conn->prepare($deleteSql);
    $deleteStmt->bind_param("s", $templateId);

    if (!$deleteStmt->execute()) {
        throw new Exception("Delete failed: " . $deleteStmt->error);
    }

    sendJSON([
        'success' => true,
        'data' => [
            'id' => $templateId,
            'message' => 'Template deleted successfully'
        ]
    ]);

} catch (Exception $e) {
    debugLog('Email Template Delete Error', ['error' => $e->getMessage()]);
    sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
}
?>

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
 * GET /api/email-templates-versions.php?id={id}
 * Get all versions of a template
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

    // Get all versions
    $sql = "SELECT id, version, content, change_description, created_by, created_date 
            FROM email_template_versions 
            WHERE template_id = ? 
            ORDER BY version DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $templateId);
    $stmt->execute();
    $result = $stmt->get_result();

    $versions = [];
    while ($row = $result->fetch_assoc()) {
        $versions[] = [
            'id' => $row['id'],
            'templateId' => $templateId,
            'version' => (int)$row['version'],
            'content' => json_decode($row['content'], true),
            'changeDescription' => $row['change_description'],
            'createdBy' => $row['created_by'],
            'createdDate' => $row['created_date']
        ];
    }

    sendJSON([
        'success' => true,
        'data' => $versions,
        'count' => count($versions)
    ]);

} catch (Exception $e) {
    debugLog('Email Template Versions Error', ['error' => $e->getMessage()]);
    sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
}
?>

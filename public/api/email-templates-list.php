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
 * GET /api/email-templates-list.php
 * Get all email templates with optional filters
 */

require_once __DIR__ . '/../../config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }

    $conn = getDB();

    // Get filter parameters
    $searchQuery = $_GET['search'] ?? null;
    $category = $_GET['category'] ?? null;
    $status = $_GET['status'] ?? null;
    $language = $_GET['language'] ?? null;

    // Build base query
    $sql = "SELECT id, name, description, category, status, version, is_default, tags, usage_count, created_by, created_date, last_modified, last_modified_by
            FROM email_templates
            WHERE 1=1";

    $params = [];
    $types = "";

    // Add filters
    if ($searchQuery) {
        $sql .= " AND (name LIKE ? OR description LIKE ?)";
        $searchTerm = "%$searchQuery%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= "ss";
    }

    if ($category) {
        $sql .= " AND category = ?";
        $params[] = $category;
        $types .= "s";
    }

    if ($status) {
        $sql .= " AND status = ?";
        $params[] = $status;
        $types .= "s";
    }

    $sql .= " ORDER BY created_date DESC";

    // Prepare and execute query
    $stmt = $conn->prepare($sql);
    
    if ($params) {
        $stmt->bind_param($types, ...$params);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $templates = [];
    while ($row = $result->fetch_assoc()) {
        // Get template content for each language
        $contentSql = "SELECT language, subject, html_content, text_content FROM email_template_content WHERE template_id = ?";
        $contentStmt = $conn->prepare($contentSql);
        $contentStmt->bind_param("s", $row['id']);
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

        $templates[] = [
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
            'createdBy' => $row['created_by'],
            'createdDate' => $row['created_date'],
            'lastModified' => $row['last_modified'],
            'lastModifiedBy' => $row['last_modified_by']
        ];
    }

    sendJSON([
        'success' => true,
        'data' => $templates,
        'count' => count($templates)
    ]);

} catch (Exception $e) {
    debugLog('Email Templates List Error', ['error' => $e->getMessage()]);
    sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
}
?>

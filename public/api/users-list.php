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
 * GET /api/users-list.php
 * Get all users with optional filters (search, role, status)
 */

require_once __DIR__ . '/../../config.php';

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    $conn = getDB();
    
    // Get filter parameters
    $search = $_GET['search'] ?? null;
    $role = $_GET['role'] ?? null;
    $status = $_GET['status'] ?? null;
    
    // Build query with filters
    $sql = "SELECT 
                id,
                name,
                email,
                role,
                status,
                phone,
                company_name,
                avatar_url,
                two_factor_enabled,
                created_at,
                updated_at,
                last_login
            FROM users 
            WHERE deleted_at IS NULL";
    
    $params = [];
    $types = '';
    
    // Add search filter
    if ($search) {
        $sql .= " AND (name LIKE ? OR email LIKE ?)";
        $searchTerm = "%$search%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $types .= 'ss';
    }
    
    // Add role filter
    if ($role && $role !== 'ALL') {
        $sql .= " AND role = ?";
        $params[] = $role;
        $types .= 's';
    }
    
    // Add status filter
    if ($status && $status !== 'ALL') {
        $sql .= " AND status = ?";
        $params[] = $status;
        $types .= 's';
    }
    
    $sql .= " ORDER BY created_at DESC";
    
    // Prepare and execute query
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        sendJSON(['success' => false, 'error' => 'Query prepare failed: ' . $conn->error], 500);
    }
    
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    
    if (!$stmt->execute()) {
        sendJSON(['success' => false, 'error' => 'Query failed: ' . $stmt->error], 500);
    }
    
    $result = $stmt->get_result();
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => (string) $row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'role' => $row['role'],
            'status' => $row['status'],
            'phone' => $row['phone'],
            'avatar' => $row['avatar_url'],
            'twoFactorEnabled' => (bool) $row['two_factor_enabled'],
            'createdAt' => $row['created_at'],
            'updatedAt' => $row['updated_at'],
            'lastLogin' => $row['last_login']
        ];
    }
    
    $stmt->close();
    $conn->close();
    
    sendJSON([
        'success' => true,
        'data' => $users,
        'total' => count($users)
    ]);
    
} catch (Exception $e) {
    sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
}
?>

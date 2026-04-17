<?php
/**
 * GET /api/users.php
 * Get all users (admin only)
 */

require_once __DIR__ . '/../../config.php';

try {
    // Only allow GET requests
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    // TODO: In production, verify token and check if user is admin
    // For now, allowing all access. Add auth middleware later:
    // $token = $_SERVER['HTTP_AUTHORIZATION'] ?? null;
    // if (!$token || !verifyToken($token)) {
    //     sendJSON(['success' => false, 'error' => 'Unauthorized'], 401);
    // }
    
    $conn = getDB();
    
    // Get all users (excluding deleted ones)
    $sql = "SELECT 
                id,
                name,
                email,
                role,
                status,
                phone,
                company_name,
                created_at,
                last_login
            FROM users 
            WHERE deleted_at IS NULL
            ORDER BY created_at DESC";
    
    $result = $conn->query($sql);
    
    if (!$result) {
        sendJSON(['success' => false, 'error' => 'Query failed: ' . $conn->error], 500);
    }
    
    $users = [];
    while ($row = $result->fetch_assoc()) {
        $users[] = [
            'id' => (string) $row['id'],
            'name' => $row['name'],
            'email' => $row['email'],
            'role' => $row['role'],
            'status' => $row['status'],
            'phone' => $row['phone'],
            'companyName' => $row['company_name'],
            'createdAt' => $row['created_at'],
            'lastLogin' => $row['last_login']
        ];
    }
    
    $conn->close();
    
    sendJSON([
        'success' => true,
        'data' => $users,
        'total' => count($users)
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

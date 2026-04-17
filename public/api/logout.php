<?php
/**
 * POST /api/logout.php
 * Logout user (invalidate token)
 */

require_once __DIR__ . '/../../config.php';

try {
    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendJSON(['success' => false, 'error' => 'Method not allowed'], 405);
    }
    
    // Get token from Authorization header
    $headers = getallheaders();
    $token = $headers['Authorization'] ?? null;
    
    if (!$token) {
        sendJSON(['success' => false, 'error' => 'Token not provided'], 400);
    }
    
    // Remove "Bearer " prefix if present
    $token = str_replace('Bearer ', '', $token);
    
    // In a real application, you would:
    // 1. Store token in blocklist (Redis, database)
    // 2. Or use JWT with expiration
    // For now, just return success
    
    sendJSON([
        'success' => true,
        'message' => 'Logged out successfully'
    ]);
    
} catch (Exception $e) {
    sendJSON([
        'success' => false,
        'error' => $e->getMessage()
    ], 500);
}
?>

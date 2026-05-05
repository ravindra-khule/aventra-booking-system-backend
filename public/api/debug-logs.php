<?php
/**
 * GET /api/debug-logs.php
 * View the debug log file
 * 
 * Security: Access protected with a simple token
 * IMPORTANT: Remove or restrict this endpoint in production!
 * 
 * Usage: http://your-domain.com/api/debug-logs.php?token=debug123&lines=100
 */

header('Content-Type: text/plain; charset=UTF-8');

// Simple security check - change this token
$allowedToken = 'debug123';
$queryToken = $_GET['token'] ?? null;

if ($queryToken !== $allowedToken) {
    http_response_code(403);
    die("Access denied. Invalid or missing token.\n");
}

require_once __DIR__ . '/../../config.php';

$lines = (int)($_GET['lines'] ?? 50);
if ($lines < 1 || $lines > 500) {
    $lines = 50;
}

echo "╔════════════════════════════════════════════════════════╗\n";
echo "║              AVENTRA LOGIN DEBUG LOGS                  ║\n";
echo "╚════════════════════════════════════════════════════════╝\n\n";

echo "Last $lines log entries:\n";
echo "────────────────────────────────────────────────────────\n\n";

echo getDebugLogs($lines);

echo "\n────────────────────────────────────────────────────────\n";
echo "View more logs: ?lines=100&token=debug123\n";
echo "⚠️  Remember to remove this endpoint in production!\n";
?>

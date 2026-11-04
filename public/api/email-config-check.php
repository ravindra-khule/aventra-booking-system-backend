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
 * GET /api/email-config-check.php
 * Diagnostic: shows the resolved email config (password masked) and which
 * Email settings exist in the DB. Use this to debug why emails are
 * "simulated" instead of sent.
 */

require_once __DIR__ . '/../../config.php';
$__mailPath = __DIR__ . '/../../lib/MailService.php';
if (!is_file($__mailPath)) {
    sendJSON(['success' => false, 'error' => 'MailService library is not installed on the server'], 500);
}
require_once $__mailPath;

try {
    $conn = getDB();
    $config = getEmailConfig($conn);

    // Raw Email rows from the settings table
    $dbRows = [];
    $tableExists = false;
    $res = @$conn->query("SELECT `key`, `value`, updated_at FROM settings WHERE category = 'Email'");
    if ($res) {
        $tableExists = true;
        while ($row = $res->fetch_assoc()) {
            $dbRows[$row['key']] = [
                'value' => ($row['key'] === 'smtp_password' || stripos($row['key'], 'key') !== false)
                    ? ($row['value'] !== '' ? '***set***' : '')
                    : $row['value'],
                'is_set' => $row['value'] !== '' && $row['value'] !== null,
                'updated_at' => $row['updated_at'],
            ];
        }
    } else {
        // Check whether the settings table exists at all
        $check = $conn->query("SHOW TABLES LIKE 'settings'");
        $tableExists = ($check && $check->num_rows > 0);
    }

    // Env vars
    $envSet = [];
    foreach (['SMTP_HOST','SMTP_PORT','SMTP_USER','SMTP_PASS','MAIL_FROM_EMAIL','MAIL_FROM_NAME','MAIL_REPLY_TO'] as $e) {
        $envSet[$e] = getenv($e) !== false && getenv($e) !== '';
    }

    sendJSON([
        'success' => true,
        'data' => [
            'configured' => $config['configured'],
            'missing' => array_values(array_filter([
                $config['host'] === '' ? 'smtp_host' : null,
                $config['username'] === '' ? 'smtp_username' : null,
                $config['password'] === '' ? 'smtp_password' : null,
                $config['from_email'] === '' ? 'from_email' : null,
            ])),
            'resolved' => [
                'host' => $config['host'],
                'port' => $config['port'],
                'username' => $config['username'],
                'password' => $config['password'] !== '' ? '***set***' : '',
                'from_email' => $config['from_email'],
                'from_name' => $config['from_name'],
                'reply_to' => $config['reply_to'],
            ],
            'settings_table_exists' => $tableExists,
            'db_settings' => $dbRows,
            'env_vars_set' => $envSet,
            'hint' => $config['configured']
                ? 'SMTP is configured - test emails should send for real.'
                : 'Fill smtp_username + smtp_password + from_email in Settings > Email, or set SMTP_* env vars / .env.',
        ],
    ]);

} catch (Throwable $e) {
    sendJSON(['success' => false, 'error' => $e->getMessage()], 500);
}
?>

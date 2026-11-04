<?php
/**
 * MailService - Email sending via Hostinger SMTP
 *
 * Configuration resolution order:
 *   1. Environment variables (SMTP_HOST, SMTP_PORT, SMTP_USER, SMTP_PASS,
 *      MAIL_FROM_EMAIL, MAIL_FROM_NAME, MAIL_REPLY_TO) - for Docker/dev
 *   2. `settings` table rows where category = 'Email' - managed via
 *      Settings -> Email page in the admin UI
 *   3. Built-in Hostinger defaults
 *
 * Hostinger SMTP (prismadot.com mailboxes):
 *   Host: smtp.hostinger.com
 *   Port: 465 (SSL) or 587 (STARTTLS)
 *   User: full mailbox address, e.g. name@prismadot.com
 *   Pass: that mailbox's password
 */

require_once __DIR__ . '/SmtpMailer.php';

/**
 * Resolve the active email configuration.
 *
 * @param mysqli|null $conn Optional DB connection (settings table lookup)
 * @return array{host:string,port:int,username:string,password:string,from_email:string,from_name:string,reply_to:string,configured:bool}
 */
function getEmailConfig($conn = null) {
    $config = [
        'host'        => 'smtp.hostinger.com',
        'port'        => 465,
        'username'    => '',
        'password'    => '',
        'from_email'  => '',
        'from_name'   => 'Aventra Booking',
        'reply_to'    => '',
    ];

    // DB settings (category = 'Email')
    if ($conn && !$conn->connect_error) {
        $keyMap = [
            'smtp_host'      => 'host',
            'smtp_port'      => 'port',
            'smtp_username'  => 'username',
            'smtp_password'  => 'password',
            'from_email'     => 'from_email',
            'from_name'      => 'from_name',
            'reply_to_email' => 'reply_to',
        ];

        $result = @$conn->query("SELECT `key`, `value` FROM settings WHERE category = 'Email'");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $k = $row['key'];
                $v = trim((string) $row['value']);
                if (isset($keyMap[$k]) && $v !== '') {
                    $config[$keyMap[$k]] = $v;
                }
            }
            $result->free();
        }
    }

    // Environment variables override DB (useful for Docker / local dev)
    $envMap = [
        'SMTP_HOST'       => 'host',
        'SMTP_PORT'       => 'port',
        'SMTP_USER'       => 'username',
        'SMTP_PASS'       => 'password',
        'MAIL_FROM_EMAIL' => 'from_email',
        'MAIL_FROM_NAME'  => 'from_name',
        'MAIL_REPLY_TO'   => 'reply_to',
    ];
    foreach ($envMap as $env => $key) {
        $val = getenv($env);
        if ($val !== false && $val !== '') {
            $config[$key] = $val;
        }
    }

    $config['port'] = (int) $config['port'];
    if ($config['from_email'] === '') {
        $config['from_email'] = $config['username'];
    }
    $config['configured'] = (
        $config['host'] !== '' &&
        $config['username'] !== '' &&
        $config['password'] !== '' &&
        $config['from_email'] !== ''
    );

    return $config;
}

/**
 * Send an email via configured SMTP.
 *
 * @param mysqli|null $conn    DB connection for settings lookup
 * @param string      $to      Recipient email
 * @param string      $subject Subject
 * @param string      $html    HTML body
 * @param string|null $text    Optional plain-text version
 * @return array{success:bool,error?:string,configured:bool,debug?:array}
 */
function sendSmtpEmail($conn, $to, $subject, $html, $text = null) {
    $config = getEmailConfig($conn);

    if (!$config['configured']) {
        return [
            'success' => false,
            'configured' => false,
            'error' => 'SMTP is not configured. Set smtp_host, smtp_username and smtp_password ' .
                       'in Settings > Email (or SMTP_* env vars).',
        ];
    }

    $mailer = new SmtpMailer($config['host'], $config['port'], $config['username'], $config['password']);

    $ok = $mailer->send(
        $config['from_email'],
        $config['from_name'],
        $to,
        $subject,
        $html,
        $text,
        $config['reply_to'] ?: null
    );

    if (!$ok) {
        $error = $mailer->getLastError();

        // Common Hostinger fix: SSL on 465 vs STARTTLS on 587
        if ($config['port'] === 465 && stripos($error, 'connection failed') !== false) {
            $error .= ' (Tip: try port 587 with STARTTLS if 465/SSL is blocked)';
        }

        if (function_exists('debugLog')) {
            debugLog('SMTP SEND FAILED', [
                'to' => $to,
                'host' => $config['host'],
                'port' => $config['port'],
                'error' => $error,
            ]);
        }

        return [
            'success' => false,
            'configured' => true,
            'error' => $error,
            'debug' => $mailer->getDebugLog(),
        ];
    }

    // Best-effort: record the outbound email in communication_logs
    logCommunicationEmail($conn, $to, $subject, $html, $config);

    return ['success' => true, 'configured' => true];
}

/**
 * Record an outbound email in communication_logs (best-effort, never fatal).
 * Links the log to a customer when the recipient email matches one.
 */
function logCommunicationEmail($conn, $to, $subject, $html, $config = []) {
    try {
        // Table may not exist yet on older installs - bail quietly
        $tableCheck = @$conn->query("SHOW TABLES LIKE 'communication_logs'");
        if (!$tableCheck || $tableCheck->num_rows === 0) {
            return;
        }

        $customerId = null;
        $recipientName = null;
        $custStmt = $conn->prepare("SELECT id, first_name, last_name FROM customers WHERE email = ? AND deleted_at IS NULL LIMIT 1");
        if ($custStmt) {
            $custStmt->bind_param('s', $to);
            $custStmt->execute();
            $custRow = $custStmt->get_result()->fetch_assoc();
            $custStmt->close();
            if ($custRow) {
                $customerId = (int) $custRow['id'];
                $recipientName = trim($custRow['first_name'] . ' ' . $custRow['last_name']);
            }
        }

        $senderName = $config['from_name'] ?? 'System';
        $senderEmail = $config['from_email'] ?? null;
        // Store plain-text-ish content for readability in the admin UI
        $content = trim(preg_replace('/\s+/', ' ', strip_tags($html ?? '')));
        if (function_exists('mb_substr')) {
            $content = mb_substr($content, 0, 2000);
        } else {
            $content = substr($content, 0, 2000);
        }
        $type = 'email';
        $direction = 'outbound';
        $status = 'sent';
        $senderType = 'staff';
        $sentAt = date('Y-m-d H:i:s');
        $tags = json_encode(['system-email']);

        $stmt = $conn->prepare(
            "INSERT INTO communication_logs
             (customer_id, type, direction, status, subject, content,
              sender_name, sender_email, sender_type,
              recipient_name, recipient_email, sent_at, tags)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        if (!$stmt) {
            return;
        }
        $stmt->bind_param(
            'issssssssssss',
            $customerId, $type, $direction, $status, $subject, $content,
            $senderName, $senderEmail, $senderType,
            $recipientName, $to, $sentAt, $tags
        );
        @$stmt->execute();
        $stmt->close();
    } catch (Throwable $e) {
        // Never let logging break email sending
        if (function_exists('debugLog')) {
            debugLog('COMMUNICATION LOG FAILED', ['to' => $to, 'error' => $e->getMessage()]);
        }
    }
}

/**
 * Replace {{placeholder}} tokens in a string.
 */
function renderEmailTemplate($content, $data) {
    if (!is_array($data)) {
        return $content;
    }
    foreach ($data as $key => $value) {
        if (is_scalar($value) || $value === null) {
            $content = str_replace('{{' . $key . '}}', (string) $value, $content);
        }
    }
    return $content;
}
?>

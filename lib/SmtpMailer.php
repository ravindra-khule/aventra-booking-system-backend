<?php
/**
 * SmtpMailer - Dependency-free SMTP client
 *
 * Works on Hostinger shared hosting without Composer/PHPMailer.
 * Supports:
 *   - SSL on port 465 (ssl://)
 *   - STARTTLS on port 587
 *   - AUTH LOGIN authentication
 *   - HTML + plain text (multipart/alternative)
 *   - UTF-8 subjects and sender names
 */

class SmtpMailer {
    private $host;
    private $port;
    private $username;
    private $password;
    private $timeout = 20;
    private $socket = null;
    private $lastError = '';
    private $debugLog = [];

    public function __construct($host, $port = 465, $username = '', $password = '') {
        $this->host = $host;
        $this->port = (int) $port;
        $this->username = $username;
        $this->password = $password;
    }

    public function getLastError() {
        return $this->lastError;
    }

    public function getDebugLog() {
        return $this->debugLog;
    }

    /**
     * Send an email.
     *
     * @param string      $from     Sender email address
     * @param string      $fromName Sender display name
     * @param string      $to       Recipient email address
     * @param string      $subject  Subject line
     * @param string      $html     HTML body
     * @param string|null $text     Optional plain-text alternative
     * @param string|null $replyTo  Optional Reply-To address
     * @return bool
     */
    public function send($from, $fromName, $to, $subject, $html, $text = null, $replyTo = null) {
        try {
            if (!$this->connect()) {
                return false;
            }

            if (!$this->authenticate()) {
                $this->disconnect();
                return false;
            }

            // Envelope
            if (!$this->command("MAIL FROM:<{$from}>", 250)) {
                $this->disconnect();
                return false;
            }
            if (!$this->command("RCPT TO:<{$to}>", [250, 251])) {
                $this->disconnect();
                return false;
            }
            if (!$this->command('DATA', 354)) {
                $this->disconnect();
                return false;
            }

            $message = $this->buildMessage($from, $fromName, $to, $subject, $html, $text, $replyTo);

            fwrite($this->socket, $message . "\r\n.\r\n");
            if (!$this->expectResponse([250])) {
                $this->disconnect();
                return false;
            }

            $this->command('QUIT', 221);
            $this->disconnect();
            return true;
        } catch (Exception $e) {
            $this->lastError = 'Exception: ' . $e->getMessage();
            $this->disconnect();
            return false;
        }
    }

    private function connect() {
        $useSsl = ($this->port === 465);
        $remote = ($useSsl ? 'ssl://' : 'tcp://') . $this->host . ':' . $this->port;

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'allow_self_signed' => false,
            ],
        ]);

        $this->socket = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!$this->socket) {
            // Retry without strict cert verification (some shared hosts chain oddly)
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true,
                ],
            ]);
            $this->socket = @stream_socket_client(
                $remote,
                $errno,
                $errstr,
                $this->timeout,
                STREAM_CLIENT_CONNECT,
                $context
            );
        }

        if (!$this->socket) {
            $this->lastError = "Connection failed to {$remote}: {$errstr} ({$errno})";
            return false;
        }

        stream_set_timeout($this->socket, $this->timeout);

        // Server greeting
        if (!$this->expectResponse(220)) {
            return false;
        }

        $ehloHost = $this->getLocalHostname();
        if (!$this->command("EHLO {$ehloHost}", 250)) {
            // Some servers only accept HELO
            if (!$this->command("HELO {$ehloHost}", 250)) {
                return false;
            }
        }

        // STARTTLS for non-465 ports (e.g. 587)
        if (!$useSsl) {
            if (!$this->command('STARTTLS', 220)) {
                return false;
            }
            if (!@stream_socket_enable_crypto($this->socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                $this->lastError = 'STARTTLS negotiation failed';
                return false;
            }
            // Re-EHLO after TLS upgrade
            if (!$this->command("EHLO {$ehloHost}", 250)) {
                return false;
            }
        }

        return true;
    }

    private function authenticate() {
        if ($this->username === '') {
            return true; // no auth required
        }

        if (!$this->command('AUTH LOGIN', 334)) {
            return false;
        }
        if (!$this->command(base64_encode($this->username), 334)) {
            return false;
        }
        if (!$this->command(base64_encode($this->password), 235)) {
            $this->lastError = 'SMTP authentication failed - check username/password. ' . $this->lastError;
            return false;
        }
        return true;
    }

    private function buildMessage($from, $fromName, $to, $subject, $html, $text, $replyTo) {
        $boundary = '=_AventraMail_' . md5(uniqid((string) mt_rand(), true));
        $messageId = '<' . md5(uniqid((string) mt_rand(), true)) . '@' . $this->getDomain($from) . '>';

        $headers = [];
        $headers[] = 'Date: ' . date('r');
        $headers[] = 'Message-ID: ' . $messageId;
        $headers[] = 'From: ' . $this->encodeHeaderName($fromName) . ' <' . $from . '>';
        $headers[] = 'To: <' . $to . '>';
        $headers[] = 'Subject: ' . $this->encodeHeader($subject);
        $headers[] = 'MIME-Version: 1.0';
        if ($replyTo) {
            $headers[] = 'Reply-To: <' . $replyTo . '>';
        }

        if ($text !== null && $text !== '') {
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary . '"';
            $body = "--{$boundary}\r\n"
                . "Content-Type: text/plain; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode($text))
                . "--{$boundary}\r\n"
                . "Content-Type: text/html; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode($html))
                . "--{$boundary}--";
        } else {
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $headers[] = 'Content-Transfer-Encoding: base64';
            $body = chunk_split(base64_encode($html));
        }

        $message = implode("\r\n", $headers) . "\r\n\r\n" . $body;

        // Normalize line endings + dot-stuffing (RFC 5321)
        $message = str_replace(["\r\n", "\r", "\n"], "\n", $message);
        $message = str_replace("\n", "\r\n", $message);
        $message = preg_replace('/^\./m', '..', $message);

        return $message;
    }

    private function encodeHeader($value) {
        if (preg_match('/[^\x20-\x7E]/', $value)) {
            return '=?UTF-8?B?' . base64_encode($value) . '?=';
        }
        return $value;
    }

    private function encodeHeaderName($name) {
        if ($name === '' || $name === null) {
            return '';
        }
        return $this->encodeHeader($name);
    }

    private function getLocalHostname() {
        $host = $_SERVER['SERVER_NAME'] ?? null;
        if (!$host || $host === 'localhost') {
            $host = gethostname() ?: 'localhost';
        }
        return $host;
    }

    private function getDomain($email) {
        $parts = explode('@', $email);
        return $parts[1] ?? 'localhost';
    }

    /**
     * Send a command and verify the expected response code.
     */
    private function command($command, $expected) {
        fwrite($this->socket, $command . "\r\n");
        return $this->expectResponse($expected);
    }

    /**
     * Read a (possibly multi-line) SMTP response and check its code.
     */
    private function expectResponse($expected) {
        $expected = (array) $expected;
        $response = '';
        $code = 0;

        while (($line = fgets($this->socket, 515)) !== false) {
            $response .= $line;
            if (preg_match('/^(\d{3})([ -])/', $line, $m)) {
                $code = (int) $m[1];
                if ($m[2] === ' ') {
                    break; // last line of a multi-line reply
                }
            } else {
                break; // malformed line
            }
        }

        $this->debugLog[] = ['response' => trim($response), 'code' => $code];

        if ($line === false) {
            $this->lastError = 'Timeout or connection lost while reading SMTP response';
            return false;
        }

        if (!in_array($code, $expected, true)) {
            $this->lastError = "Unexpected SMTP response {$code}: " . trim($response);
            return false;
        }

        return true;
    }

    private function disconnect() {
        if ($this->socket) {
            @fclose($this->socket);
            $this->socket = null;
        }
    }
}
?>

<?php
/**
 * JWT Handler - JWT Token Generation and Validation
 * Uses a simple JWT implementation without external dependencies
 */

class JWTHandler {
    private static $secret = 'aventra_super_secret_key_change_in_production_env';
    private static $algorithm = 'HS256';
    private static $expirationTime = 86400; // 24 hours in seconds
    
    /**
     * Generate a JWT token
     * 
     * @param array $payload - Data to encode in the token
     * @param int $expiresIn - Token expiration time in seconds (default: 24 hours)
     * @return string - JWT token
     */
    public static function generateToken($payload, $expiresIn = null) {
        if ($expiresIn === null) {
            $expiresIn = self::$expirationTime;
        }
        
        // Get secret from environment for security
        $secret = getenv('JWT_SECRET') ?: self::$secret;
        
        // Create header
        $header = [
            'alg' => self::$algorithm,
            'typ' => 'JWT'
        ];
        
        // Add expiration to payload
        $payload['iat'] = time(); // Issued at
        $payload['exp'] = time() + $expiresIn; // Expiration time
        
        // Encode header and payload
        $headerEncoded = self::base64UrlEncode(json_encode($header));
        $payloadEncoded = self::base64UrlEncode(json_encode($payload));
        
        // Create signature
        $signature = self::createSignature(
            $headerEncoded . '.' . $payloadEncoded,
            $secret
        );
        $signatureEncoded = self::base64UrlEncode($signature);
        
        // Return complete JWT
        return $headerEncoded . '.' . $payloadEncoded . '.' . $signatureEncoded;
    }
    
    /**
     * Verify and decode a JWT token
     * 
     * @param string $token - JWT token to verify
     * @return array|false - Decoded payload on success, false on failure
     */
    public static function verifyToken($token) {
        try {
            $secret = getenv('JWT_SECRET') ?: self::$secret;
            
            // Split token into parts
            $parts = explode('.', $token);
            
            if (count($parts) !== 3) {
                return false;
            }
            
            list($headerEncoded, $payloadEncoded, $signatureEncoded) = $parts;
            
            // Verify signature
            $expectedSignature = self::base64UrlEncode(
                self::createSignature(
                    $headerEncoded . '.' . $payloadEncoded,
                    $secret
                )
            );
            
            if (!hash_equals($signatureEncoded, $expectedSignature)) {
                return false;
            }
            
            // Decode payload
            $payload = json_decode(
                self::base64UrlDecode($payloadEncoded),
                true
            );
            
            if (!$payload) {
                return false;
            }
            
            // Check expiration
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                return false;
            }
            
            return $payload;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Extract token from Authorization header
     * Supports both getallheaders() and $_SERVER fallback
     * 
     * @return string|null - Token or null if not found
     */
    public static function getTokenFromHeader() {
        $authHeader = null;
        
        // Try getallheaders() first
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            if (isset($headers['Authorization'])) {
                $authHeader = $headers['Authorization'];
            }
        }
        
        // Fallback to $_SERVER
        if (!$authHeader && isset($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        }
        
        // Another common header format
        if (!$authHeader && isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }
        
        if (!$authHeader) {
            return null;
        }
        
        // Remove "Bearer " prefix
        if (preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Create HMAC signature
     * 
     * @param string $data - Data to sign
     * @param string $secret - Secret key
     * @return string - Signature
     */
    private static function createSignature($data, $secret) {
        return hash_hmac('sha256', $data, $secret, true);
    }
    
    /**
     * Base64 URL encode
     * 
     * @param string $data - Data to encode
     * @return string - Encoded data
     */
    private static function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Base64 URL decode
     * 
     * @param string $data - Data to decode
     * @return string - Decoded data
     */
    private static function base64UrlDecode($data) {
        $padding = 4 - (strlen($data) % 4);
        if ($padding !== 4) {
            $data .= str_repeat('=', $padding);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
?>

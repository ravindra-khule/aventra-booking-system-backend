<?php
/**
 * Auth Middleware - Protect API endpoints with JWT authentication
 * Handles token validation, blacklist checking, and RBAC
 */

require_once __DIR__ . '/JWTHandler.php';

class AuthMiddleware {
    /**
     * Path to token blacklist file
     */
    private static $blacklistPath = __DIR__ . '/../storage/token_blacklist.json';
    
    /**
     * Verify JWT token and return user payload
     * Dies with JSON error if token is invalid
     * 
     * @return array - Decoded JWT payload with user data
     */
    public static function verifyToken() {
        // Get token from Authorization header
        $token = JWTHandler::getTokenFromHeader();
        
        if (!$token) {
            http_response_code(401);
            die(json_encode(['success' => false, 'error' => 'No authorization token provided']));
        }
        
        // Check if token is in blacklist
        if (self::isTokenBlacklisted($token)) {
            http_response_code(401);
            die(json_encode(['success' => false, 'error' => 'Token has been revoked']));
        }
        
        // Verify token signature and expiration
        $payload = JWTHandler::verifyToken($token);
        
        if (!$payload) {
            http_response_code(401);
            die(json_encode(['success' => false, 'error' => 'Invalid or expired token']));
        }
        
        return $payload;
    }
    
    /**
     * Verify token for specific role
     * Dies with JSON error if user doesn't have required role
     * 
     * @param string|array $requiredRoles - Single role or array of allowed roles
     * @return array - Verified user payload
     */
    public static function verifyTokenWithRole($requiredRoles) {
        $payload = self::verifyToken();
        
        if (is_string($requiredRoles)) {
            $requiredRoles = [$requiredRoles];
        }
        
        if (!in_array($payload['role'] ?? null, $requiredRoles)) {
            http_response_code(403);
            die(json_encode(['success' => false, 'error' => 'User does not have permission to access this resource']));
        }
        
        return $payload;
    }
    
    /**
     * Add token to blacklist
     * Called during logout to invalidate token
     * 
     * @param string $token - Token to blacklist
     * @param int $expiresAt - Token expiration timestamp
     * @return bool - Success status
     */
    public static function blacklistToken($token, $expiresAt) {
        try {
            // Ensure storage directory exists
            $dir = dirname(self::$blacklistPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            // Load existing blacklist
            $blacklist = [];
            if (file_exists(self::$blacklistPath)) {
                $content = file_get_contents(self::$blacklistPath);
                $blacklist = json_decode($content, true) ?: [];
            }
            
            // Add token with expiration time
            $blacklist[$token] = $expiresAt;
            
            // Clean up expired tokens (older than 48 hours)
            $cutoff = time() - (48 * 3600);
            foreach ($blacklist as $key => $exp) {
                if ($exp < $cutoff) {
                    unset($blacklist[$key]);
                }
            }
            
            // Save blacklist
            file_put_contents(
                self::$blacklistPath,
                json_encode($blacklist, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
                LOCK_EX
            );
            
            return true;
        } catch (Exception $e) {
            error_log("Error blacklisting token: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if token is blacklisted
     * 
     * @param string $token - Token to check
     * @return bool - True if blacklisted, false otherwise
     */
    private static function isTokenBlacklisted($token) {
        if (!file_exists(self::$blacklistPath)) {
            return false;
        }
        
        try {
            $blacklist = json_decode(file_get_contents(self::$blacklistPath), true) ?: [];
            return isset($blacklist[$token]);
        } catch (Exception $e) {
            error_log("Error checking token blacklist: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get current authenticated user from token
     * Returns null if token is invalid or not provided
     * 
     * @return array|null - User payload or null
     */
    public static function getCurrentUser() {
        try {
            $token = JWTHandler::getTokenFromHeader();
            
            if (!$token) {
                return null;
            }
            
            if (self::isTokenBlacklisted($token)) {
                return null;
            }
            
            return JWTHandler::verifyToken($token);
        } catch (Exception $e) {
            return null;
        }
    }
}
?>

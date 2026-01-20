<?php
namespace Core;

/**
 * Middleware for automatic role-based access control
 */
class AuthMiddleware {
    
    /**
     * Admin middleware - requires admin or super_admin role
     */
    public static function admin() {
        return function() {
            Authorization::requireRole(['admin', 'super_admin']);
        };
    }
    
    /**
     * Super admin middleware - requires super_admin role only
     */
    public static function superAdmin() {
        return function() {
            Authorization::requireRole(['super_admin']);
        };
    }
    
    /**
     * Faculty middleware - requires faculty, admin, or super_admin role
     */
    public static function faculty() {
        return function() {
            Authorization::requireRole(['faculty', 'admin', 'super_admin']);
        };
    }
    
    /**
     * Module access middleware - requires access to specific module
     */
    public static function moduleAccess(string $module) {
        return function() use ($module) {
            Authorization::requireModuleAccess($module);
        };
    }
    
    /**
     * Rate limiting middleware (basic implementation)
     */
    public static function rateLimit(int $maxRequests = 100, int $timeWindow = 3600) {
        return function() use ($maxRequests, $timeWindow) {
            $clientId = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $key = "rate_limit_" . md5($clientId);
            
            // In a real application, you'd use Redis or database for this
            $requestCount = (int)($_SESSION[$key] ?? 0);
            $windowStart = (int)($_SESSION[$key . '_start'] ?? time());
            
            if (time() - $windowStart > $timeWindow) {
                $_SESSION[$key] = 1;
                $_SESSION[$key . '_start'] = time();
            } else {
                $_SESSION[$key] = $requestCount + 1;
                
                if ($requestCount >= $maxRequests) {
                    Response::json([
                        'error' => 'Rate limit exceeded',
                        'retry_after' => $timeWindow - (time() - $windowStart)
                    ], 429);
                    exit;
                }
            }
        };
    }
    
    /**
     * CSRF protection middleware
     */
    public static function csrfProtection() {
        return function() {
            $method = $_SERVER['REQUEST_METHOD'];
            
            // Only check CSRF for state-changing methods
            if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'])) {
                $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['_token'] ?? '';
                $sessionToken = $_SESSION['csrf_token'] ?? '';
                
                if (empty($token) || empty($sessionToken) || !hash_equals($sessionToken, $token)) {
                    Response::json([
                        'error' => 'CSRF token mismatch',
                        'csrf_token' => self::generateCsrfToken()
                    ], 403);
                    exit;
                }
            }
        };
    }
    
    /**
     * Generate CSRF token
     */
    public static function generateCsrfToken(): string {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * IP whitelist middleware
     */
    public static function ipWhitelist(array $allowedIps) {
        return function() use ($allowedIps) {
            $clientIp = $_SERVER['REMOTE_ADDR'] ?? '';
            $forwardedIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
            
            if ($forwardedIp) {
                $clientIp = explode(',', $forwardedIp)[0];
            }
            
            if (!in_array(trim($clientIp), $allowedIps)) {
                Response::json([
                    'error' => 'Access denied from this IP address'
                ], 403);
                exit;
            }
        };
    }
}
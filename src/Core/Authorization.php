<?php
namespace Core;
use Core\Auth;
use Core\Response;

/**
 * Centralized authorization class for role-based access control
 */
class Authorization {
    
    // Define module permissions centrally on the server
    private static array $modulePermissions = [
        'users' => ['super_admin'],
        'approvals' => ['super_admin'],
        'expertise' => ['super_admin'],
        'faculty' => ['super_admin'],
        'subjects' => ['super_admin'],
        'schedules' => ['admin', 'super_admin'],
        'posts' => ['admin', 'super_admin'],
        'notes' => ['admin', 'super_admin'],
        'announcements' => ['admin', 'super_admin'],
        'change-password' => ['admin', 'super_admin'],
        'class-status' => ['admin', 'super_admin'],
    ];
    
    // Define API endpoint permissions
    private static array $endpointPermissions = [
        // User Management
        'GET:/api/users' => ['super_admin'],
        'POST:/api/users' => ['super_admin'],
        'PUT:/api/users/*' => ['super_admin'],
        'DELETE:/api/users/*' => ['super_admin'],
        
        // Admin Requests
        'GET:/api/admin/requests' => ['super_admin'],
        'PATCH:/api/admin/requests/*' => ['super_admin'],
        'POST:/api/admin/users/*/role' => ['super_admin'],
        
        // Faculty Management
        'GET:/api/faculty' => ['super_admin'],
        'POST:/api/faculty' => ['super_admin'],
        'PUT:/api/faculty/*' => ['super_admin'],
        'DELETE:/api/faculty/*' => ['super_admin'],
        
        // Subject Management
        'GET:/api/subjects' => ['super_admin'],
        'POST:/api/subjects' => ['super_admin'],
        'PUT:/api/subjects/*' => ['super_admin'],
        'DELETE:/api/subjects/*' => ['super_admin'],
        
        // Expertise Management
        'GET:/api/expertise' => ['super_admin'],
        'POST:/api/expertise' => ['super_admin'],
        'PUT:/api/expertise/*' => ['super_admin'],
        'DELETE:/api/expertise/*' => ['super_admin'],
        
        // Shared admin endpoints (both admin and super_admin)
        'GET:/api/schedules' => ['admin', 'super_admin'],
        'POST:/api/schedules' => ['admin', 'super_admin'],
        'PUT:/api/schedules/*' => ['admin', 'super_admin'],
        'DELETE:/api/schedules/*' => ['admin', 'super_admin'],
        
        'GET:/api/posts' => ['admin', 'super_admin'],
        'POST:/api/posts' => ['admin', 'super_admin'],
        'PUT:/api/posts/*' => ['admin', 'super_admin'],
        'DELETE:/api/posts/*' => ['admin', 'super_admin'],
        
        'GET:/api/notes' => ['admin', 'super_admin'],
        'POST:/api/notes' => ['admin', 'super_admin'],
        'PUT:/api/notes/*' => ['admin', 'super_admin'],
        'DELETE:/api/notes/*' => ['admin', 'super_admin'],
        
        'GET:/api/announcements' => ['admin', 'super_admin'],
        'POST:/api/announcements' => ['admin', 'super_admin'],
        'PUT:/api/announcements/*' => ['admin', 'super_admin'],
        'DELETE:/api/announcements/*' => ['admin', 'super_admin'],
        
        // Class Status Management
        'GET:/api/admin/class-status' => ['admin', 'super_admin'],
        'POST:/api/admin/class-status' => ['admin', 'super_admin'],
    ];
    
    /**
     * Check if user has permission for a specific module
     */
    public static function canAccessModule(string $module, string $userRole): bool {
        if (!isset(self::$modulePermissions[$module])) {
            return false; // Deny access to undefined modules
        }
        
        return in_array($userRole, self::$modulePermissions[$module]);
    }
    
    /**
     * Check if user has permission for a specific API endpoint
     */
    public static function canAccessEndpoint(string $method, string $path, string $userRole): bool {
        $key = strtoupper($method) . ':' . $path;
        
        // Check exact match first
        if (isset(self::$endpointPermissions[$key])) {
            return in_array($userRole, self::$endpointPermissions[$key]);
        }
        
        // Check wildcard patterns
        foreach (self::$endpointPermissions as $pattern => $allowedRoles) {
            if (str_contains($pattern, '*')) {
                $regex = str_replace(['*', '/'], ['[^/]+', '\/'], $pattern);
                if (preg_match('/^' . $regex . '$/', $key)) {
                    return in_array($userRole, $allowedRoles);
                }
            }
        }
        
        return false; // Deny access by default
    }
    
    /**
     * Require specific role(s) for current request
     */
    public static function requireRole(array $allowedRoles): array {
        return Auth::requireRole($allowedRoles);
    }
    
    /**
     * Require module access permission
     */
    public static function requireModuleAccess(string $module): array {
        $payload = Auth::requireUser();
        
        if (!self::canAccessModule($module, $payload['role'])) {
            Response::json([
                'error' => 'Access denied to module: ' . $module,
                'required_permissions' => self::$modulePermissions[$module] ?? [],
                'your_role' => $payload['role']
            ], 403);
            exit;
        }
        
        return $payload;
    }
    
    /**
     * Require endpoint access permission
     */
    public static function requireEndpointAccess(string $method, string $path): array {
        $payload = Auth::requireUser();
        
        if (!self::canAccessEndpoint($method, $path, $payload['role'])) {
            Response::json([
                'error' => 'Access denied to endpoint',
                'endpoint' => strtoupper($method) . ':' . $path,
                'your_role' => $payload['role']
            ], 403);
            exit;
        }
        
        return $payload;
    }
    
    /**
     * Get user's accessible modules
     */
    public static function getAccessibleModules(string $userRole): array {
        $accessibleModules = [];
        
        foreach (self::$modulePermissions as $module => $allowedRoles) {
            if (in_array($userRole, $allowedRoles)) {
                $accessibleModules[] = $module;
            }
        }
        
        return $accessibleModules;
    }
    
    /**
     * Get all module permissions (for debugging - remove in production)
     */
    public static function getAllModulePermissions(): array {
        return self::$modulePermissions;
    }
}
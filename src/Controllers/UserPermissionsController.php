<?php
namespace Controllers;
use Core\Response;
use Core\Auth as AuthCore;
use Core\Authorization;

class UserPermissionsController {
    
    /**
     * GET /api/user/permissions - Get current user's permissions
     * This replaces client-side role checking with server-side permission validation
     */
    public function getUserPermissions() {
        $payload = AuthCore::requireUser();
        $userRole = $payload['role'];
        
        // Only allow admin and super_admin roles to access dashboard
        if (!in_array($userRole, ['admin', 'super_admin'])) {
            return Response::json([
                'error' => 'Access denied. Admin privileges required.',
                'your_role' => $userRole
            ], 403);
        }
        
        // Get accessible modules based on role
        $accessibleModules = Authorization::getAccessibleModules($userRole);
        
        // Define UI capabilities based on role
        $capabilities = [
            'can_manage_users' => $userRole === 'super_admin',
            'can_approve_requests' => $userRole === 'super_admin',
            'can_manage_faculty' => $userRole === 'super_admin',
            'can_manage_subjects' => $userRole === 'super_admin',
            'can_manage_expertise' => $userRole === 'super_admin',
            'can_manage_schedules' => in_array($userRole, ['admin', 'super_admin']),
            'can_manage_posts' => in_array($userRole, ['admin', 'super_admin']),
            'can_manage_notes' => in_array($userRole, ['admin', 'super_admin']),
            'can_manage_announcements' => in_array($userRole, ['admin', 'super_admin']),
            'can_change_password' => in_array($userRole, ['admin', 'super_admin']),
            'can_manage_class_status' => in_array($userRole, ['admin', 'super_admin']),
        ];
        
        return Response::json([
            'success' => true,
            'user' => [
                'id' => $payload['sub'],
                'role' => $userRole,
                'is_super_admin' => $userRole === 'super_admin'
            ],
            'permissions' => [
                'accessible_modules' => $accessibleModules,
                'capabilities' => $capabilities
            ]
        ]);
    }
    
    /**
     * GET /api/user/module/{module}/access - Check access to specific module
     */
    public function checkModuleAccess($params) {
        $module = $params['module'] ?? '';
        
        if (empty($module)) {
            return Response::json([
                'error' => 'Module name is required'
            ], 422);
        }
        
        try {
            $payload = Authorization::requireModuleAccess($module);
            
            return Response::json([
                'success' => true,
                'access_granted' => true,
                'module' => $module,
                'user_role' => $payload['role']
            ]);
            
        } catch (\Exception $e) {
            return Response::json([
                'success' => false,
                'access_granted' => false,
                'module' => $module,
                'error' => $e->getMessage()
            ], 403);
        }
    }
}
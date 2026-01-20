<?php
namespace Controllers;
use Core\Response;
use Core\Authorization;
use Models\UserModel;

/**
 * Example secure controller showing best practices
 */
class SecureUserController {
    private UserModel $users;
    
    public function __construct() {
        $this->users = new UserModel();
    }
    
    /**
     * GET /api/secure-users - Example of properly secured endpoint
     * Only super_admin can access user management
     */
    public function getUsers() {
        // Method 1: Use Authorization class with endpoint checking
        $payload = Authorization::requireEndpointAccess('GET', '/api/secure-users');
        
        // Method 2: Alternative - direct role requirement
        // $payload = Authorization::requireRole(['super_admin']);
        
        // Now we know the user is authenticated and authorized
        $page = (int)($_GET['page'] ?? 1);
        $limit = min((int)($_GET['limit'] ?? 20), 100); // Cap at 100 for performance
        $status = $_GET['status'] ?? 'active';
        
        $users = $this->users->getUsers($page, $limit, $status);
        
        // Remove sensitive data before sending
        $sanitizedUsers = array_map(function($user) {
            unset($user['password_hash'], $user['auth_token']);
            return $user;
        }, $users);
        
        return Response::json([
            'success' => true,
            'users' => $sanitizedUsers,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $this->users->getUsersCount($status)
            ]
        ]);
    }
    
    /**
     * POST /api/secure-users - Create user (super_admin only)
     */
    public function createUser() {
        $payload = Authorization::requireRole(['super_admin']);
        $in = Response::input();
        
        // Input validation
        $errors = $this->validateUserInput($in);
        if (!empty($errors)) {
            return Response::json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $errors
            ], 422);
        }
        
        // Business logic here...
        
        return Response::json([
            'success' => true,
            'message' => 'User created successfully'
        ], 201);
    }
    
    /**
     * PUT /api/secure-users/{id} - Update user (super_admin only)
     */
    public function updateUser(array $params) {
        $payload = Authorization::requireRole(['super_admin']);
        $userId = (int)$params['id'];
        $in = Response::input();
        
        // Check if user exists
        $user = $this->users->findById($userId);
        if (!$user) {
            return Response::json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
        
        // Prevent modifying super_admin (except by self)
        if ($user['role'] === 'super_admin' && $userId !== (int)$payload['sub']) {
            return Response::json([
                'success' => false,
                'message' => 'Cannot modify super admin account'
            ], 403);
        }
        
        // Business logic here...
        
        return Response::json([
            'success' => true,
            'message' => 'User updated successfully'
        ]);
    }
    
    /**
     * DELETE /api/secure-users/{id} - Delete user (super_admin only)
     */
    public function deleteUser(array $params) {
        $payload = Authorization::requireRole(['super_admin']);
        $userId = (int)$params['id'];
        
        // Prevent self-deletion
        if ($userId === (int)$payload['sub']) {
            return Response::json([
                'success' => false,
                'message' => 'Cannot delete your own account'
            ], 403);
        }
        
        // Check if user exists
        $user = $this->users->findById($userId);
        if (!$user) {
            return Response::json([
                'success' => false,
                'message' => 'User not found'
            ], 404);
        }
        
        // Prevent deleting super_admin
        if ($user['role'] === 'super_admin') {
            return Response::json([
                'success' => false,
                'message' => 'Cannot delete super admin account'
            ], 403);
        }
        
        // Business logic here...
        
        return Response::json([
            'success' => true,
            'message' => 'User deleted successfully'
        ]);
    }
    
    /**
     * GET /api/user-dashboard - Mixed access (admin and super_admin)
     */
    public function getDashboard() {
        $payload = Authorization::requireRole(['admin', 'super_admin']);
        
        // Different data based on role
        $data = [
            'user_role' => $payload['role'],
            'user_id' => $payload['sub']
        ];
        
        if ($payload['role'] === 'super_admin') {
            $data['admin_stats'] = $this->users->getAdminStats();
            $data['user_management'] = true;
        } else {
            $data['user_management'] = false;
        }
        
        return Response::json([
            'success' => true,
            'dashboard' => $data
        ]);
    }
    
    private function validateUserInput(array $input): array {
        $errors = [];
        
        if (empty($input['name'])) {
            $errors['name'] = 'Name is required';
        }
        
        if (empty($input['email']) || !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Valid email is required';
        }
        
        if (empty($input['role']) || !in_array($input['role'], ['user', 'admin', 'faculty'])) {
            $errors['role'] = 'Valid role is required';
        }
        
        return $errors;
    }
}
<?php
namespace Controllers;
use Core\Response;
use Core\Auth as AuthCore;
use Core\Authorization;
use Models\UserModel;

class UserManagementController {
  private UserModel $users;
  
  public function __construct(){ 
    $this->users = new UserModel(); 
  }

  // DELETE /api/self - Self-delete user account
  public function selfDeleteAccount() {
    // Get authenticated user
    $p = AuthCore::requireUser();
    $userId = (int)$p['sub'];
    
    // Get user data
    $user = $this->users->findById($userId);
    if (!$user) {
      return Response::json([
        'success' => false,
        'message' => 'User not found'
      ], 404);
    }
    
    // Cannot self-delete if already deleted
    if ($user['is_deleted']) {
      return Response::json([
        'success' => false,
        'message' => 'Account already deleted'
      ], 409);
    }
    
    // Cannot self-delete if super_admin
    if ($user['role'] === 'super_admin') {
      return Response::json([
        'success' => false,
        'message' => 'Super admin account cannot be deleted'
      ], 403);
    }
    
    // Perform self-delete
    $success = $this->users->selfDelete($userId);
    
    if ($success) {
      return Response::json([
        'success' => true,
        'message' => 'Account deleted successfully'
      ]);
    }
    
    return Response::json([
        'success' => false,
        'message' => 'Failed to delete account'
    ], 500);
  }

  // DELETE /admin/users/{id} - Delete user (Super Admin only)
  public function deleteUser(array $params){
    $p = AuthCore::requireRole(['super_admin']);
    $userId = (int)$params['id'];
    $in = Response::input();
    
    // Validate input
    if (empty($in['reason'])) {
      return Response::json([
        'success' => false,
        'message' => 'Deletion reason is required'
      ], 422);
    }
    
    // Check if user exists and is not already deleted
    $user = $this->users->findById($userId);
    if (!$user) {
      return Response::json([
        'success' => false,
        'message' => 'User not found'
      ], 404);
    }
    
    if ($user['is_deleted']) {
      return Response::json([
        'success' => false,
        'message' => 'User is already deleted'
      ], 409);
    }
    
    // Cannot delete super admin
    if ($user['role'] === 'super_admin') {
      return Response::json([
        'success' => false,
        'message' => 'Cannot delete super admin'
      ], 403);
    }
    
    // Cannot delete yourself
    if ($userId === (int)$p['sub']) {
      return Response::json([
        'success' => false,
        'message' => 'Cannot delete your own account'
      ], 403);
    }
    
    $success = $this->users->deleteUser($userId, (int)$p['sub'], $in['reason']);
    
    if ($success) {
      return Response::json([
        'success' => true,
        'message' => 'User deleted successfully',
        'user' => [
          'id' => $user['id'],
          'name' => $user['name'],
          'email' => $user['email']
        ]
      ]);
    }
    
    return Response::json([
      'success' => false,
      'message' => 'Failed to delete user'
    ], 500);
  }

  // POST /admin/users/{id}/restore - Restore deleted user (Super Admin only)
  public function restoreUser(array $params){
    $p = AuthCore::requireRole(['super_admin']);
    $userId = (int)$params['id'];
    
    $user = $this->users->findById($userId);
    if (!$user) {
      return Response::json([
        'success' => false,
        'message' => 'User not found'
      ], 404);
    }
    
    if (!$user['is_deleted']) {
      return Response::json([
        'success' => false,
        'message' => 'User is not deleted'
      ], 409);
    }
    
    $success = $this->users->restoreUser($userId);
    
    if ($success) {
      return Response::json([
        'success' => true,
        'message' => 'User restored successfully',
        'user' => [
          'id' => $user['id'],
          'name' => $user['name'],
          'email' => $user['email']
        ]
      ]);
    }
    
    return Response::json([
      'success' => false,
      'message' => 'Failed to restore user'
    ], 500);
  }

  // PATCH /admin/users/{id}/block - Block user (Super Admin only)
  public function blockUser(array $params){
    $p = AuthCore::requireRole(['super_admin']);
    $userId = (int)$params['id'];
    $in = Response::input();
    
    // Validate input
    if (empty($in['reason'])) {
      return Response::json([
        'success' => false,
        'message' => 'Block reason is required'
      ], 422);
    }
    
    $user = $this->users->findById($userId);
    if (!$user) {
      return Response::json([
        'success' => false,
        'message' => 'User not found'
      ], 404);
    }
    
    if ($user['is_deleted']) {
      return Response::json([
        'success' => false,
        'message' => 'Cannot block deleted user'
      ], 409);
    }
    
    if ($user['is_blocked']) {
      return Response::json([
        'success' => false,
        'message' => 'User is already blocked'
      ], 409);
    }
    
    // Cannot block super admin
    if ($user['role'] === 'super_admin') {
      return Response::json([
        'success' => false,
        'message' => 'Cannot block super admin'
      ], 403);
    }
    
    // Cannot block yourself
    if ($userId === (int)$p['sub']) {
      return Response::json([
        'success' => false,
        'message' => 'Cannot block your own account'
      ], 403);
    }
    
    $success = $this->users->blockUser($userId, (int)$p['sub'], $in['reason']);
    
    if ($success) {
      return Response::json([
        'success' => true,
        'message' => 'User blocked successfully',
        'user' => [
          'id' => $user['id'],
          'name' => $user['name'],
          'email' => $user['email']
        ]
      ]);
    }
    
    return Response::json([
      'success' => false,
      'message' => 'Failed to block user'
    ], 500);
  }

  // PATCH /admin/users/{id}/unblock - Unblock user (Super Admin only)
  public function unblockUser(array $params){
    $p = AuthCore::requireRole(['super_admin']);
    $userId = (int)$params['id'];
    
    $user = $this->users->findById($userId);
    if (!$user) {
      return Response::json([
        'success' => false,
        'message' => 'User not found'
      ], 404);
    }
    
    if (!$user['is_blocked']) {
      return Response::json([
        'success' => false,
        'message' => 'User is not blocked'
      ], 409);
    }
    
    $success = $this->users->unblockUser($userId);
    
    if ($success) {
      return Response::json([
        'success' => true,
        'message' => 'User unblocked successfully',
        'user' => [
          'id' => $user['id'],
          'name' => $user['name'],
          'email' => $user['email']
        ]
      ]);
    }
    
    return Response::json([
      'success' => false,
      'message' => 'Failed to unblock user'
    ], 500);
  }

  // GET /admin/users/deleted - Get deleted users (Super Admin only)
  public function getDeletedUsers(){
    $p = AuthCore::requireRole(['super_admin']);
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    
    $deletedUsers = $this->users->getDeletedUsers($page, $limit);
    
    return Response::json([
      'success' => true,
      'deleted_users' => $deletedUsers,
      'page' => $page,
      'limit' => $limit
    ]);
  }

  // GET /admin/users/blocked - Get blocked users (Super Admin only)
  public function getBlockedUsers(){
    $p = AuthCore::requireRole(['super_admin']);
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    
    $blockedUsers = $this->users->getBlockedUsers($page, $limit);
    
    return Response::json([
      'success' => true,
      'blocked_users' => $blockedUsers,
      'page' => $page,
      'limit' => $limit
    ]);
  }

  // GET /admin/users/{id}/status - Get user status (Super Admin only)
  public function getUserStatus(array $params){
    $p = AuthCore::requireRole(['super_admin']);
    $userId = (int)$params['id'];
    
    $userStatus = $this->users->getUserStatus($userId);
    
    if (!$userStatus) {
      return Response::json([
        'success' => false,
        'message' => 'User not found'
      ], 404);
    }
    
    return Response::json([
      'success' => true,
      'user_status' => $userStatus
    ]);
  }

  // PATCH /admin/users/{id}/delete-block - Combined API for delete and block (Super Admin only)
  public function deleteOrBlockUser(array $params){
    $p = AuthCore::requireRole(['super_admin']);
    $userId = (int)$params['id'];
    $in = Response::input();
    
    // Validate input
    if (empty($in['action']) || !in_array($in['action'], ['delete', 'block'])) {
      return Response::json([
        'success' => false,
        'message' => 'Action is required and must be either "delete" or "block"'
      ], 422);
    }
    
    if (empty($in['reason'])) {
      return Response::json([
        'success' => false,
        'message' => 'Reason is required'
      ], 422);
    }
    
    $user = $this->users->findById($userId);
    if (!$user) {
      return Response::json([
        'success' => false,
        'message' => 'User not found'
      ], 404);
    }
    
    // Cannot perform action on super admin
    if ($user['role'] === 'super_admin') {
      return Response::json([
        'success' => false,
        'message' => 'Cannot perform action on super admin'
      ], 403);
    }
    
    // Cannot perform action on yourself
    if ($userId === (int)$p['sub']) {
      return Response::json([
        'success' => false,
        'message' => 'Cannot perform action on your own account'
      ], 403);
    }
    
    $success = false;
    $message = '';
    
    if ($in['action'] === 'delete') {
      if ($user['is_deleted']) {
        return Response::json([
          'success' => false,
          'message' => 'User is already deleted'
        ], 409);
      }
      
      $success = $this->users->deleteUser($userId, (int)$p['sub'], $in['reason']);
      $message = $success ? 'User deleted successfully' : 'Failed to delete user';
    } else { // block
      if ($user['is_deleted']) {
        return Response::json([
          'success' => false,
          'message' => 'Cannot block deleted user'
        ], 409);
      }
      
      if ($user['is_blocked']) {
        return Response::json([
          'success' => false,
          'message' => 'User is already blocked'
        ], 409);
      }
      
      $success = $this->users->blockUser($userId, (int)$p['sub'], $in['reason']);
      $message = $success ? 'User blocked successfully' : 'Failed to block user';
    }
    
    if ($success) {
      return Response::json([
        'success' => true,
        'message' => $message,
        'action' => $in['action'],
        'user' => [
          'id' => $user['id'],
          'name' => $user['name'],
          'email' => $user['email']
        ]
      ]);
    }
    
    return Response::json([
      'success' => false,
      'message' => $message
    ], 500);
  }
  
  // POST /api/admin/faculty - Create faculty (Super Admin only)
  public function createFaculty() {
    $p = AuthCore::requireRole(['super_admin']);
    $in = Response::input();
    
    // Validate input
    $requiredFields = ['name', 'email', 'phone', 'password'];
    foreach ($requiredFields as $field) {
      if (empty($in[$field])) {
        return Response::json([
          'success' => false,
          'message' => ucfirst($field) . ' is required'
        ], 422);
      }
    }
    
    // Validate email format
    if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL)) {
      return Response::json([
        'success' => false,
        'message' => 'Invalid email format'
      ], 422);
    }
    
    // Check if email already exists
    $existingUser = $this->users->findByEmail($in['email']);
    if ($existingUser) {
      return Response::json([
        'success' => false,
        'message' => 'Email already registered'
      ], 409);
    }
    
    // Create faculty user
    $passwordHash = password_hash($in['password'], PASSWORD_DEFAULT);
    $userData = [
      'name' => $in['name'],
      'email' => $in['email'],
      'phone' => $in['phone'],
      'password' => $in['password'], // Will be hashed in the method
      'role' => 'faculty',
      'is_onboarding_done' => true
    ];
    
    $facultyId = $this->users->createSuperAdmin($userData);
    
    if ($facultyId) {
      // Update additional faculty details if provided
      $additionalFields = [];
      foreach (['bio', 'expertise', 'interests', 'experience', 'company', 'linkedin_url', 'github_url', 'whatsapp'] as $field) {
        if (isset($in[$field])) {
          $additionalFields[$field] = $in[$field];
        }
      }
      
      if (!empty($additionalFields)) {
        $this->users->updateProfile($facultyId, $additionalFields);
      }
      
      // Get the created faculty
      $faculty = $this->users->findById($facultyId);
      
      return Response::json([
        'success' => true,
        'message' => 'Faculty created successfully',
        'faculty' => [
          'id' => $faculty['id'],
          'name' => $faculty['name'],
          'email' => $faculty['email'],
          'role' => $faculty['role']
        ]
      ], 201);
    }
    
    return Response::json([
      'success' => false,
      'message' => 'Failed to create faculty'
    ], 500);
  }

  // GET /api/admin/faculty - List all faculty (Super Admin only)
  public function listFaculty() {
    $p = AuthCore::requireRole(['super_admin', 'admin']);
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    
    // Get faculty list (users with role='faculty')
    $sql = "SELECT id, name, email, phone, bio, expertise, experience, company,
                   linkedin_url, github_url, interests, whatsapp, profile_picture, created_at
            FROM users 
            WHERE role = 'faculty' AND is_deleted = 0 
            ORDER BY name ASC 
            LIMIT " . (($page - 1) * $limit) . ", " . $limit;
    
    $db = $this->users->getDatabase();
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $faculty = $stmt->fetchAll(\PDO::FETCH_ASSOC);
    
    // Count total faculty
    $countStmt = $db->prepare("SELECT COUNT(*) as total FROM users WHERE role = 'faculty' AND is_deleted = 0");
    $countStmt->execute();
    $total = (int)$countStmt->fetch(\PDO::FETCH_ASSOC)['total'];
    
    return Response::json([
      'success' => true,
      'faculty' => $faculty,
      'page' => $page,
      'limit' => $limit,
      'total' => $total,
      'total_pages' => ceil($total / $limit)
    ]);
  }

  // GET /api/admin/faculty/:id - Get faculty details (Super Admin only)
  public function getFacultyDetails(array $params) {
    $p = AuthCore::requireRole(['super_admin', 'admin']);
    $facultyId = (int)$params['id'];
    
    // Get faculty details
    $faculty = $this->users->findById($facultyId);
    
    if (!$faculty) {
      return Response::json([
        'success' => false,
        'message' => 'Faculty not found'
      ], 404);
    }
    
    if ($faculty['role'] !== 'faculty') {
      return Response::json([
        'success' => false,
        'message' => 'User is not a faculty member'
      ], 400);
    }
    
    // Get assigned subjects
    $db = $this->users->getDatabase();
    $subjectsStmt = $db->prepare("
      SELECT s.id, s.name, s.description, s.code, s.credits, s.semester, s.department
      FROM subjects s
      JOIN subject_faculty sf ON s.id = sf.subject_id
      WHERE sf.faculty_id = ?
    ");
    $subjectsStmt->execute([$facultyId]);
    $subjects = $subjectsStmt->fetchAll(\PDO::FETCH_ASSOC);
    
    // Remove sensitive data
    unset($faculty['password_hash']);
    unset($faculty['auth_token']);
    unset($faculty['token_expires_at']);
    
    $facultyData = [
      'id' => $faculty['id'],
      'name' => $faculty['name'],
      'email' => $faculty['email'],
      'phone' => $faculty['phone'],
      'bio' => $faculty['bio'],
      'expertise' => $faculty['expertise'],
      'experience' => $faculty['experience'],
      'company' => $faculty['company'],
      'linkedin_url' => $faculty['linkedin_url'],
      'github_url' => $faculty['github_url'],
      'interests' => $faculty['interests'],
      'whatsapp' => $faculty['whatsapp'],
      'profile_picture' => $faculty['profile_picture'],
      'created_at' => $faculty['created_at'],
      'subjects' => $subjects
    ];
    
    return Response::json([
      'success' => true,
      'faculty' => $facultyData
    ]);
  }

  // PUT /api/admin/faculty/:id - Update faculty details (Super Admin only)
  public function updateFaculty(array $params) {
    $p = AuthCore::requireRole(['super_admin']);
    $facultyId = (int)$params['id'];
    $in = Response::input();
    
    // Check if faculty exists
    $faculty = $this->users->findById($facultyId);
    
    if (!$faculty) {
      return Response::json([
        'success' => false,
        'message' => 'Faculty not found'
      ], 404);
    }
    
    if ($faculty['role'] !== 'faculty') {
      return Response::json([
        'success' => false,
        'message' => 'User is not a faculty member'
      ], 400);
    }
    
    // Update only allowed fields
    $allowedFields = [
      'name', 'phone', 'bio', 'expertise', 'experience', 
      'company', 'linkedin_url', 'github_url', 'interests', 'whatsapp'
    ];
    
    $updateData = [];
    foreach ($allowedFields as $field) {
      if (isset($in[$field])) {
        $updateData[$field] = $in[$field];
      }
    }
    
    // Update email separately with validation
    if (!empty($in['email'])) {
      if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL)) {
        return Response::json([
          'success' => false,
          'message' => 'Invalid email format'
        ], 422);
      }
      
      // Check if email is already taken by another user
      $existingUser = $this->users->findByEmail($in['email']);
      if ($existingUser && $existingUser['id'] != $facultyId) {
        return Response::json([
          'success' => false,
          'message' => 'Email already registered to another user'
        ], 409);
      }
      
      $updateData['email'] = $in['email'];
    }
    
    // Update password if provided
    if (!empty($in['password'])) {
      $updateData['password_hash'] = password_hash($in['password'], PASSWORD_DEFAULT);
    }
    
    // Update faculty
    if (!empty($updateData)) {
      $success = $this->users->updateProfile($facultyId, $updateData);
      
      if ($success) {
        // Get updated faculty
        $updatedFaculty = $this->users->findById($facultyId);
        
        return Response::json([
          'success' => true,
          'message' => 'Faculty updated successfully',
          'faculty' => [
            'id' => $updatedFaculty['id'],
            'name' => $updatedFaculty['name'],
            'email' => $updatedFaculty['email'],
            'role' => $updatedFaculty['role']
          ]
        ]);
      }
      
      return Response::json([
        'success' => false,
        'message' => 'Failed to update faculty'
      ], 500);
    }
    
    return Response::json([
      'success' => false,
      'message' => 'No data provided for update'
    ], 422);
  }

  // DELETE /api/admin/faculty/:id - Delete faculty (Super Admin only)
  public function deleteFaculty(array $params) {
    $p = AuthCore::requireRole(['super_admin']);
    $facultyId = (int)$params['id'];
    $in = Response::input();
    
    // Validate input
    if (empty($in['reason'])) {
      return Response::json([
        'success' => false,
        'message' => 'Deletion reason is required'
      ], 422);
    }
    
    // Check if faculty exists
    $faculty = $this->users->findById($facultyId);
    
    if (!$faculty) {
      return Response::json([
        'success' => false,
        'message' => 'Faculty not found'
      ], 404);
    }
    
    if ($faculty['role'] !== 'faculty') {
      return Response::json([
        'success' => false,
        'message' => 'User is not a faculty member'
      ], 400);
    }
    
    if ($faculty['is_deleted']) {
      return Response::json([
        'success' => false,
        'message' => 'Faculty is already deleted'
      ], 409);
    }
    
    // Delete faculty
    $success = $this->users->deleteUser($facultyId, (int)$p['sub'], $in['reason']);
    
    if ($success) {
      return Response::json([
        'success' => true,
        'message' => 'Faculty deleted successfully',
        'faculty' => [
          'id' => $faculty['id'],
          'name' => $faculty['name'],
          'email' => $faculty['email']
        ]
      ]);
    }
    
    return Response::json([
      'success' => false,
      'message' => 'Failed to delete faculty'
    ], 500);
  }
}
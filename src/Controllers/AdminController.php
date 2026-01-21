<?php
namespace Controllers;
use Core\Response;
use Core\Auth as AuthCore;
use Models\UserModel;

class AdminController {
  private UserModel $users;
  private array $config;
  
  public function __construct(){ 
    $this->users = new UserModel(); 
    $this->config = require __DIR__ . '/../../config/config.php';
  }

  // POST /admin/create-super-admin - Create super admin with secret key
  public function createSuperAdmin(){
    $in = Response::input();
    
    // Validate required fields
    if (empty($in['master_id']) || empty($in['secret_key'])) {
      return Response::json([
        'success' => false,
        'message' => 'master_id and secret_key are required'
      ], 422);
    }
    
    // Verify master credentials
    if ($in['master_id'] !== $this->config['super_admin_master_id'] || 
        $in['secret_key'] !== $this->config['super_admin_secret']) {
      return Response::json([
        'success' => false,
        'message' => 'Invalid master credentials'
      ], 403);
    }
    
    // Validate super admin data
    $requiredFields = ['name', 'email', 'phone'];
    foreach ($requiredFields as $field) {
      if (empty($in[$field])) {
        return Response::json([
          'success' => false,
          'message' => "Field '$field' is required"
        ], 422);
      }
    }
    
    // For super admin, we need either password OR google_id
    if (empty($in['password']) && empty($in['google_id'])) {
      return Response::json([
        'success' => false,
        'message' => 'Either password or google_id is required'
      ], 422);
    }
    
    // Check if super admin already exists
    $existingSuperAdmin = $this->users->findByRole('super_admin');
    if ($existingSuperAdmin) {
      return Response::json([
        'success' => false,
        'message' => 'Super admin already exists',
        'existing_super_admin' => [
          'id' => $existingSuperAdmin['id'],
          'name' => $existingSuperAdmin['name'],
          'email' => $existingSuperAdmin['email']
        ]
      ], 409);
    }
    
    // Check if email is already taken
    $existingUser = $this->users->findByEmail($in['email']);
    if ($existingUser) {
      return Response::json([
        'success' => false,
        'message' => 'Email address is already registered'
      ], 409);
    }
    
    // Validate password strength if password is provided
    if (!empty($in['password']) && strlen($in['password']) < 8) {
      return Response::json([
        'success' => false,
        'message' => 'Password must be at least 8 characters long'
      ], 422);
    }
    
    // Create super admin
    $superAdminData = [
      'name' => $in['name'],
      'email' => $in['email'],
      'phone' => $in['phone'],
      'password' => $in['password'] ?? null,
      'google_id' => $in['google_id'] ?? null,
      'role' => 'super_admin',
      'is_onboarding_done' => true
    ];
    
    $superAdminId = $this->users->createSuperAdmin($superAdminData);
    
    if ($superAdminId) {
      // Get created super admin data (without password)
      $createdSuperAdmin = $this->users->findById($superAdminId);
      unset($createdSuperAdmin['password_hash']);
      
      return Response::json([
        'success' => true,
        'message' => 'Super admin created successfully',
        'super_admin' => [
          'id' => $createdSuperAdmin['id'],
          'name' => $createdSuperAdmin['name'],
          'email' => $createdSuperAdmin['email'],
          'phone' => $createdSuperAdmin['phone'],
          'role' => $createdSuperAdmin['role'],
          'created_at' => $createdSuperAdmin['created_at']
        ],
        'next_steps' => [
          'login_url' => '/api/auth/login',
          'admin_requests_url' => '/api/admin/requests',
          'note' => 'Use the created credentials to login and manage admin requests'
        ]
      ], 201);
    }
    
    return Response::json([
      'success' => false,
      'message' => 'Failed to create super admin'
    ], 500);
  }

  // POST /admin/setup-database - Setup missing database tables (Emergency endpoint)
  public function setupDatabase(){
    $in = Response::input();
    
    // Require master credentials for security
    if (empty($in['master_id']) || empty($in['secret_key'])) {
      return Response::json([
        'success' => false,
        'message' => 'master_id and secret_key are required'
      ], 422);
    }
    
    if ($in['master_id'] !== $this->config['super_admin_master_id'] || 
        $in['secret_key'] !== $this->config['super_admin_secret']) {
      return Response::json([
        'success' => false,
        'message' => 'Invalid master credentials'
      ], 403);
    }
    
    try {
      $db = $this->users->getDatabase();
      $results = [];
      
      // Check and create notification_preferences table
      $checkNotifPrefs = $db->query("SHOW TABLES LIKE 'notification_preferences'")->fetch();
      
      if (!$checkNotifPrefs) {
        $createNotifPrefsSql = "CREATE TABLE notification_preferences (
          id INT AUTO_INCREMENT PRIMARY KEY,
          user_id INT NOT NULL,
          push_notifications BOOLEAN NOT NULL DEFAULT TRUE,
          email_notifications BOOLEAN NOT NULL DEFAULT FALSE,
          academic_notifications BOOLEAN NOT NULL DEFAULT TRUE,
          social_notifications BOOLEAN NOT NULL DEFAULT TRUE,
          admin_notifications BOOLEAN NOT NULL DEFAULT FALSE,
          system_notifications BOOLEAN NOT NULL DEFAULT TRUE,
          quiet_hours_enabled BOOLEAN NOT NULL DEFAULT FALSE,
          quiet_hours_start TIME NULL,
          quiet_hours_end TIME NULL,
          created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
          updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
          UNIQUE KEY uniq_user_pref (user_id),
          FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        
        $db->exec($createNotifPrefsSql);
        $results[] = 'notification_preferences table created';
      } else {
        $results[] = 'notification_preferences table already exists';
      }
      
      return Response::json([
        'success' => true,
        'message' => 'Database setup completed',
        'results' => $results
      ]);
      
    } catch (Exception $e) {
      return Response::json([
        'success' => false,
        'message' => 'Database setup failed: ' . $e->getMessage()
      ], 500);
    }
  }

  // GET /admin/quick-setup - Quick database setup (for testing)
  public function quickSetup(){
    try {
      return Response::json([
        'success' => true,
        'message' => 'Quick database setup completed - using users table for admin requests',
        'note' => 'Admin requests now use the users table columns instead of separate table'
      ]);
      
    } catch (Exception $e) {
      return Response::json([
        'success' => false,
        'message' => 'Quick setup failed: ' . $e->getMessage()
      ], 500);
    }
  }

  // GET /admin/clear-all-data - Clear all data from database (Emergency endpoint)
  public function clearAllData(){
    try {
      $db = $this->users->getDatabase();
      
      // Disable foreign key checks
      $db->exec('SET FOREIGN_KEY_CHECKS = 0');
      
      // List of all tables to clear
      $tables = [
        'notification_preferences',
        'notifications',
        'connections',
        'schedules',
        'posts',
        'notes',
        'enrollments',
        'subjects',
        'users'
      ];
      
      $results = [];
      foreach ($tables as $table) {
        $stmt = $db->query("SELECT COUNT(*) as count FROM $table");
        $count = $stmt->fetch()['count'];
        
        $db->exec("DELETE FROM $table");
        $db->exec("ALTER TABLE $table AUTO_INCREMENT = 1");
        
        $results[] = "Deleted $count records from $table";
      }
      
      // Re-enable foreign key checks
      $db->exec('SET FOREIGN_KEY_CHECKS = 1');
      
      return Response::json([
        'success' => true,
        'message' => 'All data deleted successfully',
        'results' => $results,
        'note' => 'Database is now empty but structure preserved'
      ]);
      
    } catch (Exception $e) {
      return Response::json([
        'success' => false,
        'message' => 'Failed to clear data: ' . $e->getMessage()
      ], 500);
    }
  }

  // GET /admin/list - Get list of all admins
  public function getAdminList(){
    $admins = $this->users->getAdminList();
    
    $formattedAdmins = [];
    foreach ($admins as $admin) {
      $formattedAdmins[] = [
        'user_id' => $admin['id'],
        'name' => $admin['name'],
        'email' => $admin['email'],
        'role' => $admin['role'],
        'company' => $admin['company'],
        'expertise' => $admin['expertise'],
        'profile_picture' => $admin['profile_picture'],
        'admin_since' => $admin['created_at']
      ];
    }
    
    return Response::json([
      'success' => true,
      'admins' => $formattedAdmins
    ]);
  }

  // GET /admin/requests - Get admin requests (Super Admin only)
  public function getAdminRequests(){
    $p = AuthCore::requireUser();
    
    // Check if user is super admin
    if ($p['role'] !== 'super_admin') {
      return Response::json([
        'success' => false,
        'message' => 'Only super admins can view admin requests',
        'statusCode' => 403
      ], 403);
    }
    
    $status = $_GET['status'] ?? null;
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    
    $requests = $this->users->getAllAdminRequests($status, $page, $limit);
    
    // Format requests
    $formattedRequests = [];
    foreach ($requests as $request) {
      $formattedRequests[] = [
        'request_id' => $request['id'], // Using user id as request identifier
        'user' => [
          'user_id' => $request['id'],
          'name' => $request['name'],
          'email' => $request['email'],
          'phone' => $request['phone'],
          'company' => $request['company'],
          'expertise' => $request['expertise'],
          'experience' => $request['experience']
        ],
        'status' => $request['admin_status'],
        'requested_at' => $request['created_at']
      ];
    }
    
    // Calculate pagination
    $totalRequests = $this->users->getTotalAdminRequestsCount($status);
    $totalPages = ceil($totalRequests / $limit);
    
    return Response::json([
      'success' => true,
      'requests' => $formattedRequests,
      'pagination' => [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_requests' => $totalRequests,
        'has_next' => $page < $totalPages,
        'has_prev' => $page > 1
      ]
    ]);
  }

  // PATCH /admin/users/{user_id}/demote - Demote admin to normal user
  public function demoteAdminToUser(array $params) {
    $p = AuthCore::requireUser();
    $userId = (int)$params['id'];

    // Check if user is super admin
    if ($p['role'] !== 'super_admin') {
      return Response::json([
        'success' => false,
        'message' => 'Only super admins can demote admins'
      ], 403);
    }

    // Get and validate user
    $user = $this->users->findById($userId);
    if (!$user) {
      return Response::json([
        'success' => false,
        'message' => 'User not found'
      ], 404);
    }

    if ($user['role'] !== 'admin') {
      return Response::json([
        'success' => false,
        'message' => 'User is not an admin'
      ], 400);
    }

    // Process the demotion with automatic admin request fields reset
    $success = $this->users->changeUserRole($userId, 'user');

    if ($success) {
      return Response::json([
        'success' => true,
        'message' => 'Admin successfully demoted to user. Admin request status has been reset.',
        'user' => [
          'id' => $user['id'],
          'name' => $user['name'],
          'email' => $user['email'],
          'previous_role' => 'admin',
          'new_role' => 'user'
        ],
        'admin_request_reset' => true
      ]);
    }

    return Response::json([
      'success' => false,
      'message' => 'Failed to demote admin'
    ], 500);
  }

  // PATCH /admin/users/{user_id}/role - Change user role (Super Admin only)
  public function changeUserRole(array $params) {
    $p = AuthCore::requireUser();
    $userId = (int)$params['id'];
    $in = Response::input();

    // Check if user is super admin
    if ($p['role'] !== 'super_admin') {
      return Response::json([
        'success' => false,
        'message' => 'Only super admins can change user roles'
      ], 403);
    }

    // Validate input
    if (empty($in['role'])) {
      return Response::json([
        'success' => false,
        'message' => 'Role is required'
      ], 422);
    }

    $newRole = $in['role'];
    $validRoles = ['user', 'admin', 'super_admin', 'faculty'];
    
    if (!in_array($newRole, $validRoles)) {
      return Response::json([
        'success' => false,
        'message' => 'Invalid role. Valid roles are: ' . implode(', ', $validRoles)
      ], 422);
    }

    // Get and validate user
    $user = $this->users->findById($userId);
    if (!$user) {
      return Response::json([
        'success' => false,
        'message' => 'User not found'
      ], 404);
    }

    // Prevent changing own role
    if ($userId === (int)$p['sub']) {
      return Response::json([
        'success' => false,
        'message' => 'Cannot change your own role'
      ], 403);
    }

    // Prevent changing another super admin's role
    if ($user['role'] === 'super_admin') {
      return Response::json([
        'success' => false,
        'message' => 'Cannot change super admin role'
      ], 403);
    }

    $previousRole = $user['role'];
    
    // Process the role change with automatic admin request fields handling
    $success = $this->users->changeUserRole($userId, $newRole);

    if ($success) {
      $response = [
        'success' => true,
        'message' => "User role successfully changed from {$previousRole} to {$newRole}",
        'user' => [
          'id' => $user['id'],
          'name' => $user['name'],
          'email' => $user['email'],
          'previous_role' => $previousRole,
          'new_role' => $newRole
        ]
      ];

      // Add note about admin request reset if applicable
      if ($previousRole === 'admin' && $newRole === 'user') {
        $response['admin_request_reset'] = true;
        $response['message'] .= '. Admin request status has been reset.';
      }

      return Response::json($response);
    }

    return Response::json([
      'success' => false,
      'message' => 'Failed to change user role'
    ], 500);
  }

  // PATCH /admin/requests/{user_id} - Approve/reject admin request
  public function updateAdminRequest(array $params){
    $p = AuthCore::requireUser();
    $userId = (int)$params['id'];
    $in = Response::input();
    
    // Validation
    $errors = [];
    if ($p['role'] !== 'super_admin') {
      $errors[] = ['message' => 'Only super admins can approve/reject admin requests', 'code' => 403];
    }
    if (empty($in['action']) || !in_array($in['action'], ['approve', 'reject'])) {
      $errors[] = ['message' => 'Action must be either "approve" or "reject"', 'code' => 422];
    }
    
    if (!empty($errors)) {
      $error = $errors[0];
      return Response::json(['success' => false, 'message' => $error['message']], $error['code']);
    }
    
    $action = $in['action'];
    $notes = $in['notes'] ?? null;
    
    // Get and validate user
    $user = $this->users->findById($userId);
    if (!$user || !$user['admin_request'] || $user['admin_status'] !== 'pending') {
      return Response::json([
        'success' => false,
        'message' => 'Pending admin request not found for this user'
      ], 404);
    }
    
    // Process the request
    $success = $this->users->updateAdminRequest($userId, $action, (int)$p['sub'], $notes);
    $statusCode = $success ? 200 : 500;
    $message = $success
      ? ucfirst($action) . 'd admin request successfully'
      : 'Failed to update admin request';
    
    $response = ['success' => $success, 'message' => $message];
    
    if ($success) {
      $userUpdated = null;
      if ($action === 'approve') {
        $userUpdated = ['user_id' => $userId, 'new_role' => 'admin'];
      }
      
      $response['request'] = [
        'request_id' => $userId,
        'user_id' => $userId,
        'user_name' => $user['name'],
        'user_email' => $user['email'],
        'status' => $action . 'd',
        'action_taken_by' => $p['sub'],
        'action_notes' => $notes,
        'action_taken_at' => date('Y-m-d\TH:i:s\Z')
      ];
      $response['user_updated'] = $userUpdated;
    }
    
    return Response::json($response, $statusCode);
  }

  // GET /admin/class-status - Get all subjects with their class status
  public function getClassStatuses() {
    // $p = AuthCore::requireUser();
    
    // // Check if user is admin or super_admin
    // if (!in_array($p['role'], ['admin', 'super_admin'])) {
    //   return Response::json([
    //     'success' => false,
    //     'message' => 'Only admins can access this resource',
    //     'statusCode' => 403
    //   ], 403);
    // }
    
    $semester = $_GET['semester'] ?? null;
    
    try {
      $db = $this->users->getDatabase();
      
      $sql = "SELECT id, name, semester, saturday_status, sunday_status
              FROM subjects";
      
      if ($semester !== null) {
        $sql .= " WHERE semester = :semester";
      }
      
      $sql .= " ORDER BY name";
      
      $stmt = $db->prepare($sql);
      
      if ($semester !== null) {
        $stmt->execute(['semester' => $semester]);
      } else {
        $stmt->execute();
      }
      
      $subjects = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      return Response::json([
        'success' => true,
        'subjects' => $subjects
      ]);
    } catch (\Exception $e) {
      return Response::json([
        'success' => false,
        'message' => 'Failed to fetch class statuses: ' . $e->getMessage()
      ], 500);
    }
  }

  // POST /admin/class-status - Update class status for a subject
  public function updateClassStatus() {
    // $p = AuthCore::requireUser();
    
    // // Check if user is admin or super_admin
    // if (!in_array($p['role'], ['admin', 'super_admin'])) {
    //   return Response::json([
    //     'success' => false,
    //     'message' => 'Only admins can access this resource',
    //     'statusCode' => 403
    //   ], 403);
    // }
    
    $in = Response::input();

    // Validate required fields
    if (empty($in['subject_id'])) {
      return Response::json([
        'success' => false,
        'message' => 'subject_id is required'
      ], 422);
    }

    $subjectId = (int)$in['subject_id'];
    $saturdayStatus = $in['saturday_status'] ?? 'Not Confirm';
    $sundayStatus = $in['sunday_status'] ?? 'Not Confirm';

    // Validate status values
    $validStatuses = ['Confirm', 'Not Confirm', 'Cancelled'];
    if (!in_array($saturdayStatus, $validStatuses) || !in_array($sundayStatus, $validStatuses)) {
      return Response::json([
        'success' => false,
        'message' => 'Invalid status value. Must be one of: ' . implode(', ', $validStatuses)
      ], 422);
    }

    try {
      $db = $this->users->getDatabase();
      
      // Check if subject exists
      $stmt = $db->prepare("SELECT id FROM subjects WHERE id = ?");
      $stmt->execute([$subjectId]);
      if (!$stmt->fetch()) {
        return Response::json([
          'success' => false,
          'message' => 'Subject not found'
        ], 404);
      }

      // Update class status in subjects table
      $stmt = $db->prepare("
        UPDATE subjects 
        SET saturday_status = ?, sunday_status = ?, updated_at = CURRENT_TIMESTAMP
        WHERE id = ?
      ");
      $success = $stmt->execute([$saturdayStatus, $sundayStatus, $subjectId]);

      if ($success) {
        return Response::json([
          'success' => true,
          'message' => 'Class status updated successfully'
        ]);
      } else {
        return Response::json([
          'success' => false,
          'message' => 'Failed to update class status'
        ], 500);
      }
    } catch (\Exception $e) {
      return Response::json([
        'success' => false,
        'message' => 'Failed to update class status: ' . $e->getMessage()
      ], 500);
    }
  }
}
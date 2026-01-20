<?php
namespace Controllers;
use Core\Response;
use Core\Auth as AuthCore;
use Models\UserModel;
use DateTime;

class ProfileController {
  private UserModel $users;
  public function __construct(){ $this->users = new UserModel(); }

  // GET /user/profile - Get current user's complete profile
  public function getProfile(){
    $p = AuthCore::requireUser();
    $user = $this->users->findById((int)$p['sub']);
    
    if (!$user) {
      return Response::json([
        'success' => false,
        'message' => 'User not found'
      ], 404);
    }
    
    unset($user['password_hash'], $user['token_expires_at']);
    $user['is_onboarding_done'] = (bool)$user['is_onboarding_done'];
    $user['admin_request'] = (bool)$user['admin_request'];
    
    // Ensure admin_status is properly set - if admin_request is true but admin_status is null, set it to pending
    if ($user['admin_request'] && !$user['admin_status']) {
      $user['admin_status'] = 'pending';
    }
    
    return Response::json([
      'success' => true,
      'user' => $user
    ]);
  }

  // PUT /user/profile - Update user's profile information
  public function updateProfile(){
    $p = AuthCore::requireUser();
    $in = Response::input();
    
    $allowedFields = ['name', 'phone', 'whatsapp', 'age', 'company', 'expertise', 'interests', 'experience', 'linkedin_url', 'github_url', 'bio', 'admin_request'];
    $updateData = [];
    
    foreach ($allowedFields as $field) {
      if (isset($in[$field])) {
        $updateData[$field] = $in[$field];
      }
    }
    
    // Handle admin request status
    if (isset($updateData['admin_request'])) {
      // Handle empty string case for boolean conversion
      if ($updateData['admin_request'] === '' || $updateData['admin_request'] === null) {
        $updateData['admin_request'] = false;
      } else {
        // Convert to boolean - handles 'true', '1', 1, true, 'false', '0', 0, false
        $updateData['admin_request'] = filter_var($updateData['admin_request'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        if ($updateData['admin_request'] === null) {
          $updateData['admin_request'] = false;
        }
      }
      
      // Convert boolean to integer for database storage (MySQL BOOLEAN is actually TINYINT)
      $updateData['admin_request'] = $updateData['admin_request'] ? 1 : 0;
      
      // Set admin_status to 'pending' if admin_request is true
      if ($updateData['admin_request']) {
        $updateData['admin_status'] = 'pending';
      }
    }
    
    if (empty($updateData)) {
      return Response::json([
        'success' => false,
        'message' => 'No valid fields provided for update'
      ], 422);
    }

    // Validate date of birth if provided
    if (isset($updateData['age']) && !empty($updateData['age'])) {
      $dob = $updateData['age'];
      $dobDate = date_create_from_format('Y-m-d', $dob);
      
      if (!$dobDate) {
        return Response::json([
          'success' => false,
          'message' => 'Date of birth must be in YYYY-MM-DD format'
        ], 422);
      }
      
      // Calculate age
      $today = new DateTime();
      $age = $today->diff($dobDate)->y;
      
      // Validate age range (18-100)
      if ($age < 16 || $age > 100) {
        return Response::json([
          'success' => false,
          'message' => 'Age must be between 18 and 100 years'
        ], 422);
      }
      
      $updateData['age'] = $dobDate->format('Y-m-d');
    } elseif (isset($updateData['age']) && empty($updateData['age'])) {
      // If age is provided but empty, set it to null
      $updateData['age'] = null;
    }
    
    // Validate LinkedIn URL if provided
    if (isset($updateData['linkedin_url']) && !empty($updateData['linkedin_url']) && !filter_var($updateData['linkedin_url'], FILTER_VALIDATE_URL)) {
      return Response::json([
        'success' => false,
        'message' => 'Invalid LinkedIn URL format'
      ], 422);
    }
    
    // Validate GitHub URL if provided
    if (isset($updateData['github_url']) && !empty($updateData['github_url']) && !filter_var($updateData['github_url'], FILTER_VALIDATE_URL)) {
      return Response::json([
        'success' => false,
        'message' => 'Invalid GitHub URL format'
      ], 422);
    }
    
    // Validate interests if provided
    if (isset($updateData['interests'])) {
      if (!is_array($updateData['interests'])) {
        return Response::json([
          'success' => false,
          'message' => 'Interests must be an array'
        ], 422);
      }
      
      foreach ($updateData['interests'] as $interest) {
        if (!is_string($interest) || trim($interest) === '') {
          return Response::json([
            'success' => false,
            'message' => 'All interests must be non-empty strings'
          ], 422);
        }
      }
      
      // Convert interests array to comma-separated string for database storage (like expertise)
      $updateData['interests'] = implode(',', $updateData['interests']);
    }

    // Handle expertise field - convert array to comma-separated string if needed (same as interests)
    if (isset($updateData['expertise']) && is_array($updateData['expertise'])) {
      foreach ($updateData['expertise'] as $expertise) {
        if (!is_string($expertise) || trim($expertise) === '') {
          return Response::json([
            'success' => false,
            'message' => 'All expertise items must be non-empty strings'
          ], 422);
        }
      }
      // Convert expertise array to comma-separated string for database storage
      $updateData['expertise'] = implode(',', $updateData['expertise']);
    }

    $success = $this->users->updateProfile((int)$p['sub'], $updateData);
    
    if ($success) {
      // Fetch the complete updated profile
      $updatedUser = $this->users->findById((int)$p['sub']);
      
      // Remove sensitive data
      unset($updatedUser['password_hash'], $updatedUser['auth_token'], $updatedUser['token_expires_at']);
      $updatedUser['is_onboarding_done'] = (bool)$updatedUser['is_onboarding_done'];
      $updatedUser['admin_request'] = (bool)$updatedUser['admin_request'];
      
      // Ensure admin_status is properly set - if admin_request is true but admin_status is null, set it to pending
      if ($updatedUser['admin_request'] && !$updatedUser['admin_status']) {
        $updatedUser['admin_status'] = 'pending';
      }
      
      return Response::json([
        'success' => true,
        'message' => 'Profile updated successfully',
        'user' => $updatedUser
      ]);
    }
    
    return Response::json([
      'success' => false,
      'message' => 'Failed to update profile'
    ], 500);
  }

  // POST /user/profile/picture - Upload profile picture
  public function uploadProfilePicture(){
    $p = AuthCore::requireUser();
    
    if (!isset($_FILES['picture']) || $_FILES['picture']['error'] !== UPLOAD_ERR_OK) {
      return Response::json([
        'success' => false,
        'message' => 'No valid image file uploaded'
      ], 422);
    }
    
    $file = $_FILES['picture'];
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    
    if (!in_array($file['type'], $allowedTypes)) {
      return Response::json([
        'success' => false,
        'message' => 'Only JPEG and PNG images are allowed'
      ], 422);
    }
    
    // Check file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
      return Response::json([
        'success' => false,
        'message' => 'File size must be less than 5MB'
      ], 422);
    }
    
    // Generate unique filename
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'profile_' . $p['sub'] . '_' . time() . '.' . $extension;
    $uploadDir = __DIR__ . '/../../uploads/profiles/';
    
    // Create directory if it doesn't exist
    if (!is_dir($uploadDir)) {
      mkdir($uploadDir, 0755, true);
    }
    
    $uploadPath = $uploadDir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
      $profilePictureUrl = '/uploads/profiles/' . $filename;
      
      // Update user's profile picture in database
      $success = $this->users->updateProfilePicture((int)$p['sub'], $profilePictureUrl);
      
      if ($success) {
        return Response::json([
          'success' => true,
          'message' => 'Profile picture updated successfully',
          'profile_picture' => $profilePictureUrl,
          'updated_at' => date('Y-m-d\TH:i:s\Z')
        ]);
      }
    }
    
    return Response::json([
      'success' => false,
      'message' => 'Failed to upload profile picture'
    ], 500);
  }

  // POST /user/admin-request - Request admin role
  public function requestAdminRole(){
    $p = AuthCore::requireUser();
    $in = Response::input();
    
    if (empty($in['reason'])) {
      return Response::json([
        'success' => false,
        'message' => 'Reason is required'
      ], 422);
    }
    
    // Check if user already has pending request
    $existingRequest = $this->users->getAdminRequest((int)$p['sub']);
    if ($existingRequest && $existingRequest['admin_status'] === 'pending') {
      return Response::json([
        'success' => false,
        'message' => 'You already have a pending admin request'
      ], 422);
    }
    
    $success = $this->users->createAdminRequest((int)$p['sub']);
    
    if ($success) {
      return Response::json([
        'success' => true,
        'message' => 'Admin request submitted successfully',
        'request' => [
          'request_id' => $p['sub'], // Using user_id as request identifier
          'user_id' => $p['sub'],
          'reason' => $in['reason'],
          'status' => 'pending',
          'requested_at' => date('Y-m-d\TH:i:s\Z')
        ]
      ]);
    }
    
    return Response::json([
      'success' => false,
      'message' => 'Failed to submit admin request'
    ], 500);
  }

  // Legacy methods for backward compatibility
  public function updateBasic(){
    $p = AuthCore::requireUser();
    $in = Response::input();
    if(empty($in['name']) || empty($in['phone'])) return Response::json(['error'=>'name and phone required'],422);
    $ok = $this->users->updateBasic((int)$p['sub'], $in['name'], $in['phone']);
    return Response::json(['ok'=>$ok]);
  }

  public function updateProfessional(){
    $p = AuthCore::requireUser();
    $in = Response::input();
    $bio = $in['bio'] ?? null; $links = $in['links'] ?? [];
    $ok = $this->users->updateProfessional((int)$p['sub'], $bio, json_encode($links));
    return Response::json(['ok'=>$ok]);
  }

  public function getPublic(array $params){
    $u = $this->users->findById((int)$params['id']);
    if(!$u) return Response::json(['error'=>'User not found'],404);
    
    // Remove sensitive data
    unset($u['password_hash'], $u['auth_token'], $u['token_expires_at'], $u['phone'], $u['whatsapp']);
    $u['is_onboarding_done'] = (bool)$u['is_onboarding_done'];
    
    return Response::json([
      'success' => true,
      'user' => $u
    ]);
  }
}

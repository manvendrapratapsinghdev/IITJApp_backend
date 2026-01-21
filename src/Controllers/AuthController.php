<?php
namespace Controllers;
use Core\Response;
use Core\Auth as AuthCore;
use Models\UserModel;
use DateTime;

class AuthController {
  private UserModel $users;
  private const DEFAULT_PASSWORD = 'IITJ@Campus2024#Secure';
  public function __construct(){ $this->users = new UserModel(); }


  public function googleAuth(){
    try {
      $in = Response::input();
      
      // Validate required fields
      foreach (['google_id', 'email'] as $f) {
        if (empty($in[$f])) { 
          throw new \Exception("Missing required field: $f");
        }
      }
      
      // Validate email format
      if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL)) {
        throw new \Exception('Invalid email format');
      }
    
      // Check if this is a super admin (bypass domain restrictions)
    $googleUser = $this->users->findByEmailAndGoogleId($in['email'], $in['google_id']);
    $isSuperAdmin = $googleUser && $googleUser['role'] === 'super_admin';

      // Validate IIT domain (skip for super admin)
      if (!$isSuperAdmin && !$this->isIITDomain($in['email'])) {
        throw new \Exception('Outside IIT not allowed. Please login with IIT ID');
      }
      
      // Validate G24 prefix (skip for super admin)
      if (!$isSuperAdmin && !$this->isG24Email($in['email'])) {
        throw new \Exception('This app is currently for MTech Jan-2025 Batch. We will launch the app for everyone soon.');
      }
      
      if ($googleUser) {
        // Check if user is blocked
        if ($googleUser['is_blocked']) {
          return Response::json([
            'error' => 'Account has been blocked by administrator, Please cordinate with Admin',
            'blocked_at' => $googleUser['blocked_at'],
            'reason' => $googleUser['block_reason'] ?? 'No reason provided'
          ], 403);
        }
        // If user was deleted, restore them
        if ($googleUser['is_deleted']) {
          $this->users->restoreUser((int)$googleUser['id']);
          $this->users->resetOnboarding((int)$googleUser['id']);
          $googleUser['is_onboarding_done'] = false;
        }
        $token = AuthCore::issueToken((int)$googleUser['id'], $googleUser['role']);
        $this->users->saveToken((int)$googleUser['id'], $token, null);
        if (!empty($in['device_token'])) {
          $this->users->updateDeviceToken((int)$googleUser['id'], $in['device_token']);
        }
        // Directly update token in user array
        $googleUser['auth_token'] = $token;
        unset($googleUser['password_hash']);
        $googleUser['is_onboarding_done'] = (bool)$googleUser['is_onboarding_done'];
        return Response::json([
          'user' => $googleUser,
          'is_first_time' => false,
          'action_taken' => 'login',
          'message' => 'Logged in successfully'
        ]);
      } else {
         // Check if user exists by BOTH Google ID and email
        $emailUser = $this->users->findByEmail($in['email']);
        $googleIdUser = $this->users->findByGoogleId($in['google_id']);
        // If either email or Google ID exists (but not both together), block signup
        if ($emailUser || $googleIdUser) {
          return Response::json([
            'error' => 'Please login with the email ID and Google account you have previously used.'
          ], 409);
        }
      }
    // User doesn't exist - SIGNUP flow
    $hash = password_hash(self::DEFAULT_PASSWORD, PASSWORD_DEFAULT); // Use default password instead of random
    
    $id = $this->users->createWithGoogle(
      $in['name'],
      $in['email'],
      $hash,
      $in['google_id'],
      'user'
    );
    
    $token = AuthCore::issueToken($id,'user');
    $this->users->saveToken($id, $token, null);

    return Response::json([
      'user' => [
        'id' => $id,
        'name' => $in['name'],
        'email' => $in['email'],
        'auth_token' => $token,
        'role' => 'user',
        'google_id' => $in['google_id'],
        'is_onboarding_done' => false
      ],
      'is_first_time' => true,
      'message' => 'Account created successfully with Google'
    ]);

    } catch (\Exception $e) {
      error_log('Google Auth Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
      return Response::json([
        'error' => $e->getMessage()
      ], 422);
    } catch (\Error $e) {
      error_log('Google Auth Critical Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
      return Response::json([
        'error' => 'An unexpected error occurred',
      ], 500);
    }
  }

  public function adminLogin(){
    $in = Response::input();
    
    // Validate required fields
    if (empty($in['email']) || empty($in['password'])) {
      return Response::json(['error' => 'Email and password are required'], 422);
    }
    
    // Validate email format
    if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL)) {
      return Response::json(['error' => 'Invalid email format'], 422);
    }
    
    // Find user by email
    $user = $this->users->findByEmail($in['email']);
    if (!$user) {
      return Response::json(['error' => 'Invalid credentials'], 401);
    }
    
    // Check if user has admin or super_admin role
    if (!in_array($user['role'], ['admin', 'super_admin'])) {
      return Response::json(['error' => 'Access denied. Admin privileges required.'], 403);
    }
    
    // Verify password
    if (!password_verify($in['password'], $user['password_hash'])) {
      return Response::json(['error' => 'Invalid credentials'], 401);
    }
    
    // Generate token
    $token = AuthCore::issueToken((int)$user['id'], $user['role']);
    
    // Save token to database
    $this->users->saveToken((int)$user['id'], $token, null);
    
    // Handle device token if provided and user is eligible
    if (!empty($in['device_token'])) {
      $this->users->updateDeviceToken((int)$user['id'], $in['device_token']);
    }
    
    // Remove sensitive data
    unset($user['password_hash']);
    $user['is_onboarding_done'] = (bool)$user['is_onboarding_done'];
    
    return Response::json([
      'success' => true,
      'token' => $token,
      'user' => $user,
      'message' => 'Login successful'
    ]);
  }

  public function logout(){
    $p = AuthCore::requireUser();
    $userId = (int)$p['sub'];
    
    // Clear authentication token
    $tokenCleared = $this->users->clearToken($userId);
    
    // Clear device token to stop push notifications
    $deviceTokenCleared = $this->users->clearDeviceToken($userId);
    
    // Log the logout action for security purposes
    error_log("User logout: ID={$userId}, Token cleared: " . ($tokenCleared ? 'Yes' : 'No') . ", Device token cleared: " . ($deviceTokenCleared ? 'Yes' : 'No'));
    
    if ($tokenCleared) {
      return Response::json([
        'success' => true,
        'message' => 'Logged out successfully',
        'details' => [
          'session_cleared' => true,
          'device_token_cleared' => $deviceTokenCleared,
          'push_notifications_disabled' => $deviceTokenCleared
        ]
      ]);
    } else {
      return Response::json([
        'success' => false,
        'error' => 'Failed to logout',
        'details' => [
          'session_cleared' => false,
          'device_token_cleared' => $deviceTokenCleared
        ]
      ], 500);
    }
  }

  public function completeOnboarding(){
    $p = AuthCore::requireUser();
    $in = Response::input();
    
    // Validation rules
    $requiredFields = ['name', 'phone', 'whatsapp', 'company', 'expertise', 'experience'];
    $errors = [];
    
    // Check required fields
    foreach ($requiredFields as $field) {
      if (empty($in[$field])) {
        $errors[$field] = ["The $field field is required."];
      }
    }
    
    // Validate date of birth if provided
    if (!empty($in['age'])) {
      $dobDate = date_create_from_format('Y-m-d', $in['age']);
      if (!$dobDate) {
        $errors['age'] = ["The date of birth must be in YYYY-MM-DD format."];
      } else {
        // Calculate age
        $today = new DateTime();
        $age = $today->diff($dobDate)->y;
        
        // Validate age range
        if ($age < 18 || $age > 100) {
          $errors['age'] = ["Age must be between 18 and 100 years."];
        }
      }
    }

    // Validate experience is numeric
    if (!empty($in['experience']) && !is_numeric($in['experience'])) {
      $errors['experience'] = ["The experience must be a number."];
    }
    
    // Validate LinkedIn URL if provided
    if (!empty($in['linkedin_url']) && !filter_var($in['linkedin_url'], FILTER_VALIDATE_URL)) {
      $errors['linkedin_url'] = ["The linkedin_url must be a valid URL."];
    }
    
    // Validate GitHub URL if provided
    if (!empty($in['github_url']) && !filter_var($in['github_url'], FILTER_VALIDATE_URL)) {
      $errors['github_url'] = ["The github_url must be a valid URL."];
    }
    
    // Validate interests if provided
    if (!empty($in['interests'])) {
      if (!is_array($in['interests'])) {
        $errors['interests'] = ["The interests must be an array."];
      } else {
        foreach ($in['interests'] as $interest) {
          if (!is_string($interest) || trim($interest) === '') {
            $errors['interests'] = ["All interests must be non-empty strings."];
            break;
          }
        }
      }
    }
    
    // Return validation errors if any
    if (!empty($errors)) {
      return Response::json([
        'success' => false,
        'error' => 'Validation failed',
        'message' => 'The given data was invalid.',
        'errors' => $errors
      ], 422);
    }
    
    // Complete onboarding with data
    $success = $this->users->completeOnboardingWithData((int)$p['sub'], $in);
    
    if ($success) {
      // Create default notification preferences for the user
      $notificationPreferences = new \Models\NotificationPreferenceModel();
      $notificationPreferences->createDefaultPreferences((int)$p['sub']);
      
      // Handle device token if provided (user is now eligible since onboarding is completed)
      if (!empty($in['device_token'])) {
        $this->users->updateDeviceToken((int)$p['sub'], $in['device_token']);
      }
      
      // Get updated user data
      $user = $this->users->findById((int)$p['sub']);
      if ($user) {
        unset($user['password_hash']);
        $user['is_onboarding_done'] = (bool)$user['is_onboarding_done'];
        $user['admin_request'] = (bool)$user['admin_request'];
        
        return Response::json([
          'success' => true,
          'message' => 'Onboarding completed successfully',
          'user' => $user
        ]);
      }
    }
    
    return Response::json([
      'success' => false,
      'error' => 'Failed to complete onboarding'
    ], 500);
  }

  public function me(){
    $p = AuthCore::requireUser();
    $u = $this->users->findById((int)$p['sub']);
    if(!$u) return Response::json(['error'=>'User not found'],404);
    unset($u['password_hash']);
    $u['is_onboarding_done'] = (bool)$u['is_onboarding_done'];
    if (isset($u['admin_request'])) {
      $u['admin_request'] = (bool)$u['admin_request'];
    }
    return Response::json(['user'=>$u]);
  }

  public function resetOnboarding(){
    $p = AuthCore::requireUser();
    $success = $this->users->resetOnboarding((int)$p['sub']);
    
    if ($success) {
      return Response::json([
        'message' => 'Onboarding reset successfully',
        'is_onboarding_done' => false
      ]);
    } else {
      return Response::json(['error' => 'Failed to reset onboarding'], 500);
    }
  }

  // DELETE /user/self-delete - Hard delete user account
  public function selfDelete() {
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
    
    // Cannot self-delete if super_admin
    if ($user['role'] === 'super_admin') {
      return Response::json([
        'success' => false,
        'message' => 'Super admin account cannot be deleted'
      ], 403);
    }
    
    // Perform hard delete
    $success = $this->users->hardDelete($userId);
    
    if ($success) {
      return Response::json([
        'success' => true,
        'message' => 'Account permanently deleted'
      ]);
    }
    
    return Response::json([
        'success' => false,
        'message' => 'Failed to delete account'
    ], 500);
  }

  public function onboardingStatus(){
    $p = AuthCore::requireUser();
    $u = $this->users->findById((int)$p['sub']);
    
    if (!$u) {
      return Response::json(['error' => 'User not found'], 404);
    }
    
    return Response::json([
      'is_onboarding_done' => (bool)$u['is_onboarding_done'],
      'user_id' => $u['id'],
      'redirect_to' => (bool)$u['is_onboarding_done'] ? 'dashboard' : 'onboarding'
    ]);
  }
  
  // PUT /auth/device-token - Update user's device token for push notifications
  public function updateDeviceToken(){
    $p = AuthCore::requireUser();
    $in = Response::input();
    
    $deviceToken = $in['device_token'] ?? null;
    
    // Allow clearing device token by passing null or empty string
    if (empty($deviceToken)) {
      // Check if device token exists before clearing
      $user = $this->users->findById((int)$p['sub']);
      
      if (empty($user['device_token'])) {
        return Response::json([
          'success' => true,
          'message' => 'Device token is already cleared'
        ]);
      }
      
      $success = $this->users->clearDeviceToken((int)$p['sub']);
      
      if ($success) {
        return Response::json([
          'success' => true,
          'message' => 'Device token cleared successfully'
        ]);
      }
      
      return Response::json([
        'success' => false,
        'message' => 'Failed to clear device token'
      ], 500);
    }
    
    // Update device token
    $success = $this->users->updateDeviceToken((int)$p['sub'], $deviceToken);
    
    if ($success) {
      return Response::json([
        'success' => true,
        'message' => 'Device token updated successfully'
      ]);
    }
    
    return Response::json([
      'success' => false,
      'message' => 'Failed to update device token. Make sure you have completed onboarding.'
    ], 500);
  }
  
  // GET /auth/device-token - Get user's current device token status
  public function getDeviceTokenStatus(){
    $p = AuthCore::requireUser();
    $user = $this->users->findById((int)$p['sub']);
    
    if (!$user) {
      return Response::json(['error' => 'User not found'], 404);
    }
    
    return Response::json([
      'success' => true,
      'has_device_token' => !empty($user['device_token']),
      'device_token_set_at' => !empty($user['device_token']) ? $user['updated_at'] : null,
      'push_notifications_eligible' => $user['is_onboarding_done'] && !$user['is_deleted'] && !$user['is_blocked']
    ]);
  }
  
  /**
   * Check if the email is a designated test account
   */
  private function isTestAccount($email) {
    $testAccounts = [
      'tacrotechtest@gmail.com'
      // Add more test accounts here as needed
    ];
    return in_array(strtolower($email), $testAccounts);
  }

  /**
   * Check if email is from IIT domain
   */
  private function isIITDomain($email) {
    if ($this->isTestAccount($email)) {
      return true;
    }
    $domain = substr(strrchr($email, '@'), 1);
    // Only allow IITJ domain
    $allowedDomains = [
      'iitj.ac.in'
    ];
    return in_array(strtolower($domain), $allowedDomains);
  }
  
  /**
   * Check if email starts with G24
   */
  private function isG24Email($email) {
    if ($this->isTestAccount($email)) {
      return true;
    }
    $localPart = substr($email, 0, strpos($email, '@'));
    return strtoupper(substr($localPart, 0, 3)) === 'M25';
  }

  public function changePassword() {
    $p = AuthCore::requireUser();
    $userId = (int)$p['sub'];
    $in = Response::input();

    // Validate input
    if (empty($in['current_password']) || empty($in['new_password'])) {
      return Response::json([
        'error' => 'Current password and new password are required'
      ], 422);
    }

    // Validate new password requirements
    if (strlen($in['new_password']) < 8) {
      return Response::json([
        'error' => 'New password must be at least 8 characters long'
      ], 422);
    }

    // Get user's current password hash
    $user = $this->users->findByIdWithPassword($userId);
    if (!$user) {
      return Response::json(['error' => 'User not found'], 404);
    }

    // Verify current password
    if (!password_verify($in['current_password'], $user['password_hash'])) {
      return Response::json([
        'error' => 'Current password is incorrect'
      ], 401);
    }

    // Hash new password
    $newHash = password_hash($in['new_password'], PASSWORD_DEFAULT);

    // Update password in database
    if ($this->users->updatePassword($userId, $newHash)) {
      return Response::json([
        'success' => true,
        'message' => 'Password updated successfully'
      ]);
    }

    return Response::json([
      'error' => 'Failed to update password'
    ], 500);
  }

  // ========== Apple Sign-In Methods ==========

  /**
   * POST /api/auth/apple
   * Authenticates user with Apple Sign-In
   */
  public function appleAuth() {
    try {
      $in = Response::input();
      
      if (empty($in['apple_user_id'])) {
        throw new \Exception('apple_user_id is required');
      }

      // Handle name - could be string or array {firstName, lastName}
      $name = 'Apple User';
      if (!empty($in['name'])) {
        if (is_array($in['name'])) {
          $firstName = $in['name']['firstName'] ?? '';
          $lastName = $in['name']['lastName'] ?? '';
          $name = trim($firstName . ' ' . $lastName);
        } else {
          $name = trim($in['name']);
        }
      }

      $appleEmail = !empty($in['email']) && is_string($in['email']) ? trim($in['email']) : null;
      $emailVerified = $appleEmail && $this->isIITDomain($appleEmail);

      // Check if user exists with this Apple ID
      $appleUser = $this->users->findByAppleId($in['apple_user_id']);
      
      if ($appleUser) {
        return $this->handleExistingAppleUser($appleUser, $in);
      }

      // New user - check if can auto-register with IIT email
      if ($appleEmail && $emailVerified) {
        return $this->handleAutoRegistrationWithIITEmail($in, $name, $appleEmail);
      }

      // Email not provided or not IIT domain - create user and require verification
      return $this->createUnverifiedUserAndRequireVerification($in, $name, $appleEmail);

    } catch (\Exception $e) {
      error_log('Apple Auth Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
      return Response::json([
        'success' => false,
        'error' => $e->getMessage(),
        'message' => 'An error occurred during authentication'
      ], 400);
    }
  }

  /**
   * Handle existing Apple user login
   */
  private function handleExistingAppleUser($appleUser, $input) {
    // Check if blocked
    if ($appleUser['is_blocked']) {
      return Response::json([
        'success' => false,
        'error' => 'Account has been blocked by administrator',
        'blocked_at' => $appleUser['blocked_at'],
        'reason' => $appleUser['block_reason'] ?? 'No reason provided'
      ], 403);
    }

    // If deleted, restore
    if ($appleUser['is_deleted']) {
      $this->users->restoreUser((int)$appleUser['id']);
      $this->users->resetOnboarding((int)$appleUser['id']);
      $appleUser['is_onboarding_done'] = false;
      $appleUser['email_verified'] = false;
    }

    // Check email verification
    if (!$appleUser['email_verified']) {
      return $this->requireEmailVerification(
        $input['apple_user_id'],
        $appleUser['name'],
        $appleUser['email']
      );
    }

    // Login successful
    $token = AuthCore::issueToken((int)$appleUser['id'], $appleUser['role']);
    $this->users->saveToken((int)$appleUser['id'], $token);
    
    if (!empty($input['device_token'])) {
      $this->users->updateDeviceToken((int)$appleUser['id'], $input['device_token']);
    }

    $appleUser['auth_token'] = $token;
    unset($appleUser['password_hash']);
    $appleUser['is_onboarding_done'] = (bool)$appleUser['is_onboarding_done'];
    $appleUser['email_verified'] = (bool)$appleUser['email_verified'];

    return Response::json([
      'success' => true,
      'message' => 'Login successful',
      'user' => $appleUser
    ]);
  }

  /**
   * Handle auto-registration with IIT domain email
   */
  private function handleAutoRegistrationWithIITEmail($input, $name, $email) {
    // Validate G24 prefix
    if (!$this->isG24Email($email)) {
      throw new \Exception('This app is currently for MTech Jan-2025 Batch. We will launch the app for everyone soon.');
    }

    // Check if email already exists
    $existingEmailUser = $this->users->findByEmail($email);
    
    if ($existingEmailUser) {
      // If same Apple ID, treat as login
      if (!empty($existingEmailUser['apple_user_id']) && 
          $existingEmailUser['apple_user_id'] === $input['apple_user_id']) {
        return $this->handleExistingAppleUser($existingEmailUser, $input);
      }
      
      // Different Apple ID - conflict
      throw new \Exception('This email is already registered with another account. Please contact support.');
    }

    // Create new account
    $hash = password_hash(self::DEFAULT_PASSWORD, PASSWORD_DEFAULT);
    $userId = $this->users->createWithApple(
      $name, $email, $hash, $input['apple_user_id'], 'user', true
    );

    $token = AuthCore::issueToken($userId, 'user');
    $this->users->saveToken($userId, $token);

    if (!empty($input['device_token'])) {
      $this->users->updateDeviceToken($userId, $input['device_token']);
    }

    return Response::json([
      'success' => true,
      'message' => 'Account created successfully',
      'token' => $token,
      'is_first_time' => true,
      'user' => [
        'id' => $userId,
        'name' => $name,
        'email' => $email,
        'auth_token' => $token,
        'role' => 'user',
        'apple_user_id' => $input['apple_user_id'],
        'email_verified' => true,
        'is_onboarding_done' => false
      ]
    ]);
  }

  /**
   * Return response requiring email verification
   */
  private function requireEmailVerification($appleUserId, $name, $email) {
    return Response::json([
      'success' => true,
      'message' => 'Email verification required',
      'requires_email_verification' => true,
      'user' => [
        'apple_user_id' => $appleUserId,
        'name' => $name,
        'email' => $email
      ]
    ]);
  }

  /**
   * Create unverified user and require email verification
   */
  private function createUnverifiedUserAndRequireVerification($input, $name, $email) {
    // If no email provided, cannot create user yet - just require verification
    if (!$email) {
      return Response::json([
        'success' => true,
        'message' => 'Email verification required',
        'requires_email_verification' => true,
        'user' => [
          'apple_user_id' => $input['apple_user_id'],
          'name' => $name,
          'email' => null
        ]
      ]);
    }

    // Check if email already exists with different apple_user_id
    $existingEmailUser = $this->users->findByEmail($email);
    if ($existingEmailUser && 
        !empty($existingEmailUser['apple_user_id']) && 
        $existingEmailUser['apple_user_id'] !== $input['apple_user_id']) {
      throw new \Exception('This email is already registered with another account. Please contact support.');
    }

    // Create user with unverified email
    $hash = password_hash(self::DEFAULT_PASSWORD, PASSWORD_DEFAULT);
    $userId = $this->users->createWithApple(
      $name,
      $email,
      $hash,
      $input['apple_user_id'],
      'user',
      false  // email_verified = false
    );

    return Response::json([
      'success' => true,
      'message' => 'Email verification required',
      'requires_email_verification' => true,
      'user' => [
        'apple_user_id' => $input['apple_user_id'],
        'name' => $name,
        'email' => $email
      ]
    ]);
  }

  /**
   * POST /api/send-verification-otp
   * Sends OTP to email for verification
   */
  public function sendVerificationOTP() {
    try {
      $in = Response::input();

      // Validate email
      if (empty($in['email'])) {
        throw new \Exception('Email is required');
      }

      if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL)) {
        throw new \Exception('Invalid email format');
      }

      // Validate email domain
      if (!$this->isIITDomain($in['email'])) {
        return Response::json([
          'success' => false,
          'error' => 'Invalid email domain',
          'message' => 'Only @iitj.ac.in emails are allowed'
        ], 400);
      }

      // Validate G24 prefix
      if (!$this->isG24Email($in['email'])) {
        return Response::json([
          'success' => false,
          'error' => 'Invalid email prefix',
          'message' => 'This app is currently for MTech Jan-2025 Batch (G24). We will launch the app for everyone soon.'
        ], 400);
      }

      // Check rate limit
      if ($this->users->checkRateLimit($in['email'], 10, 1)) {
        return Response::json([
          'success' => false,
          'error' => 'Rate limit exceeded',
          'message' => 'Please wait 60 minutes before requesting another code'
        ], 429);
      }

      // Generate 6-digit OTP
      $otp = str_pad((string)random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

      // Store OTP in database
      if (!$this->users->storeOTP($in['email'], $otp, 10)) {
        throw new \Exception('Failed to store verification code');
      }

      // Send email
      $emailService = new \Services\EmailService();
      if ($emailService->sendOTP($in['email'], $otp)) {
        return Response::json([
          'success' => true,
          'message' => "Verification code sent to {$in['email']}",
          'expires_in' => 600 // 10 minutes in seconds
        ]);
      } else {
        throw new \Exception('Failed to send verification email');
      }

    } catch (\Exception $e) {
      error_log('Send OTP Error: ' . $e->getMessage());
      return Response::json([
        'success' => false,
        'error' => $e->getMessage(),
        'message' => 'An error occurred while sending verification code'
      ], 500);
    }
  }

  /**
   * POST /api/verify-email-apple-signin
   * Verifies OTP and completes Apple Sign-In registration
   */
  public function verifyEmailAppleSignin() {
    try {
      $in = Response::input();

      // Validate required fields
      $requiredFields = ['email', 'otp', 'apple_user_id', 'name'];
      foreach ($requiredFields as $field) {
        if (empty($in[$field])) {
          throw new \Exception("Missing required field: {$field}");
        }
      }

      // Validate email format
      if (!filter_var($in['email'], FILTER_VALIDATE_EMAIL)) {
        throw new \Exception('Invalid email format');
      }

      // Validate OTP format
      if (!preg_match('/^\d{6}$/', $in['otp'])) {
        throw new \Exception('OTP must be 6 digits');
      }

      // Verify OTP
      $otpResult = $this->users->verifyOTP($in['email'], $in['otp']);
      
      if (!$otpResult['valid']) {
        $response = [
          'success' => false,
          'error' => $otpResult['error'],
          'message' => $otpResult['error']
        ];
        
        if (isset($otpResult['attempts_remaining'])) {
          $response['attempts_remaining'] = $otpResult['attempts_remaining'];
        }

        $statusCode = 400;
        if ($otpResult['error'] === 'Too many failed attempts') {
          $statusCode = 429;
        }

        return Response::json($response, $statusCode);
      }

      // OTP verified successfully
      // First check if a user with this Apple ID already exists
      $existingAppleUser = $this->users->findByAppleId($in['apple_user_id']);
      
      if ($existingAppleUser) {
        // Update the existing Apple user's email and verify it
        $this->users->updateUserEmail((int)$existingAppleUser['id'], $in['email']);
        $this->users->updateEmailVerified((int)$existingAppleUser['id'], true);
        
        // Generate token
        $token = AuthCore::issueToken((int)$existingAppleUser['id'], $existingAppleUser['role']);
        $this->users->saveToken((int)$existingAppleUser['id'], $token);
        
        if (!empty($in['device_token'])) {
          $this->users->updateDeviceToken((int)$existingAppleUser['id'], $in['device_token']);
        }

        // Clear rate limit
        $this->users->clearRateLimit($in['email']);

        $existingAppleUser['email'] = $in['email'];
        $existingAppleUser['auth_token'] = $token;
        $existingAppleUser['email_verified'] = true;
        unset($existingAppleUser['password_hash']);
        $existingAppleUser['is_onboarding_done'] = (bool)$existingAppleUser['is_onboarding_done'];

        return Response::json([
          'success' => true,
          'message' => 'Email verified successfully',
          'user' => $existingAppleUser
        ]);
      }
      
      // Check if email already exists
      $existingUser = $this->users->findByEmail($in['email']);
      
      if ($existingUser) {
        // Check if email belongs to different apple_user_id
        if (!empty($existingUser['apple_user_id']) && $existingUser['apple_user_id'] !== $in['apple_user_id']) {
          return Response::json([
            'success' => false,
            'error' => 'Email already registered',
            'message' => 'This email is already associated with another Apple account'
          ], 409);
        }

        // Email exists with same Apple ID or no Apple ID - update and login
        if (empty($existingUser['apple_user_id'])) {
          $this->users->updateAppleId((int)$existingUser['id'], $in['apple_user_id']);
        }
        
        $this->users->updateEmailVerified((int)$existingUser['id'], true);
        
        // Generate token
        $token = AuthCore::issueToken((int)$existingUser['id'], $existingUser['role']);
        $this->users->saveToken((int)$existingUser['id'], $token);
        
        if (!empty($in['device_token'])) {
          $this->users->updateDeviceToken((int)$existingUser['id'], $in['device_token']);
        }

        // Clear rate limit
        $this->users->clearRateLimit($in['email']);

        $existingUser['auth_token'] = $token;
        $existingUser['email_verified'] = true;
        unset($existingUser['password_hash']);
        $existingUser['is_onboarding_done'] = (bool)$existingUser['is_onboarding_done'];

        return Response::json([
          'success' => true,
          'message' => 'Email verified successfully',
          'user' => $existingUser
        ]);
      }

      // New user - create account
      $hash = password_hash(self::DEFAULT_PASSWORD, PASSWORD_DEFAULT);
      
      $userId = $this->users->createWithApple(
        $in['name'],
        $in['email'],
        $hash,
        $in['apple_user_id'],
        'user',
        true // email_verified = true
      );

      // Generate token
      $token = AuthCore::issueToken($userId, 'user');
      $this->users->saveToken($userId, $token);

      if (!empty($in['device_token'])) {
        $this->users->updateDeviceToken($userId, $in['device_token']);
      }

      // Clear rate limit
      $this->users->clearRateLimit($in['email']);

      return Response::json([
        'success' => true,
        'message' => 'Email verified successfully',
        'user' => [
          'id' => $userId,
          'name' => $in['name'],
          'email' => $in['email'],
          'auth_token' => $token,
          'role' => 'user',
          'apple_user_id' => $in['apple_user_id'],
          'email_verified' => true,
          'is_onboarding_done' => false,
          'profile_photo' => null,
          'device_token' => $in['device_token'] ?? null
        ]
      ]);

    } catch (\Exception $e) {
      error_log('Verify Email Apple Signin Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
      return Response::json([
        'success' => false,
        'error' => $e->getMessage(),
        'message' => 'An error occurred during email verification'
      ], 500);
    }
  }
}

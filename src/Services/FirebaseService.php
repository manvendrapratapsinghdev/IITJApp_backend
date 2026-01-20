<?php
namespace Services;

/**
 * Firebase Cloud Messaging Service
 * 
 * This class is organized into 3 main sections:
 * 1. Core Firebase Configuration & Authentication
 * 2. Used Notification Methods (SECTION 2)
 * 3. Unused FCM Delivery Methods (SECTION 3)
 */
class FirebaseService {
    
    // ==========================================
    // SECTION 1: CORE FIREBASE CONFIGURATION
    // ==========================================
    
    private $serverKey;
    private $serviceAccount;
    private $accessToken;
    private $fcmUrl = 'https://fcm.googleapis.com/fcm/send';
    private $fcmV1Url;
    
    public function __construct() {
        $this->serverKey = $this->getServerKey();
        $this->serviceAccount = $this->getServiceAccount();
        
        if ($this->serviceAccount) {
            $this->fcmV1Url = 'https://fcm.googleapis.com/v1/projects/' . $this->serviceAccount['project_id'] . '/messages:send';
        }
    }
    
    // ==========================================
    // PRIVATE METHODS - CORE FIREBASE FUNCTIONALITY
    // ==========================================
    
    /**
     * Get Firebase service account configuration
     */
    private function getServiceAccount(): ?array {
        // Try environment variable first (for AWS deployment)
        $envServiceAccount = $_ENV['FIREBASE_SERVICE_ACCOUNT'] ?? getenv('FIREBASE_SERVICE_ACCOUNT');
        if ($envServiceAccount) {
            $serviceAccountData = json_decode($envServiceAccount, true);
            if ($serviceAccountData) {
                error_log("[DEBUG] Using Firebase service account from environment variable");
                return $serviceAccountData;
            }
        }
        
        // Try environment path variable
        $envPath = $_ENV['FIREBASE_SERVICE_ACCOUNT_PATH'] ?? getenv('FIREBASE_SERVICE_ACCOUNT_PATH');
        if ($envPath && file_exists($envPath)) {
            $serviceAccountJson = file_get_contents($envPath);
            $serviceAccountData = json_decode($serviceAccountJson, true);
            if ($serviceAccountData) {
                error_log("[DEBUG] Using Firebase service account from environment path: " . $envPath);
                return $serviceAccountData;
            }
        }
        
        // Fallback to config file
        $configPath = __DIR__ . '/../../config/firebase.php';
        if (file_exists($configPath)) {
            $config = require $configPath;
            if (!empty($config['service_account_path']) && file_exists($config['service_account_path'])) {
                $serviceAccountJson = file_get_contents($config['service_account_path']);
                $serviceAccountData = json_decode($serviceAccountJson, true);
                if ($serviceAccountData) {
                    error_log("[DEBUG] Using Firebase service account from config file");
                    return $serviceAccountData;
                }
            }
        }
        
        error_log("[DEBUG] No Firebase service account configuration found");
        return null;
    }
    
    /**
     * Get OAuth2 access token for service account authentication
     */
    private function getAccessToken(): ?string {
        if (!$this->serviceAccount) {
            return null;
        }
        
        // Create JWT for service account authentication
        $now = time();
        $expiry = $now + 3600; // 1 hour
        
        $header = json_encode(['alg' => 'RS256', 'typ' => 'JWT']);
        $payload = json_encode([
            'iss' => $this->serviceAccount['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => 'https://oauth2.googleapis.com/token',
            'iat' => $now,
            'exp' => $expiry
        ]);
        
        $base64Header = $this->base64UrlEncode($header);
        $base64Payload = $this->base64UrlEncode($payload);
        
        $signature = '';
        openssl_sign(
            $base64Header . '.' . $base64Payload,
            $signature,
            $this->serviceAccount['private_key'],
            'SHA256'
        );
        
        $base64Signature = $this->base64UrlEncode($signature);
        $jwt = $base64Header . '.' . $base64Payload . '.' . $base64Signature;
        
        // Exchange JWT for access token
        $tokenRequest = [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $jwt
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://oauth2.googleapis.com/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($tokenRequest));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . '/../../config/cacert.pem');
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            error_log("[Firebase] OAuth2 cURL error: " . $error);
            return null;
        }
        
        if ($httpCode !== 200) {
            error_log("[Firebase] OAuth2 HTTP error {$httpCode}: " . $response);
            return null;
        }
        
        $tokenData = json_decode($response, true);
        if (!isset($tokenData['access_token'])) {
            error_log("[Firebase] OAuth2 response missing access_token: " . $response);
            return null;
        }
        
        return $tokenData['access_token'];
    }
    
    /**
     * Base64 URL encoding helper
     */
    private function base64UrlEncode($data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
    
    /**
     * Get Firebase server key from configuration
     */
    private function getServerKey(): ?string {
        // Check if Firebase config file exists
        $configPath = __DIR__ . '/../../config/firebase.php';
        if (file_exists($configPath)) {
            $config = require $configPath;
            
            // Try legacy server key first
            if (!empty($config['server_key'])) {
                return $config['server_key'];
            }
            
            // Try VAPID key (for web push - can work for mobile too)
            if (!empty($config['vapid_key'])) {
                return $config['vapid_key'];
            }
            
            // For service account, OAuth2 is implemented - return null but log success
            if (!empty($config['service_account_path']) && file_exists($config['service_account_path'])) {
                return null; // This is correct - service account uses OAuth2, not server key
            }
        }
        
        // Fallback to environment variable (check both $_ENV and getenv)
        $envKey = $_ENV['FIREBASE_SERVER_KEY'] ?? getenv('FIREBASE_SERVER_KEY') ?? 
                  $_ENV['FIREBASE_VAPID_KEY'] ?? getenv('FIREBASE_VAPID_KEY') ?? null;
        if ($envKey) {
            error_log("[DEBUG] Using environment variable for Firebase server key");
            return $envKey;
        }
        
        return null;
    }
    
    // ==========================================
    // SECTION 2: USED NOTIFICATION METHODS
    // ==========================================
    // Status: ACTIVELY USED in production code and test scripts
    
    /**
     * Create a notification array for new post or announcement
     * STATUS: USED - Called by notifyNewPost() and test scripts
     */
    public function createPostNotification(array $post, array $poster): array {
        $isAnnouncement = isset($post['is_announcement']) && $post['is_announcement'];
        
        if ($isAnnouncement) {
            $title = "📢Announcement: " . $post['title'];
            if ($poster['role'] === 'admin' || $poster['role'] === 'super_admin') {
                $title = "🚨 Admin Announcement: " . $post['title'];
            } elseif ($poster['role'] === 'faculty') {
                $title = "📋 Faculty Announcement: " . $post['title'];
            }
        } else {
            $title = $post['title'];
            if ($poster['role'] === 'admin' || $poster['role'] === 'super_admin') {
                $title = "📢 Admin Post: " . $post['title'];
            } elseif ($poster['role'] === 'faculty') {
                $title = "🎓 Faculty Post: " . $post['title'];
            }
        }
        
        $body = "by " . $poster['name'];
        
        return [
            'title' => $title,
            'body' => $body
        ];
    }
    
    /**
     * Create a notification array for new notes
     * STATUS: USED - Called by notifyNewNotes() and test scripts
     */
    public function createNotesNotification(array $note, array $uploader, array $subject): array {
        $title = "📚 Notes: " . $note['title'];
        $body =  $subject['name'] . " -by " . $uploader['name'];
        
        return [
            'title' => $title,
            'body' => $body
        ];
    }
    

    /**
     * Create a notification array for general notifications
     * STATUS: USED - Called by test scripts only (notifyNewNotification not used in production)
     */
    public function createGeneralNotification(array $notification, array $creator): array {
        $title = "🔔 " . $notification['title'];
        $body = $notification['description'];
        
        if ($creator['role'] === 'admin' || $creator['role'] === 'super_admin') {
            $title = "📢 Admin: " . $notification['title'];
        } elseif ($creator['role'] === 'faculty') {
            $title = "🎓 Faculty: " . $notification['title'];
        }
        
        return [
            'title' => $title,
            'body' => $body
        ];
    }
    
    /**
     * Send notification about new post to all users
     * STATUS: USED - Called by PostModel.php
     */
    public function notifyNewPost(array $post, array $poster): array {
        // Get device tokens based on notification preferences
        $notificationType = isset($post['is_announcement']) && $post['is_announcement'] ? 'announcement' : 'post';
        $deviceTokens = $this->getEligibleDeviceTokens([], $notificationType);
        
        // Remove the poster's device token to avoid self-notification
        $userModel = new \Models\UserModel();
        $posterData = $userModel->findById($poster['user_id']);
        if ($posterData && $posterData['device_token']) {
            $deviceTokens = array_filter($deviceTokens, function($token) use ($posterData) {
                return $token !== $posterData['device_token'];
            });
        }
        
        if (empty($deviceTokens)) {
            return ['success' => true, 'message' => 'No device tokens to send to'];
        }
        
        // Create notification (handles both posts and announcements)
        $notification = $this->createPostNotification($post, $poster);
        
        // Set notification type based on is_announcement flag
        $isAnnouncement = isset($post['is_announcement']) && $post['is_announcement'];
        $notificationType = $isAnnouncement ? 'new_announcement' : 'new_post';
        $data = $this->createDataPayload($notificationType, ['post_id' => $post['post_id']]);
        
        return $this->sendToDevices($deviceTokens, $notification, $data);
    }
    
    /**
     * Send notification about new notes to all users
     * STATUS: USED - Called by SubjectModel.php
     */
    public function notifyNewNotes(array $note, array $uploader, array $subject): array {
        $deviceTokens = $this->getEligibleDeviceTokens([], 'notes');
        
        // Remove the uploader's device token to avoid self-notification
        $userModel = new \Models\UserModel();
        $uploaderData = $userModel->findById($uploader['user_id']);
        if ($uploaderData && $uploaderData['device_token']) {
            $deviceTokens = array_filter($deviceTokens, function($token) use ($uploaderData) {
                return $token !== $uploaderData['device_token'];
            });
        }
        
        if (empty($deviceTokens)) {
            return ['success' => true, 'message' => 'No device tokens to send to'];
        }
        
        $notification = $this->createNotesNotification($note, $uploader, $subject);
        $data = $this->createDataPayload('new_notes', [
            'note_id' => $note['id'],
            'subject_id' => $subject['id']
        ]);
        
        return $this->sendToDevices($deviceTokens, $notification, $data);
    }
    
   
    
    // ==========================================
    // SECTION 3: UNUSED FCM DELIVERY METHODS
    // ==========================================
    // Note: These methods exist but are not currently used in production
     /**
     * Send notification about new general notification to all users
     * STATUS: UNUSED - Method exists but not called in production code
     */
    public function notifyNewNotification(array $notificationPost, array $creator): array {
        $deviceTokens = $this->getEligibleDeviceTokens();
        
        // Remove the creator's device token to avoid self-notification
        $userModel = new \Models\UserModel();
        $creatorData = $userModel->findById($creator['user_id']);
        if ($creatorData && $creatorData['device_token']) {
            $deviceTokens = array_filter($deviceTokens, function($token) use ($creatorData) {
                return $token !== $creatorData['device_token'];
            });
        }
        
        if (empty($deviceTokens)) {
            return ['success' => true, 'message' => 'No device tokens to send to'];
        }
        
        $notification = $this->createGeneralNotification($notificationPost, $creator);
        $data = $this->createDataPayload('new_notification', [
            'notification_id' => $notificationPost['post_id']
        ]);
        
        return $this->sendToDevices($deviceTokens, $notification, $data);
    }
    /**
     * Send a push notification to a single device
     * STATUS: UNUSED - Available for future use
     */
    public function sendToDevice(string $deviceToken, array $notification, array $data = []): array {
        // Remove the early check for serverKey - let sendRequest handle authentication
        $payload = [
            'to' => $deviceToken,
            'notification' => $notification,
            'data' => $data,
            'priority' => 'high',
            'content_available' => true
        ];
        
        return $this->sendRequest($payload);
    }
    
    /**
     * Send a push notification to multiple devices with robust error handling
     * Implements industry standards for handling invalid/stale device tokens
     */
    public function sendToDevices(array $deviceTokens, array $notification, array $data = []): array {
        // Check if we have either server key OR service account configured
        if (empty($this->serverKey) && !$this->serviceAccount) {
            error_log("[DEBUG] No Firebase authentication method configured");
            return [
                'success' => false,
                'error' => 'No Firebase authentication method configured'
            ];
        }
        
        // STRATEGY 1: Pre-filter obviously invalid tokens
        $validTokens = $this->preValidateTokens($deviceTokens);
        
        if (empty($validTokens)) {
            return [
                'success' => false,
                'error' => 'No valid device tokens found',
                'original_count' => count($deviceTokens),
                'valid_count' => 0
            ];
        }
        
        // STRATEGY 2: Use smaller chunks to isolate failures (industry best practice)
        $tokenChunks = array_chunk($validTokens, 10); 
        $allChunkResults = [];
        $totalSuccessful = 0;
        $totalFailed = 0;
        $invalidTokens = [];

        foreach ($tokenChunks as $chunkIndex => $chunk) {
            try {
                // STRATEGY 3: Individual token sending for better error isolation
                $chunkResults = $this->sendChunkWithIndividualFallback($chunk, $notification, $data);
            } catch (\Throwable $e) {
                // Ensure failures don't break the loop
                $chunkResults = [
                    'chunk_index' => $chunkIndex,
                    'successful_sends' => 0,
                    'failed_sends' => count($chunk),
                    'invalid_tokens' => [],
                    'error' => $e->getMessage()
                ];
                // log error here if needed
            }

            $allChunkResults[] = $chunkResults;
            $totalSuccessful += $chunkResults['successful_sends'] ?? 0;
            $totalFailed += $chunkResults['failed_sends'] ?? 0;

            if (!empty($chunkResults['invalid_tokens'])) {
                $invalidTokens[] = $chunkResults['invalid_tokens']; // collect for one-time merge
            }
        }

        // STRATEGY 4: Automatic cleanup of invalid tokens
        if (!empty($invalidTokens)) {
            $invalidTokens = array_merge(...$invalidTokens);
            $this->cleanInvalidTokensFromDatabase($invalidTokens);
        }
        
        return [
            'success' => true,
            'results' => $allChunkResults,
            'total_chunks' => count($tokenChunks),
            'total_sent' => $totalSuccessful,
            'total_failed' => $totalFailed,
            'original_tokens' => count($deviceTokens),
            'valid_tokens' => count($validTokens),
            'cleaned_tokens' => count($invalidTokens)
        ];
    }
    
    /**
     * Pre-validate device tokens to filter out obviously invalid ones
     * STRATEGY 1: Remove invalid tokens before sending
     */
    private function preValidateTokens(array $deviceTokens): array {
        $validTokens = [];
        $removedCount = 0;
        
        foreach ($deviceTokens as $token) {
            $token = trim($token);
            
            // Check for empty or too short tokens
            if (empty($token) || strlen($token) < 20) {
                $removedCount++;
                continue;
            }
            
            // Check for obviously invalid tokens (contain only spaces, special chars, etc.)
            if (!preg_match('/^[a-zA-Z0-9_-]+[:]?[a-zA-Z0-9_-]+$/', $token)) {
                $removedCount++;
                continue;
            }
            
            $validTokens[] = $token;
        }
        
        return $validTokens;
    }
    
    /**
     * Send chunk - simplified without individual fallback
     * STRATEGY: Small batches only, no individual retries to prevent duplicates
     */
    private function sendChunkWithIndividualFallback(array $chunk, array $notification, array $data): array {
        // Send batch only - no individual fallback
        $payload = [
            'registration_ids' => $chunk,
            'notification' => $notification,
            'data' => $data,
            'priority' => 'high',
            'content_available' => true
        ];
        
        $batchResult = $this->sendRequest($payload);
        
        // If batch succeeded and we have detailed results, process them
        if ($batchResult['success'] && isset($batchResult['response']['results'])) {
            return $this->processBatchResults($chunk, $batchResult['response']['results']);
        }
        
        // If batch failed completely, just mark all as failed (no retry)
        return [
            'successful_sends' => 0,
            'failed_sends' => count($chunk),
            'invalid_tokens' => [] // Don't mark for cleanup if it was a network/server issue
        ];
    }
    
    /**
     * Process batch results to identify successful/failed tokens
     */
    private function processBatchResults(array $tokens, array $results): array {
        $successful = 0;
        $failed = 0;
        $invalidTokens = [];
        
        foreach ($results as $index => $result) {
            if (isset($result['message_id'])) {
                $successful++;
            } elseif (isset($result['error'])) {
                $failed++;
                $error = $result['error'];
                $token = $tokens[$index] ?? 'unknown';
                
                // Mark tokens for cleanup based on error type
                if (in_array($error, ['NotRegistered', 'InvalidRegistration', 'MismatchSenderId', 'InvalidToken'])) {
                    $invalidTokens[] = $token;
                }
            }
        }
        
        return [
            'successful_sends' => $successful,
            'failed_sends' => $failed,
            'invalid_tokens' => $invalidTokens
        ];
    }
    
    /**
     * Clean invalid tokens from database
     * STRATEGY 4: Automatic cleanup to prevent future issues
     */
    private function cleanInvalidTokensFromDatabase(array $invalidTokens): void {
        if (empty($invalidTokens)) {
            return;
        }
        
        try {
            $userModel = new \Models\UserModel();
            $cleanedCount = 0;
            
            foreach ($invalidTokens as $token) {
                if ($userModel->clearDeviceTokenByToken($token)) {
                    $cleanedCount++;
                }
            }
            
        } catch (\Exception $e) {
            error_log("🔥 [Firebase] ❌ Error cleaning tokens from database: " . $e->getMessage());
        }
    }
    
    /**
     * Send notification to topic subscribers
     * STATUS: UNUSED - Available for future use
     */
    public function sendToTopic(string $topic, array $notification, array $data = []): array {
        if (empty($this->serverKey)) {
            return [
                'success' => false,
                'error' => 'Firebase server key not configured'
            ];
        }
        
        $payload = [
            'to' => '/topics/' . $topic,
            'notification' => $notification,
            'data' => $data,
            'priority' => 'high',
            'content_available' => true
        ];
        
        return $this->sendRequest($payload);
    }
    
    /**
     * Create data payload for the notification
     * STATUS: UNUSED - Available for future use
     */
    public function createDataPayload(string $type, array $data): array {
        $payload = [
            'notification_type' => $type,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
        ];
        
        switch ($type) {
            case 'new_post':
                $payload['post_id'] = (string)$data['post_id'];
                $payload['screen'] = 'post_detail';
                break;
                
            case 'new_announcement':
                $payload['post_id'] = (string)$data['post_id'];
                $payload['screen'] = 'post_detail';
                $payload['is_announcement'] = 'true';
                break;
                
            case 'new_notes':
                $payload['note_id'] = (string)$data['note_id'];
                $payload['subject_id'] = (string)$data['subject_id'];
                $payload['screen'] = 'notes';
                break;
                
            case 'new_notification':
                $payload['notification_id'] = (string)$data['notification_id'];
                $payload['screen'] = 'notifications';
                break;
                
            case 'test':
                $payload['test_id'] = (string)$data['test_id'];
                $payload['screen'] = 'home';
                break;
                
            default:
                $payload['screen'] = 'home';
        }
        
        // Ensure all values are strings for FCM v1 API compatibility
        foreach ($payload as $key => $value) {
            $payload[$key] = (string)$value;
        }
        
        return $payload;
    }
    
    /**
     * Get all eligible device tokens for notification broadcast
     * STATUS: UNUSED - Available for future use
     */
    /**
     * Get eligible device tokens based on notification type and user preferences
     * @param array $userIds Optional specific users to target
     * @param string $notificationType Type of notification ('post', 'notes', 'announcement', etc.)
     * @return array Array of eligible device tokens
     */
    public function getEligibleDeviceTokens(array $userIds = [], string $notificationType = ''): array {
        // If no specific notification type, return all active device tokens
        if (empty($notificationType)) {
            $userModel = new \Models\UserModel();
            $users = $userModel->getUsersWithDeviceTokens($userIds);
        } else {
            // Get users who have enabled this notification type
            $prefModel = new \Models\NotificationPreferenceModel();
            $eligibleUserIds = $prefModel->getEligibleUserIds($notificationType);
            
            // If specific users were requested, intersect with eligible users
            if (!empty($userIds)) {
                $eligibleUserIds = array_intersect($userIds, $eligibleUserIds);
            }
            
            // No eligible users found
            if (empty($eligibleUserIds)) {
                return [];
            }
            
            // Get device tokens for eligible users
            $userModel = new \Models\UserModel();
            $users = $userModel->getUsersWithDeviceTokens($eligibleUserIds);
        }
        
        $deviceTokens = array_column($users, 'device_token');
        
        // Remove duplicate tokens to prevent duplicate notifications
        $uniqueTokens = array_unique($deviceTokens);
        
        // Remove empty tokens
        $validTokens = array_filter($uniqueTokens, function($token) {
            return !empty(trim($token));
        });
        
        // Reset array indices after filtering
        return array_values($validTokens);
    }
    
    /**
     * Clear device token for a specific user (useful for logout/deregistration)
     * STATUS: NEW - Added for device token management
     */
    public function clearUserDeviceToken(int $userId): bool {
        $userModel = new \Models\UserModel();
        return $userModel->clearDeviceToken($userId);
    }
    
    /**
     * Debug Firebase configuration - useful for troubleshooting AWS deployment
     */
    public function debugConfiguration(): array {
        $debug = [
            'server_key' => $this->serverKey ? 'Present' : 'NULL',
            'service_account' => $this->serviceAccount ? 'Present' : 'NULL',
            'fcm_v1_url' => $this->fcmV1Url ?? 'NULL',
            'environment_vars' => [
                'FIREBASE_SERVER_KEY' => !empty($_ENV['FIREBASE_SERVER_KEY']) || !empty(getenv('FIREBASE_SERVER_KEY')) ? 'Set' : 'Not set',
                'FIREBASE_SERVICE_ACCOUNT' => !empty($_ENV['FIREBASE_SERVICE_ACCOUNT']) || !empty(getenv('FIREBASE_SERVICE_ACCOUNT')) ? 'Set' : 'Not set',
                'FIREBASE_SERVICE_ACCOUNT_PATH' => !empty($_ENV['FIREBASE_SERVICE_ACCOUNT_PATH']) || !empty(getenv('FIREBASE_SERVICE_ACCOUNT_PATH')) ? 'Set' : 'Not set',
            ],
            'file_exists' => [
                'firebase_config' => file_exists(__DIR__ . '/../../config/firebase.php'),
                'service_account_file' => file_exists(__DIR__ . '/../../config/firebase-service-account.json'),
            ],
            'php_extensions' => [
                'openssl' => extension_loaded('openssl'),
                'curl' => extension_loaded('curl'),
                'json' => extension_loaded('json'),
            ]
        ];
        
        if ($this->serviceAccount) {
            $debug['service_account_details'] = [
                'project_id' => $this->serviceAccount['project_id'] ?? 'Missing',
                'client_email' => $this->serviceAccount['client_email'] ?? 'Missing',
                'private_key' => isset($this->serviceAccount['private_key']) ? 'Present' : 'Missing',
            ];
        }
        
        return $debug;
    }
    
    /**
     * Validate if a device token is still active by sending a test notification
     * STATUS: UNUSED - Available for future use (token validation)
     */
    public function validateDeviceToken(string $deviceToken): array {
        $testPayload = [
            'message' => [
                'token' => $deviceToken,
                'data' => [
                    'type' => 'token_validation',
                    'message' => 'Token validation check'
                ]
            ],
            'validate_only' => true // Use v1 dry run
        ];
        
        // This now directly calls the v1 request method
        $result = $this->sendV1Request($testPayload);
        
        return [
            'is_valid' => $result['success'] ?? false,
            'details' => $result
        ];
    }
    
    // ==========================================
    // PRIVATE HTTP REQUEST METHODS
    // ==========================================
    
    /**
     * Send the actual HTTP request to FCM
     */
    private function sendRequest(array $payload): array {
        // Try modern service account method first
        if ($this->serviceAccount && $this->fcmV1Url) {
            return $this->sendV1Request($payload);
        }
        
        // Fallback to legacy server key method
        if (empty($this->serverKey)) {
            return [
                'success' => false,
                'error' => 'No Firebase authentication method configured'
            ];
        }
        
        return $this->sendLegacyRequest($payload);
    }
    
    /**
     * Send request using FCM v1 API with service account
     */
    private function sendV1Request(array $payload): array {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return [
                'success' => false,
                'error' => 'Failed to get OAuth2 access token'
            ];
        }
        
        // The payload is now expected to be in v1 format already for dry runs
        $v1Payload = $payload;
        if (!isset($v1Payload['validate_only'])) {
            // Convert legacy payload to v1 format if not a dry run
            $v1Payload = $this->convertToV1Format($payload);
        }
        
        $headers = [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->fcmV1Url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . '/../../config/cacert.pem');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($v1Payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'error' => 'cURL error: ' . $error
            ];
        }
        
        $responseData = json_decode($response, true);
        
        if ($httpCode === 200 && isset($responseData['name'])) {
            return [
                'success' => true,
                'response' => $responseData,
                'successful_sends' => 1,
                'failed_sends' => 0
            ];
        }
        
        return [
            'success' => false,
            'error' => 'FCM v1 request failed',
            'http_code' => $httpCode,
            'response' => $responseData
        ];
    }
    
    /**
     * Send request using legacy FCM API
     */
    private function sendLegacyRequest(array $payload): array {
        $headers = [
            'Authorization: key=' . $this->serverKey,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->fcmUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . '/../../config/cacert.pem');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            return [
                'success' => false,
                'error' => 'cURL error: ' . $error
            ];
        }
        
        $responseData = json_decode($response, true);
        
        if ($httpCode === 200 && isset($responseData['success'])) {
            return [
                'success' => true,
                'response' => $responseData,
                'successful_sends' => $responseData['success'] ?? 0,
                'failed_sends' => $responseData['failure'] ?? 0
            ];
        }
        
        return [
            'success' => false,
            'error' => 'FCM request failed',
            'http_code' => $httpCode,
            'response' => $responseData
        ];
    }
    
    /**
     * Convert legacy FCM payload to v1 format
     */
    private function convertToV1Format(array $legacyPayload): array {
        $v1Message = [
            'message' => [
                'notification' => $legacyPayload['notification'] ?? null,
                'data' => $legacyPayload['data'] ?? null,
                'android' => [
                    'priority' => 'high',
                    'notification' => [
                        'icon' => 'ic_notification',
                        'sound' => 'default',
                        'click_action' => 'FLUTTER_NOTIFICATION_CLICK'
                    ]
                ]
            ]
        ];
        
        // Handle single token
        if (isset($legacyPayload['to'])) {
            if (strpos($legacyPayload['to'], '/topics/') === 0) {
                $v1Message['message']['topic'] = substr($legacyPayload['to'], 8);
            } else {
                $v1Message['message']['token'] = $legacyPayload['to'];
            }
        }
        
        // Handle multiple tokens (not supported in v1, would need multiple requests)
        if (isset($legacyPayload['registration_ids'])) {
            // For now, just use the first token
            $v1Message['message']['token'] = $legacyPayload['registration_ids'][0];
        }
        
        return $v1Message;
    }
}
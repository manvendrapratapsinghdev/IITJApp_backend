<?php
require_once __DIR__ . '/../src/Core/Autoload.php';

use Models\UserModel;
use Models\PostModel;
use Services\FirebaseService;

class PostNotificationTester {
    private $userModel;
    private $postModel;
    private $firebaseService;

    public function __construct() {
        $this->userModel = new UserModel();
        $this->postModel = new PostModel();
        $this->firebaseService = new FirebaseService();
    }

    /**
     * Create a test post and send notification
     */
    public function createPostAndNotify() {
        // First, let's get a list of users with device tokens
        $usersWithTokens = $this->userModel->getUsersWithDeviceTokens();
        
        if (empty($usersWithTokens)) {
            die("❌ No users with device tokens found. Please run insert_dummy_device_tokens.php first.\n");
        }

        echo "Found " . count($usersWithTokens) . " users with device tokens\n";

        // Select a random user as the poster
        $poster = $usersWithTokens[array_rand($usersWithTokens)];
        
        echo "\n🧑‍💻 Selected poster: {$poster['name']} (ID: {$poster['id']})\n";

        // Create a test post
        $post = [
            'title' => '🧪 Test Post ' . date('Y-m-d H:i:s'),
            'content' => 'This is a test post created by the notification testing script.',
            'user_id' => $poster['id'],
            'is_announcement' => rand(0, 1), // Randomly make it an announcement
            'post_id' => null // Will be set after creation
        ];

        try {
            // Create the post
            $postId = $this->postModel->create(
                $post['user_id'],
                [
                    'title' => $post['title'],
                    'description' => $post['content'],
                    'link' => null,
                    'is_announcement' => $post['is_announcement']
                ]
            );

            if (!$postId) {
                throw new Exception("Failed to create post");
            }

            $post['post_id'] = $postId;
            echo "\n📝 Created " . ($post['is_announcement'] ? "announcement" : "post") . " with ID: $postId\n";

            // Get poster details for notification
            $posterDetails = [
                'user_id' => $poster['id'],
                'name' => $poster['name'],
                'role' => 'faculty' // Setting as faculty to test different notification formats
            ];

            echo "\n📤 Sending notification...\n";
            
            // Print current state of device tokens
            echo "\nBefore notification - Device tokens in DB:\n";
            $this->printDeviceTokens();

            // Send notification
            $result = $this->firebaseService->notifyNewPost($post, $posterDetails);

            echo "\nAfter notification - Device tokens in DB:\n";
            $this->printDeviceTokens();

            echo "\n📊 Notification Result:\n";
            echo "Success: " . ($result['success'] ? 'Yes' : 'No') . "\n";
            echo "Message: " . ($result['message'] ?? 'Not provided') . "\n";
            
            if (isset($result['results'])) {
                echo "\nDetailed Results:\n";
                print_r($result['results']);
            }

            if (isset($result['total_sent'])) {
                echo "\nTotal sent: " . $result['total_sent'] . "\n";
                echo "Total failed: " . $result['total_failed'] . "\n";
                echo "Cleaned tokens: " . $result['cleaned_tokens'] . "\n";
            }

        } catch (Exception $e) {
            echo "\n❌ Error: " . $e->getMessage() . "\n";
            exit(1);
        }
    }

    /**
     * Helper function to print current device tokens
     */
    private function printDeviceTokens() {
        $users = $this->userModel->getUsersWithDeviceTokens();
        echo "Total users with tokens: " . count($users) . "\n";
        foreach ($users as $user) {
            $token = $user['device_token'];
            echo "- User {$user['id']} ({$user['name']}): " . substr($token, 0, 20) . "...\n";
        }
    }
}

// Execute the script
echo "=== Post Notification Test ===\n";
$tester = new PostNotificationTester();
$tester->createPostAndNotify();
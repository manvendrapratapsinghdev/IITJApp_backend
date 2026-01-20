<?php

namespace App\Services;

use App\Core\Database;
use App\Services\FirebaseService;

class NotificationService {
    private $db;
    private $firebase;

    public function __construct() {
        $this->db = new Database();
        $this->firebase = new FirebaseService();
    }

    /**
     * Send notification to users based on their preferences
     * 
     * @param string $type Notification type (post, notes, announcement, connection, schedule)
     * @param array $data Notification data
     * @param int|null $excludeUserId User ID to exclude from notification
     * @return array Result of sending notification
     */
    public function sendNotification($type, $data, $excludeUserId = null) {
        // Validate notification type
        $validTypes = ['post', 'notes', 'announcement', 'connection', 'schedule'];
        if (!in_array($type, $validTypes)) {
            throw new \InvalidArgumentException("Invalid notification type: $type");
        }

        // Build query to get eligible device tokens
        $query = "
            SELECT u.device_token 
            FROM users u
            INNER JOIN user_notification_preferences np ON u.id = np.user_id
            WHERE u.device_token IS NOT NULL 
            AND np.master_notifications = 1
        ";

        // Add type-specific preference check
        $typeColumn = "{$type}_notifications";
        $query .= " AND np.$typeColumn = 1";

        // Exclude specific user if requested
        $params = [];
        if ($excludeUserId) {
            $query .= " AND u.id != ?";
            $params[] = $excludeUserId;
        }

        // Get device tokens
        $result = $this->db->query($query, $params);
        if (!$result) {
            return ['success' => false, 'message' => 'No eligible recipients found'];
        }

        // Extract device tokens
        $deviceTokens = array_column($result, 'device_token');
        if (empty($deviceTokens)) {
            return ['success' => true, 'message' => 'No device tokens found'];
        }

        // Send notification using existing Firebase service
        return $this->firebase->sendNotification($deviceTokens, $data);
    }

    /**
     * Get count of eligible recipients for a notification type
     * Useful for checking impact before sending
     */
    public function getEligibleRecipientsCount($type, $excludeUserId = null) {
        $query = "
            SELECT COUNT(*) as count
            FROM users u
            INNER JOIN user_notification_preferences np ON u.id = np.user_id
            WHERE u.device_token IS NOT NULL 
            AND np.master_notifications = 1
            AND np.{$type}_notifications = 1
        ";

        $params = [];
        if ($excludeUserId) {
            $query .= " AND u.id != ?";
            $params[] = $excludeUserId;
        }

        $result = $this->db->query($query, $params);
        return $result ? $result[0]['count'] : 0;
    }
}
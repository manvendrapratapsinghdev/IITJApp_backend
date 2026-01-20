<?php

namespace Models;

use Core\Database;
use PDO;

class NotificationPreferenceModel {
    private $db;

    public function __construct() {
        $this->db = Database::pdo();
    }

    /**
     * Get user's notification preferences
     */
    public function getUserPreferences($userId) {
        $query = "SELECT 
            master_notifications,
            post_notifications,
            notes_notifications,
            announcement_notifications,
            connection_notifications,
            schedule_notifications
        FROM user_notification_preferences
        WHERE user_id = ?";

        $st = $this->db->prepare($query);
        $st->execute([$userId]);
        return $st->fetch() ?: null;
    }

    /**
     * Update user's notification preferences
     */
    public function updatePreferences($userId, $preferences) {
        // Start transaction for atomic update
        $this->db->beginTransaction();

        try {
            $query = "UPDATE user_notification_preferences SET ";
            $params = [];
            $updates = [];

            // Build dynamic update query based on provided preferences
            // Convert boolean values to integers (1 for true, 0 for false)
            $fields = [
                'master_notifications',
                'post_notifications',
                'notes_notifications',
                'announcement_notifications',
                'connection_notifications',
                'schedule_notifications'
            ];

            foreach ($fields as $field) {
                if (isset($preferences[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $preferences[$field] ? 1 : 0;
                }
            }

            if (empty($updates)) {
                return false;
            }

            $query .= implode(", ", $updates);
            $query .= " WHERE user_id = ?";
            $params[] = $userId;

            $st = $this->db->prepare($query);
            $success = $st->execute($params);
            
            if ($success) {
                $this->db->commit();
                return true;
            }

            $this->db->rollback();
            return false;
        } catch (\Exception $e) {
            $this->db->rollback();
            throw $e;
        }
    }

    /**
     * Create default preferences for new user
     */
    public function createDefaultPreferences($userId) {
        $query = "INSERT INTO user_notification_preferences 
            (user_id, master_notifications, post_notifications, notes_notifications, 
             announcement_notifications, connection_notifications, schedule_notifications)
            VALUES (?, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE)";

        $st = $this->db->prepare($query);
        return $st->execute([$userId]);
    }

    /**
     * Check if user has specific notification type enabled
     */
    public function isNotificationEnabled($userId, $type) {
        $query = "SELECT 
            master_notifications,
            CASE ? 
                WHEN 'post' THEN post_notifications
                WHEN 'notes' THEN notes_notifications
                WHEN 'announcement' THEN announcement_notifications
                WHEN 'connection' THEN connection_notifications
                WHEN 'schedule' THEN schedule_notifications
            END as type_enabled
        FROM user_notification_preferences
        WHERE user_id = ?";

        $st = $this->db->prepare($query);
        $st->execute([$type, $userId]);
        $result = $st->fetch();
        
        if (!$result) {
            return false;
        }

        // Both master switch and specific type must be enabled
        return $result['master_notifications'] && $result['type_enabled'];
    }

    /**
     * Get user IDs who have enabled specific type of notifications
     */
    public function getEligibleUserIds(string $type): array {
        $query = "SELECT user_id FROM user_notification_preferences 
                 WHERE master_notifications = 1 AND 
                 CASE ? 
                    WHEN 'post' THEN post_notifications
                    WHEN 'notes' THEN notes_notifications
                    WHEN 'announcement' THEN announcement_notifications
                    WHEN 'connection' THEN connection_notifications
                    WHEN 'schedule' THEN schedule_notifications
                 END = 1";

        $st = $this->db->prepare($query);
        $st->execute([$type]);
        return array_column($st->fetchAll(), 'user_id');
    }
}
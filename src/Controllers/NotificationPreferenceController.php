<?php

namespace Controllers;

use Core\Response;
use Core\Auth as AuthCore;
use Models\NotificationPreferenceModel;

class NotificationPreferenceController {
    private $model;

    public function __construct() {
        $this->model = new NotificationPreferenceModel();
    }

    /**
     * Get user's notification preferences
     */
    public function getPreferences() {
        try {
            // Get authenticated user
            $payload = AuthCore::requireUser();
            $userId = (int)$payload['sub'];

            // Get preferences
            $preferences = $this->model->getUserPreferences($userId);
            if (!$preferences) {
                // Create default preferences if none exist
                $this->model->createDefaultPreferences($userId);
                $preferences = $this->model->getUserPreferences($userId);
            }

            return Response::success($preferences);
        } catch (\Exception $e) {
            return Response::error('Failed to get notification preferences: ' . $e->getMessage());
        }
    }

    /**
     * Update user's notification preferences
     */
    public function updatePreferences() {
        try {
            // Get authenticated user
            $payload = AuthCore::requireUser();
            $userId = (int)$payload['sub'];

            // Get request body
            $body = file_get_contents('php://input');
            $data = json_decode($body, true);

            if (!is_array($data)) {
                return Response::error('Invalid request body', 400);
            }

            // Validate fields
            $validFields = [
                'master_notifications',
                'post_notifications',
                'notes_notifications',
                'announcement_notifications',
                'connection_notifications',
                'schedule_notifications'
            ];

            $preferences = [];
            foreach ($data as $key => $value) {
                if (!in_array($key, $validFields)) {
                    return Response::error("Invalid field: $key", 400);
                }
                if (!is_bool($value)) {
                    return Response::error("Field $key must be boolean", 400);
                }
                $preferences[$key] = $value;
            }

            // If master_notifications is false, force all other notifications to false
            if (isset($preferences['master_notifications']) && !$preferences['master_notifications']) {
                $preferences = array_fill_keys($validFields, false);
            }

            // Update preferences
            $success = $this->model->updatePreferences($userId, $preferences);
            if (!$success) {
                return Response::error('Failed to update preferences');
            }

            // Get updated preferences
            $updatedPreferences = $this->model->getUserPreferences($userId);
            return Response::success($updatedPreferences);
        } catch (\Exception $e) {
            return Response::error('Failed to update notification preferences: ' . $e->getMessage());
        }
    }
}
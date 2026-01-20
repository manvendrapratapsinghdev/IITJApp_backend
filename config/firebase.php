<?php
/**
 * Firebase Configuration
 *
 * To set up Firebase Cloud Messaging:
 * 1. Go to Firebase Console (https://console.firebase.google.com/)
 * 2. Select your project or create a new one
 * 3. Go to Project Settings > Cloud Messaging
 * 4. Copy your Server Key and place it below
 *
 * For production, consider using Firebase Admin SDK with service account
 * instead of the legacy server key approach.
 */

return [
    // Project information from google-services.json and firebase-service-account.json
    'project_id' => 'ai-gyan-connect',
    'project_number' => '693857525815',

    // Client API Key (from google-services.json)
    'client_api_key' => 'AIzaSyBvEOtY0BE41DMJpdLrvjjNbLJu5kGYQRU',

    // === CHOOSE ONE OF THE FOLLOWING METHODS ===

    // Method 1: Legacy Server Key (if you can find it)
    'server_key' => null, // 'YOUR_LEGACY_SERVER_KEY_HERE',

    // Method 2: Web Push VAPID Key (not present in google-services.json, set manually if needed)
    'vapid_key' => null,

    // Method 3: Service Account (RECOMMENDED - download JSON from Service Accounts tab)
    'service_account_path' => __DIR__ . '/firebase-service-account.json',

    // FCM Settings
    'fcm_url' => 'https://fcm.googleapis.com/fcm/send',
    'fcm_v1_url' => 'https://fcm.googleapis.com/v1/projects/ai-gyan-connect/messages:send',

    // Notification settings
    'default_sound' => 'default',
    'default_icon' => 'ic_notification',
    'click_action' => 'FLUTTER_NOTIFICATION_CLICK',

    // Topic settings for broadcast notifications
    'topics' => [
        'all_users' => 'all_users',
        'students' => 'students',
        'faculty' => 'faculty',
        'admins' => 'admins'
    ]
];
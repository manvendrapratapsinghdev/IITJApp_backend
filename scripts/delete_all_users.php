<?php
/**
 * Delete All Users Script
 * WARNING: This will permanently delete ALL users from the database
 * Run with: php scripts/delete_all_users.php
 */

require_once __DIR__ . '/../src/Core/Autoload.php';

use Core\Database;

echo "\n========================================\n";
echo "DELETE ALL USERS FROM DATABASE\n";
echo "========================================\n\n";

echo "WARNING: This will permanently delete ALL users and related data!\n";
echo "This action cannot be undone.\n\n";

// Ask for confirmation
echo "Type 'DELETE ALL USERS' to confirm: ";
$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));
fclose($handle);

if ($line !== 'DELETE ALL USERS') {
    echo "\nOperation cancelled. No data was deleted.\n";
    exit(0);
}

echo "\nProceeding with deletion...\n\n";

try {
    $db = Database::pdo();
    
    // Get count of users before deletion
    $stmt = $db->query("SELECT COUNT(*) as count FROM users");
    $userCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    echo "Found $userCount users in the database.\n\n";
    
    // Start transaction
    $db->beginTransaction();
    
    // Disable foreign key checks temporarily to avoid constraint issues
    $db->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    echo "Deleting related data...\n";
    
    // Delete data from related tables
    $tables = [
        'notifications' => 'Notifications',
        'notification_preferences' => 'Notification preferences',
        'comments' => 'Comments',
        'post_likes' => 'Post likes',
        'comment_likes' => 'Comment likes',
        'posts' => 'Posts',
        'enrollments' => 'Enrollments',
        'schedules' => 'Schedules',
        'notes' => 'Notes',
        'user_expertise' => 'User expertise',
        'connections' => 'Connections',
        'connection_requests' => 'Connection requests'
    ];
    
    foreach ($tables as $table => $description) {
        try {
            $stmt = $db->query("SELECT COUNT(*) as count FROM $table");
            $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
            
            if ($count > 0) {
                $db->exec("DELETE FROM $table");
                echo "  ✓ Deleted $count records from $description\n";
            } else {
                echo "  - No records in $description\n";
            }
        } catch (Exception $e) {
            echo "  - Skipped $description (table may not exist)\n";
        }
    }
    
    echo "\nDeleting all users...\n";
    
    // Delete all users
    $db->exec("DELETE FROM users");
    
    // Reset auto increment
    $db->exec("ALTER TABLE users AUTO_INCREMENT = 1");
    
    // Re-enable foreign key checks
    $db->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    $db->commit();
    
    echo "\n========================================\n";
    echo "SUCCESS!\n";
    echo "========================================\n";
    echo "✓ Deleted $userCount users\n";
    echo "✓ Deleted all related data\n";
    echo "✓ Reset user ID counter\n";
    echo "\nDatabase is now clean.\n\n";
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    echo "\n========================================\n";
    echo "ERROR!\n";
    echo "========================================\n";
    echo "Failed to delete users: " . $e->getMessage() . "\n";
    echo "No data was deleted due to error.\n\n";
    exit(1);
}

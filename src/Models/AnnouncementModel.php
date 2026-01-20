<?php
namespace Models;
use Core\Database;
use PDO;

class AnnouncementModel {
  private PDO $db;
  public function __construct(){ $this->db = Database::pdo(); }

  public function getAllForUser(int $userId, int $page = 1, int $limit = 20, bool $unreadOnly = false): array {
    $offset = ($page - 1) * $limit;
    
    // Build where clause for announcements
    $whereClause = 'WHERE p.is_announcement = 1';
    $params = [];
    
    if ($unreadOnly) {
      $whereClause .= ' AND pr.read_at IS NULL';
    }
    
    // Get total count of announcements
    $countSql = "SELECT COUNT(*) as total FROM posts p 
                 LEFT JOIN post_reads pr ON p.id = pr.post_id AND pr.user_id = ?
                 $whereClause";
    $countStmt = $this->db->prepare($countSql);
    $countStmt->execute([$userId]);
    $totalNotifications = (int)$countStmt->fetch()['total'];
    
    // Get unread count
    $unreadCountSql = "SELECT COUNT(*) as total FROM posts p 
                       LEFT JOIN post_reads pr ON p.id = pr.post_id AND pr.user_id = ?
                       WHERE p.is_announcement = 1 AND pr.read_at IS NULL";
    $unreadStmt = $this->db->prepare($unreadCountSql);
    $unreadStmt->execute([$userId]);
    $unreadCount = (int)$unreadStmt->fetch()['total'];
    
    // Get announcements with read status
    $sql = "SELECT 
              p.id as post_id,
              p.title,
              p.description,
              p.link,
              p.user_id as created_by,
              p.created_at,
              u.name as created_by_name,
              u.role as created_by_role,
              pr.read_at
            FROM posts p
            LEFT JOIN users u ON p.user_id = u.id
            LEFT JOIN post_reads pr ON p.id = pr.post_id AND pr.user_id = ?
            $whereClause
            ORDER BY p.created_at DESC
            LIMIT ? OFFSET ?";
    
    $st = $this->db->prepare($sql);
    $st->bindValue(1, $userId, PDO::PARAM_INT);
    $st->bindValue(2, $limit, PDO::PARAM_INT);
    $st->bindValue(3, $offset, PDO::PARAM_INT);
    
    $st->execute();
    $notifications = $st->fetchAll();

    $formattedNotifications = [];
    foreach ($notifications as $notif) {
      $formattedNotifications[] = [
        'notification_id' => $notif['post_id'], // Using post_id as notification_id
        'post_id' => $notif['post_id'],
        'title' => $notif['title'],
        'description' => $notif['description'],
        'link' => $notif['link'],
        'created_by' => [
          'user_id' => $notif['created_by'],
          'name' => $notif['created_by_name'],
          'role' => $notif['created_by_role']
        ],
        'is_read' => $notif['read_at'] !== null,
        'read_at' => $notif['read_at'],
        'created_at' => $notif['created_at']
      ];
    }
    
    $totalPages = ceil($totalNotifications / $limit);
    
    return [
      'notifications' => $formattedNotifications,
      'pagination' => [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_notifications' => $totalNotifications,
        'unread_count' => $unreadCount,
        'has_next' => $page < $totalPages,
        'has_prev' => $page > 1
      ]
    ];
  }

  public function markAsRead(int $postId, int $userId): bool {
    $sql = 'INSERT INTO post_reads (user_id, post_id) VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE read_at = CURRENT_TIMESTAMP';
    $st = $this->db->prepare($sql);
    return $st->execute([$userId, $postId]);
  }

  public function markAllAsRead(int $userId): int {
    // Insert read records for all unread announcements
    $sql = 'INSERT IGNORE INTO post_reads (user_id, post_id)
            SELECT ?, p.id 
            FROM posts p
            LEFT JOIN post_reads pr ON p.id = pr.post_id AND pr.user_id = ?
            WHERE p.is_announcement = 1 AND pr.id IS NULL';
    $st = $this->db->prepare($sql);
    $st->execute([$userId, $userId]);
    return $st->rowCount();
  }

  public function delete(int $postId, int $userId): bool {
    // Instead of deleting the post, we'll just mark it as read
    return $this->markAsRead($postId, $userId);
  }

  public function create(array $data): ?int {
    // Create a new announcement post
    $sql = 'INSERT INTO posts (title, description, user_id, is_announcement) VALUES (?, ?, ?, 1)';
    $st = $this->db->prepare($sql);
    
    if ($st->execute([
      $data['title'],
      $data['description'],
      $data['user_id']
    ])) {
      return (int)$this->db->lastInsertId();
    }
    
    return null;
  }

  // Legacy method for backward compatibility
  public function allForUser(int $uid): array {
    $result = $this->getAllForUser($uid, 1, 1000);
    return $result['notifications'];
  }
}
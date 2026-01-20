<?php
namespace Models;
use Core\Database;
use PDO;

class PostModel {
  private PDO $db;
  public function __construct(){ $this->db = Database::pdo(); }

  public function getAllPaginated(int $page = 1, int $limit = 20, bool $includeNotifications = true, bool $onlyNotifications = false, ?int $currentUserId = null): array {
    $offset = ($page - 1) * $limit;
    
    // Build where clause based on business logic
    $whereClause = '';
    if ($onlyNotifications) {
      // Only announcement posts (for /api/notifications endpoint)
      $whereClause = 'WHERE p.is_announcement = 1';
    } elseif (!$includeNotifications) {
      // Only regular posts (exclude announcements)
      $whereClause = 'WHERE p.is_announcement = 0';
    }
    // If includeNotifications = true and onlyNotifications = false, show all posts (no WHERE clause)
    
    // Get total count
    $countSql = "SELECT COUNT(*) as total FROM posts p $whereClause";
    $totalResult = $this->db->query($countSql)->fetch();
    $totalPosts = (int)$totalResult['total'];
    
    // Get posts with user info and engagement data
    $sql = "SELECT 
              p.id as post_id,
              p.title,
              p.description,
              p.link,
              p.is_announcement,
              p.view_count,
              p.link_clicks,
              p.created_at,
              p.updated_at,
              u.id as user_id,
              u.name as user_name,
              u.role as user_role,
              u.is_deleted as is_deleted,
              COUNT(DISTINCT pl.like_id) as likes_count,
              COUNT(DISTINCT CASE WHEN pr.is_deleted = 0 THEN pr.reply_id END) as comments_count,
              MAX(CASE WHEN upl.user_id IS NOT NULL THEN 1 ELSE 0 END) as is_liked
            FROM posts p 
            JOIN users u ON u.id = p.user_id 
            LEFT JOIN post_likes pl ON p.id = pl.post_id
            LEFT JOIN post_replies pr ON p.id = pr.post_id
            LEFT JOIN post_likes upl ON p.id = upl.post_id AND upl.user_id = ?
            $whereClause
            GROUP BY p.id
            ORDER BY p.created_at DESC 
            LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
    
    $st = $this->db->prepare($sql);
    $st->execute([$currentUserId]);
    $posts = $st->fetchAll();
    
    // Format posts
    $formattedPosts = [];
    foreach ($posts as $post) {
      $posterName = $post['is_deleted'] ? '[Deleted User]' : $post['user_name'];
      $formattedPosts[] = [
        'post_id' => $post['post_id'],
        'title' => $post['title'],
        'description' => $post['description'],
        'link' => $post['link'],
        'poster' => [
          'user_id' => $post['user_id'],
          'name' => $posterName,
          'role' => $post['user_role'],
          'is_deleted' => (bool)$post['is_deleted']
        ],
        'is_announcement' => (bool)$post['is_announcement'],
        'likes_count' => (int)$post['likes_count'],
        'comments_count' => (int)$post['comments_count'],
        'is_liked' => (bool)$post['is_liked'],
        'created_at' => $post['created_at'],
        'updated_at' => $post['updated_at']
      ];
    }
    
    $totalPages = ceil($totalPosts / $limit);
    
    return [
      'posts' => $formattedPosts,
      'pagination' => [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_posts' => $totalPosts,
        'has_next' => $page < $totalPages,
        'has_prev' => $page > 1
      ]
    ];
  }

  public function create(int $userId, array $data): ?int {
    $sql = 'INSERT INTO posts (title, description, link, user_id, is_announcement) VALUES (?, ?, ?, ?, ?)';
    $st = $this->db->prepare($sql);
    
    if ($st->execute([
      $data['title'],
      $data['description'],
      $data['link'],
      $userId,
      $data['is_announcement'] ? 1 : 0
    ])) {
      $postId = (int)$this->db->lastInsertId();
      
      // Send push notification for new posts
      $this->sendPostNotification($postId, $userId, $data);
      
      return $postId;
    }
    
    return null;
  }
  
  private function sendPostNotification(int $postId, int $userId, array $postData): void {
    try {
      $firebaseService = new \Services\FirebaseService();
      $userModel = new \Models\UserModel();
      
      // Get poster information
      $poster = $userModel->findById($userId);
      if (!$poster) {
        return;
      }
      
      // Prepare post data for notification
      $post = [
        'post_id' => $postId,
        'title' => $postData['title'],
        'description' => $postData['description'],
        'link' => $postData['link'],
        'is_announcement' => $postData['is_announcement']
      ];
      
      $posterInfo = [
        'user_id' => $poster['id'],
        'name' => $poster['name'],
        'role' => $poster['role']
      ];
      
      // Send the notification
      $firebaseService->notifyNewPost($post, $posterInfo);
    } catch (\Exception $e) {
      // Log error but don't fail the post creation
      error_log("Failed to send post notification: " . $e->getMessage());
    }
  }


  public function getById(int $id, ?int $currentUserId = null): ?array {
    $sql = "SELECT 
              p.id as post_id,
              p.title,
              p.description,
              p.link,
              p.is_announcement,
              p.view_count,
              p.link_clicks,
              p.created_at,
              p.updated_at,
              u.id as user_id,
              u.name as user_name,
              u.role as user_role,
              u.is_deleted as is_deleted,
              COUNT(DISTINCT pl.like_id) as likes_count,
              COUNT(DISTINCT CASE WHEN pr.is_deleted = 0 THEN pr.reply_id END) as comments_count,
              MAX(CASE WHEN upl.user_id IS NOT NULL THEN 1 ELSE 0 END) as is_liked
            FROM posts p 
            JOIN users u ON u.id = p.user_id 
            LEFT JOIN post_likes pl ON p.id = pl.post_id
            LEFT JOIN post_replies pr ON p.id = pr.post_id
            LEFT JOIN post_likes upl ON p.id = upl.post_id AND upl.user_id = ?
            WHERE p.id = ?
            GROUP BY p.id";
    
    $st = $this->db->prepare($sql);
    $st->execute([$currentUserId, $id]);
    $post = $st->fetch();
    
    if (!$post) {
      return null;
    }
    
    $posterName = $post['is_deleted'] ? '[Deleted User]' : $post['user_name'];
    return [
      'post_id' => $post['post_id'],
      'title' => $post['title'],
      'description' => $post['description'],
      'link' => $post['link'],
      'poster' => [
        'user_id' => $post['user_id'],
        'name' => $posterName,
        'role' => $post['user_role'],
        'is_deleted' => (bool)$post['is_deleted']
      ],
      'is_announcement' => (bool)$post['is_announcement'],
      'likes_count' => (int)$post['likes_count'],
      'comments_count' => (int)$post['comments_count'],
      'is_liked' => (bool)$post['is_liked'],
      'created_at' => $post['created_at'],
      'updated_at' => $post['updated_at'],
      'view_count' => (int)$post['view_count'],
      'link_clicks' => (int)$post['link_clicks']
    ];
  }

  public function incrementViewCount(int $id): bool {
    $st = $this->db->prepare('UPDATE posts SET view_count = view_count + 1 WHERE id = ?');
    return $st->execute([$id]);
  }

  public function incrementLinkClicks(int $id): bool {
    $st = $this->db->prepare('UPDATE posts SET link_clicks = link_clicks + 1 WHERE id = ?');
    return $st->execute([$id]);
  }

  public function updateAnnouncementStatus(int $id, bool $isAnnouncement): bool {
    $st = $this->db->prepare('UPDATE posts SET is_announcement = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    return $st->execute([$isAnnouncement ? 1 : 0, $id]);
  }

  public function update(int $id, array $data): bool {
    $updateFields = [];
    $values = [];
    
    if (isset($data['title'])) {
      $updateFields[] = 'title = ?';
      $values[] = $data['title'];
    }
    
    if (isset($data['description'])) {
      $updateFields[] = 'description = ?';
      $values[] = $data['description'];
    }
    
    if (isset($data['link'])) {
      $updateFields[] = 'link = ?';
      $values[] = $data['link'];
    }
    
    if (empty($updateFields)) {
      return false;
    }
    
    $updateFields[] = 'updated_at = CURRENT_TIMESTAMP';
    $values[] = $id;
    
    $sql = 'UPDATE posts SET ' . implode(', ', $updateFields) . ' WHERE id = ?';
    $st = $this->db->prepare($sql);
    
    return $st->execute($values);
  }

  public function delete(int $id): bool {
    $st = $this->db->prepare('DELETE FROM posts WHERE id = ?');
    return $st->execute([$id]);
  }

  // ========================================
  // LIKES FUNCTIONALITY
  // ========================================
  
  /**
   * Add a like to a post
   * @param int $postId Post ID to like
   * @param int $userId User ID who is liking
   * @return bool Success status
   */
  public function addLike(int $postId, int $userId): bool {
    try {
      $sql = 'INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)';
      $st = $this->db->prepare($sql);
      return $st->execute([$postId, $userId]);
    } catch (\PDOException $e) {
      // Check if it's a duplicate key error (already liked)
      if ($e->getCode() == 23000) {
        return false; // Already liked
      }
      throw $e;
    }
  }
  
  /**
   * Remove a like from a post
   * @param int $postId Post ID to unlike
   * @param int $userId User ID who is unliking
   * @return bool Success status
   */
  public function removeLike(int $postId, int $userId): bool {
    $sql = 'DELETE FROM post_likes WHERE post_id = ? AND user_id = ?';
    $st = $this->db->prepare($sql);
    return $st->execute([$postId, $userId]);
  }
  
  /**
   * Check if a user has liked a post
   * @param int $postId Post ID to check
   * @param int $userId User ID to check
   * @return bool True if user has liked the post
   */
  public function hasUserLiked(int $postId, int $userId): bool {
    $sql = 'SELECT 1 FROM post_likes WHERE post_id = ? AND user_id = ? LIMIT 1';
    $st = $this->db->prepare($sql);
    $st->execute([$postId, $userId]);
    return $st->fetch() !== false;
  }
  
  /**
   * Get like count for a post
   * @param int $postId Post ID
   * @return int Number of likes
   */
  public function getLikesCount(int $postId): int {
    $sql = 'SELECT COUNT(*) as count FROM post_likes WHERE post_id = ?';
    $st = $this->db->prepare($sql);
    $st->execute([$postId]);
    $result = $st->fetch();
    return (int)($result['count'] ?? 0);
  }
  
  /**
   * Get list of users who liked a post
   * @param int $postId Post ID
   * @param int $page Page number
   * @param int $limit Items per page
   * @return array List of users with pagination
   */
  public function getLikes(int $postId, int $page = 1, int $limit = 20): array {
    $offset = ($page - 1) * $limit;
    
    // Get total count
    $countSql = 'SELECT COUNT(*) as total FROM post_likes WHERE post_id = ?';
    $countSt = $this->db->prepare($countSql);
    $countSt->execute([$postId]);
    $totalResult = $countSt->fetch();
    $total = (int)$totalResult['total'];
    
    // Get likes with user info
    $sql = "SELECT 
              pl.like_id,
              pl.created_at as liked_at,
              u.id as user_id,
              u.name as full_name,
              u.email as username,
              u.profile_picture,
              u.role
            FROM post_likes pl
            JOIN users u ON pl.user_id = u.id
            WHERE pl.post_id = ?
            ORDER BY pl.created_at DESC
            LIMIT ? OFFSET ?";
    
    $st = $this->db->prepare($sql);
    $st->bindValue(1, $postId, \PDO::PARAM_INT);
    $st->bindValue(2, $limit, \PDO::PARAM_INT);
    $st->bindValue(3, $offset, \PDO::PARAM_INT);
    $st->execute();
    $likes = $st->fetchAll();
    
    $totalPages = ceil($total / $limit);
    
    return [
      'likes' => $likes,
      'total' => $total,
      'page' => $page,
      'limit' => $limit,
      'total_pages' => $totalPages,
      'has_next' => $page < $totalPages,
      'has_prev' => $page > 1
    ];
  }

  // Legacy methods for backward compatibility
  public function all(): array {
    $result = $this->getAllPaginated(1, 100, true);
    return $result['posts'];
  }
}

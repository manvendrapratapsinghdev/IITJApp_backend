<?php
namespace Models;

use Core\Database;
use PDO;

/**
 * CommentModel - Handles all operations related to post comments/replies
 * 
 * Features:
 * - CRUD operations for comments
 * - Soft delete support
 * - Pagination
 * - User authorization checks
 */
class CommentModel {
  private PDO $db;
  
  public function __construct() {
    $this->db = Database::pdo();
  }
  
  /**
   * Get all comments for a post with pagination
   * @param int $postId Post ID
   * @param int $currentUserId Current user ID for ownership check
   * @param int $page Page number
   * @param int $limit Items per page
   * @param string $sort Sort order ('asc' or 'desc')
   * @return array Comments with pagination info
   */
  public function getByPostId(int $postId, int $currentUserId, int $page = 1, int $limit = 20, string $sort = 'asc'): array {
    $offset = ($page - 1) * $limit;
    $sortOrder = strtoupper($sort) === 'DESC' ? 'DESC' : 'ASC';
    
    // Get total count of active comments
    $countSql = 'SELECT COUNT(*) as total FROM post_replies WHERE post_id = ? AND is_deleted = 0';
    $countSt = $this->db->prepare($countSql);
    $countSt->execute([$postId]);
    $totalResult = $countSt->fetch();
    $total = (int)$totalResult['total'];
    
    // Get comments with user info
    $sql = "SELECT 
              pr.reply_id,
              pr.post_id,
              pr.user_id,
              pr.content,
              pr.created_at,
              pr.updated_at,
              (pr.updated_at > pr.created_at) as is_edited,
              (pr.user_id = ?) as is_own_comment,
              u.name as full_name,
              u.email as username,
              u.profile_picture,
              u.role,
              u.is_deleted as user_is_deleted
            FROM post_replies pr
            JOIN users u ON pr.user_id = u.id
            WHERE pr.post_id = ? AND pr.is_deleted = 0
            ORDER BY pr.created_at {$sortOrder}
            LIMIT ? OFFSET ?";
    
    $st = $this->db->prepare($sql);
    $st->bindValue(1, $currentUserId, \PDO::PARAM_INT);
    $st->bindValue(2, $postId, \PDO::PARAM_INT);
    $st->bindValue(3, $limit, \PDO::PARAM_INT);
    $st->bindValue(4, $offset, \PDO::PARAM_INT);
    $st->execute();
    $comments = $st->fetchAll();
    
    // Format comments
    $formattedComments = [];
    foreach ($comments as $comment) {
      $userName = $comment['user_is_deleted'] ? '[Deleted User]' : $comment['full_name'];
      $formattedComments[] = [
        'reply_id' => (int)$comment['reply_id'],
        'post_id' => (int)$comment['post_id'],
        'user_id' => (int)$comment['user_id'],
        'username' => $comment['username'],
        'full_name' => $userName,
        'profile_picture' => $comment['profile_picture'],
        'role' => $comment['role'],
        'content' => $comment['content'],
        'created_at' => $comment['created_at'],
        'updated_at' => $comment['updated_at'],
        'is_edited' => (bool)$comment['is_edited'],
        'is_own_comment' => (bool)$comment['is_own_comment']
      ];
    }
    
    $totalPages = $total > 0 ? ceil($total / $limit) : 1;
    
    return [
      'comments' => $formattedComments,
      'total' => $total,
      'page' => $page,
      'limit' => $limit,
      'total_pages' => $totalPages,
      'has_next' => $page < $totalPages,
      'has_prev' => $page > 1
    ];
  }
  
  /**
   * Get a single comment by ID
   * @param int $replyId Comment ID
   * @return array|null Comment data or null if not found
   */
  public function getById(int $replyId): ?array {
    $sql = "SELECT 
              pr.reply_id,
              pr.post_id,
              pr.user_id,
              pr.content,
              pr.created_at,
              pr.updated_at,
              pr.is_deleted,
              u.name as full_name,
              u.email as username
            FROM post_replies pr
            JOIN users u ON pr.user_id = u.id
            WHERE pr.reply_id = ?";
    
    $st = $this->db->prepare($sql);
    $st->execute([$replyId]);
    $comment = $st->fetch();
    
    if (!$comment) {
      return null;
    }
    
    return [
      'reply_id' => (int)$comment['reply_id'],
      'post_id' => (int)$comment['post_id'],
      'user_id' => (int)$comment['user_id'],
      'username' => $comment['username'],
      'full_name' => $comment['full_name'],
      'content' => $comment['content'],
      'created_at' => $comment['created_at'],
      'updated_at' => $comment['updated_at'],
      'is_deleted' => (bool)$comment['is_deleted']
    ];
  }
  
  /**
   * Create a new comment
   * @param int $postId Post ID
   * @param int $userId User ID creating the comment
   * @param string $content Comment content
   * @return int|null Newly created comment ID or null on failure
   */
  public function create(int $postId, int $userId, string $content): ?int {
    // Sanitize content
    $content = trim($content);
    
    if (empty($content)) {
      return null;
    }
    
    $sql = 'INSERT INTO post_replies (post_id, user_id, content) VALUES (?, ?, ?)';
    $st = $this->db->prepare($sql);
    
    if ($st->execute([$postId, $userId, $content])) {
      return (int)$this->db->lastInsertId();
    }
    
    return null;
  }
  
  /**
   * Update a comment
   * @param int $replyId Comment ID
   * @param string $content New content
   * @return bool Success status
   */
  public function update(int $replyId, string $content): bool {
    // Sanitize content
    $content = trim($content);
    
    if (empty($content)) {
      return false;
    }
    
    $sql = 'UPDATE post_replies SET content = ?, updated_at = CURRENT_TIMESTAMP WHERE reply_id = ? AND is_deleted = 0';
    $st = $this->db->prepare($sql);
    return $st->execute([$content, $replyId]);
  }
  
  /**
   * Soft delete a comment
   * @param int $replyId Comment ID
   * @param int $deletedBy User ID who is deleting (for audit)
   * @return bool Success status
   */
  public function delete(int $replyId, int $deletedBy): bool {
    $sql = 'UPDATE post_replies SET is_deleted = 1, deleted_at = CURRENT_TIMESTAMP, deleted_by = ? WHERE reply_id = ? AND is_deleted = 0';
    $st = $this->db->prepare($sql);
    return $st->execute([$deletedBy, $replyId]);
  }
  
  /**
   * Get comment count for a post
   * @param int $postId Post ID
   * @return int Number of active comments
   */
  public function getCountByPostId(int $postId): int {
    $sql = 'SELECT COUNT(*) as count FROM post_replies WHERE post_id = ? AND is_deleted = 0';
    $st = $this->db->prepare($sql);
    $st->execute([$postId]);
    $result = $st->fetch();
    return (int)($result['count'] ?? 0);
  }
  
  /**
   * Check if user owns a comment
   * @param int $replyId Comment ID
   * @param int $userId User ID to check
   * @return bool True if user owns the comment
   */
  public function isOwner(int $replyId, int $userId): bool {
    $sql = 'SELECT 1 FROM post_replies WHERE reply_id = ? AND user_id = ? AND is_deleted = 0 LIMIT 1';
    $st = $this->db->prepare($sql);
    $st->execute([$replyId, $userId]);
    return $st->fetch() !== false;
  }
  
  /**
   * Check if a comment exists and is not deleted
   * @param int $replyId Comment ID
   * @return bool True if comment exists and is active
   */
  public function exists(int $replyId): bool {
    $sql = 'SELECT 1 FROM post_replies WHERE reply_id = ? AND is_deleted = 0 LIMIT 1';
    $st = $this->db->prepare($sql);
    $st->execute([$replyId]);
    return $st->fetch() !== false;
  }
  
  /**
   * Get post ID from comment ID
   * @param int $replyId Comment ID
   * @return int|null Post ID or null if not found
   */
  public function getPostIdByReplyId(int $replyId): ?int {
    $sql = 'SELECT post_id FROM post_replies WHERE reply_id = ?';
    $st = $this->db->prepare($sql);
    $st->execute([$replyId]);
    $result = $st->fetch();
    return $result ? (int)$result['post_id'] : null;
  }
}

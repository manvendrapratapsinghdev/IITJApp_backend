<?php
namespace Controllers;

use Core\Response;
use Core\Auth as AuthCore;
use Models\CommentModel;
use Models\PostModel;
use Models\UserModel;

/**
 * CommentController - Handles all comment/reply related endpoints
 * 
 * Endpoints:
 * - GET /posts/{post_id}/comments - Get all comments for a post
 * - POST /posts/{post_id}/comments - Add a comment to a post
 * - PUT /comments/{reply_id} - Edit a comment
 * - DELETE /comments/{reply_id} - Delete a comment
 */
class CommentController {
  private CommentModel $comments;
  private PostModel $posts;
  private UserModel $users;
  
  public function __construct() {
    $this->comments = new CommentModel();
    $this->posts = new PostModel();
    $this->users = new UserModel();
  }
  
  /**
   * GET /posts/{post_id}/comments
   * Get all comments for a post with pagination
   */
  public function getComments(array $params) {
    $p = AuthCore::requireUser();
    $postId = (int)$params['post_id'];
    $userId = (int)$p['sub'];
    
    // Check if post exists
    $post = $this->posts->getById($postId, $userId);
    if (!$post) {
      return Response::json([
        'success' => false,
        'message' => 'Post not found'
      ], 404);
    }
    
    // Get pagination parameters
    $page = max(1, (int)($_GET['page'] ?? 1));
    $limit = min(max(1, (int)($_GET['limit'] ?? 20)), 100); // Between 1-100
    $sort = strtolower($_GET['sort'] ?? 'desc');
    
    // Validate sort parameter
    if (!in_array($sort, ['asc', 'desc'])) {
      $sort = 'asc';
    }
    
    $result = $this->comments->getByPostId($postId, $userId, $page, $limit, $sort);
    
    return Response::json([
      'success' => true,
      'data' => $result
    ]);
  }
  
  /**
   * POST /posts/{post_id}/comments
   * Add a new comment to a post
   */
  public function addComment(array $params) {
    $p = AuthCore::requireUser();
    $postId = (int)$params['post_id'];
    $userId = (int)$p['sub'];
    $in = Response::input();
    
    // Check if post exists
    $post = $this->posts->getById($postId, $userId);
    if (!$post) {
      return Response::json([
        'success' => false,
        'message' => 'Post not found'
      ], 404);
    }
    
    // Validate input
    if (!isset($in['content']) || trim($in['content']) === '') {
      return Response::json([
        'success' => false,
        'message' => 'Comment content is required',
        'errors' => ['content' => 'Comment content cannot be empty']
      ], 422);
    }
    
    $content = trim($in['content']);
    
    // Validate length
    if (strlen($content) < 1) {
      return Response::json([
        'success' => false,
        'message' => 'Comment is too short',
        'errors' => ['content' => 'Comment must be at least 1 character']
      ], 422);
    }
    
    if (strlen($content) > 1000) {
      return Response::json([
        'success' => false,
        'message' => 'Comment is too long',
        'errors' => ['content' => 'Comment must not exceed 1000 characters']
      ], 422);
    }
    
    // Sanitize content (prevent XSS)
    $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
    
    // Create comment
    $replyId = $this->comments->create($postId, $userId, $content);
    
    if ($replyId) {
      $comment = $this->comments->getById($replyId);
      
      return Response::json([
        'success' => true,
        'message' => 'Comment added successfully',
        'data' => $comment
      ], 201);
    }
    
    return Response::json([
      'success' => false,
      'message' => 'Failed to add comment'
    ], 500);
  }
  
  /**
   * PUT /comments/{reply_id}
   * Edit an existing comment
   */
  public function editComment(array $params) {
    $p = AuthCore::requireUser();
    $replyId = (int)$params['reply_id'];
    $userId = (int)$p['sub'];
    $in = Response::input();
    
    // Check if comment exists
    $comment = $this->comments->getById($replyId);
    if (!$comment) {
      return Response::json([
        'success' => false,
        'message' => 'Comment not found'
      ], 404);
    }
    
    // Check if comment is deleted
    if ($comment['is_deleted']) {
      return Response::json([
        'success' => false,
        'message' => 'Comment has been deleted'
      ], 410);
    }
    
    // Check authorization (owner or admin)
    $isOwner = $this->comments->isOwner($replyId, $userId);
    
    $currentUser = $this->users->findById($userId);
    $isAdmin = $currentUser && in_array($currentUser['role'], ['admin', 'super_admin', 'faculty']);
    
    if (!$isOwner && !$isAdmin) {
      return Response::json([
        'success' => false,
        'message' => 'You don\'t have permission to edit this comment'
      ], 403);
    }
    
    // Validate input
    if (!isset($in['content']) || trim($in['content']) === '') {
      return Response::json([
        'success' => false,
        'message' => 'Comment content is required',
        'errors' => ['content' => 'Comment content cannot be empty']
      ], 422);
    }
    
    $content = trim($in['content']);
    
    // Validate length
    if (strlen($content) < 1) {
      return Response::json([
        'success' => false,
        'message' => 'Comment is too short',
        'errors' => ['content' => 'Comment must be at least 1 character']
      ], 422);
    }
    
    if (strlen($content) > 1000) {
      return Response::json([
        'success' => false,
        'message' => 'Comment is too long',
        'errors' => ['content' => 'Comment must not exceed 1000 characters']
      ], 422);
    }
    
    // Sanitize content
    $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
    
    // Update comment
    $success = $this->comments->update($replyId, $content);
    
    if ($success) {
      $updatedComment = $this->comments->getById($replyId);
      
      return Response::json([
        'success' => true,
        'message' => 'Comment updated successfully',
        'data' => $updatedComment
      ]);
    }
    
    return Response::json([
      'success' => false,
      'message' => 'Failed to update comment'
    ], 500);
  }
  
  /**
   * DELETE /comments/{reply_id}
   * Delete a comment (soft delete)
   */
  public function deleteComment(array $params) {
    $p = AuthCore::requireUser();
    $replyId = (int)$params['reply_id'];
    $userId = (int)$p['sub'];
    
    // Check if comment exists
    $comment = $this->comments->getById($replyId);
    if (!$comment) {
      return Response::json([
        'success' => false,
        'message' => 'Comment not found'
      ], 404);
    }
    
    // Check if already deleted
    if ($comment['is_deleted']) {
      return Response::json([
        'success' => false,
        'message' => 'Comment has already been deleted'
      ], 410);
    }
    
    // Check authorization (owner or admin)
    $isOwner = $this->comments->isOwner($replyId, $userId);
    
    $currentUser = $this->users->findById($userId);
    $isAdmin = $currentUser && in_array($currentUser['role'], ['admin', 'super_admin', 'faculty']);
    
    if (!$isOwner && !$isAdmin) {
      return Response::json([
        'success' => false,
        'message' => 'You don\'t have permission to delete this comment'
      ], 403);
    }
    
    // Delete comment (soft delete)
    $success = $this->comments->delete($replyId, $userId);
    
    if ($success) {
      return Response::json([
        'success' => true,
        'message' => 'Comment deleted successfully'
      ]);
    }
    
    return Response::json([
      'success' => false,
      'message' => 'Failed to delete comment'
    ], 500);
  }
}

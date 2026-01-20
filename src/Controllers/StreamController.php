<?php
namespace Controllers;
use Core\Response;
use Core\Auth as AuthCore;
use Models\PostModel;
use Models\UserModel;
use Models\AnnouncementModel;

class StreamController {
  private PostModel $posts;
  private UserModel $users;
  private AnnouncementModel $announcements;
  public function __construct(){ 
    $this->posts = new PostModel(); 
    $this->users = new UserModel();
    $this->announcements = new AnnouncementModel();
  }

  // GET /stream/posts - Get paginated list of ALL stream posts (both regular and announcements)
  public function getPosts(){
    $p = AuthCore::requireUser();
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    
    // Always include all posts (both regular posts and announcement posts)
    $result = $this->posts->getAllPaginated($page, $limit, true, false, (int)$p['sub']);
    
    return Response::json([
      'success' => true,
      'posts' => $result['posts'],
      'pagination' => $result['pagination']
    ]);
  }

  // GET /announcements - Get paginated list of ONLY announcement posts (marked by admin/super admin)
  public function getAnnouncements(){
    $p = AuthCore::requireUser();
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    
    // Only get posts that are marked as announcements
    $result = $this->posts->getAllPaginated($page, $limit, false, true, (int)$p['sub']); // includeAnnouncements=false, onlyAnnouncements=true
    
    return Response::json([
      'success' => true,
      'posts' => $result['posts'],
      'pagination' => $result['pagination']
    ]);
  }

  // POST /stream/posts - Create a new stream post
  public function createPost(){
    $p = AuthCore::requireUser();
    $in = Response::input();
    
    if (empty($in['title'])) {
      return Response::json([
        'success' => false,
        'message' => 'Title is required'
      ], 422);
    }
    
    if (empty($in['description'])) {
      return Response::json([
        'success' => false,
        'message' => 'Description is required'
      ], 422);
    }
    
    $markAsAnnouncement = $in['is_announcement'] ?? $in['mark_as_announcement'] ?? false;
    
    // Check if user has permission to mark as announcement (check from database for current role)
    if ($markAsAnnouncement) {
      $currentUser = $this->users->findById((int)$p['sub']);
      if (!$currentUser || !in_array($currentUser['role'], ['admin', 'super_admin', 'faculty'])) {
        return Response::json([
          'success' => false,
          'message' => 'Only admins can mark posts as announcements',
          'statusCode' => 403
        ], 403);
      }
    }
    
    $postData = [
      'title' => $in['title'],
      'description' => $in['description'],
      'link' => $in['link'] ?? null,
      'is_announcement' => $markAsAnnouncement
    ];
    
    $postId = $this->posts->create((int)$p['sub'], $postData);
    
    if ($postId) {
      $post = $this->posts->getById($postId);
      return Response::json([
        'success' => true,
        'message' => 'Post created successfully',
        'post' => $post
      ], 201);
    }
    
    return Response::json([
      'success' => false,
      'message' => 'Failed to create post'
    ], 500);
  }

  // GET /stream/posts/{post_id} - Get detailed post information
  public function getPost(array $params){
    $p = AuthCore::requireUser();
    $postId = (int)$params['id'];
    $post = $this->posts->getById($postId, (int)$p['sub']);
    
    if (!$post) {
      return Response::json([
        'success' => false,
        'message' => 'Post not found'
      ], 404);
    }
    
    // Increment view count
    $this->posts->incrementViewCount($postId);
    
    return Response::json([
      'success' => true,
      'post' => $post
    ]);
  }

  // PATCH /stream/posts/{post_id}/announcement - Mark/unmark post as announcement
  public function toggleAnnouncement(array $params){
    $p = AuthCore::requireUser();
    
    // Check admin permission (check from database for current role)
    $currentUser = $this->users->findById((int)$p['sub']);
    if (!$currentUser || !in_array($currentUser['role'], ['admin', 'super_admin', 'faculty'])) {
      return Response::json([
        'success' => false,
        'message' => 'Only admins can mark posts as announcements',
        'statusCode' => 403
      ], 403);
    }
    
    $postId = (int)$params['id'];
    $in = Response::input();
    $markAsAnnouncement = $in['is_announcement'] ?? $in['mark_as_announcement'] ?? false;
    
    $success = $this->posts->updateAnnouncementStatus($postId, $markAsAnnouncement);
    
    if ($success) {
      return Response::json([
        'success' => true,
        'message' => $markAsAnnouncement ? 'Post marked as announcement successfully' : 'Post unmarked as announcement successfully',
        'post' => [
          'post_id' => $postId,
          'is_announcement' => $markAsAnnouncement,
          'updated_at' => date('Y-m-d\TH:i:s\Z')
        ]
      ]);
    }
    
    return Response::json([
      'success' => false,
      'message' => 'Failed to update announcement status'
    ], 500);
  }

  // DELETE /stream/posts/{post_id} - Delete a post
  public function deletePost(array $params){
    $p = AuthCore::requireUser();
    $postId = (int)$params['id'];
    
    $post = $this->posts->getById($postId);
    if (!$post) {
      return Response::json([
        'success' => false,
        'message' => 'Post not found'
      ], 404);
    }
    
    // Check if user is post owner or admin (check current role from database)
    $isOwner = (int)$post['poster']['user_id'] === (int)$p['sub'];
    
    $currentUser = $this->users->findById((int)$p['sub']);
    $isAdmin = $currentUser && in_array($currentUser['role'], ['admin', 'super_admin', 'faculty']);
    
    if (!$isOwner && !$isAdmin) {
      return Response::json([
        'success' => false,
        'message' => 'You don\'t have permission to delete this post',
        'statusCode' => 403
      ], 403);
    }
    
    $success = $this->posts->delete($postId);
    
    if ($success) {
      return Response::json([
        'success' => true,
        'message' => 'Post deleted successfully'
      ]);
    }
    
    return Response::json([
      'success' => false,
      'message' => 'Failed to delete post'
    ], 500);
  }

  // PUT /stream/posts/{post_id} - Update a post
  public function updatePost(array $params){
    $p = AuthCore::requireUser();
    $postId = (int)$params['id'];
    $in = Response::input();
    
    $post = $this->posts->getById($postId);
    if (!$post) {
      return Response::json([
        'success' => false,
        'message' => 'Post not found'
      ], 404);
    }
    
    // Check if user is post owner or admin
    $isOwner = (int)$post['poster']['user_id'] === (int)$p['sub'];
    
    $currentUser = $this->users->findById((int)$p['sub']);
    $isAdmin = $currentUser && in_array($currentUser['role'], ['admin', 'super_admin', 'faculty']);
    
    if (!$isOwner && !$isAdmin) {
      return Response::json([
        'success' => false,
        'message' => 'You don\'t have permission to edit this post',
        'statusCode' => 403
      ], 403);
    }
    
    $updateData = [];
    if (isset($in['title'])) {
      if (empty($in['title'])) {
        return Response::json([
          'success' => false,
          'message' => 'Title cannot be empty'
        ], 422);
      }
      $updateData['title'] = $in['title'];
    }
    
    if (isset($in['description'])) {
      if (empty($in['description'])) {
        return Response::json([
          'success' => false,
          'message' => 'Description cannot be empty'
        ], 422);
      }
      $updateData['description'] = $in['description'];
    }
    
    if (isset($in['link'])) {
      $updateData['link'] = $in['link'];
    }
    
    // Handle announcement status update
    if (isset($in['is_announcement']) || isset($in['mark_as_announcement'])) {
      $markAsAnnouncement = $in['is_announcement'] ?? $in['mark_as_announcement'] ?? false;
      
      // Check if user has permission to mark/unmark as announcement
      if ($markAsAnnouncement && !$isAdmin) {
        return Response::json([
          'success' => false,
          'message' => 'Only admins can mark posts as announcements',
          'statusCode' => 403
        ], 403);
      }
      
      $updateData['is_announcement'] = $markAsAnnouncement;
    }
    
    $success = $this->posts->update($postId, $updateData);
    
    if ($success) {
      $updatedPost = $this->posts->getById($postId);
      return Response::json([
        'success' => true,
        'message' => 'Post updated successfully',
        'post' => $updatedPost
      ]);
    }
    
    return Response::json([
      'success' => false,
      'message' => 'Failed to update post'
    ], 500);
  }

  // Legacy methods for backward compatibility
  public function index(){ 
    return $this->getPosts();
  }

  public function create(){
    return $this->createPost();
  }
  
  // ========================================
  // LIKES ENDPOINTS
  // ========================================
  
  // POST /stream/posts/{post_id}/like - Like a post
  public function likePost(array $params){
    $p = AuthCore::requireUser();
    $postId = (int)$params['id'];
    $userId = (int)$p['sub'];
    
    // Check if post exists
    $post = $this->posts->getById($postId, $userId);
    if (!$post) {
      return Response::json([
        'success' => false,
        'message' => 'Post not found'
      ], 404);
    }
    
    // Check if already liked
    if ($this->posts->hasUserLiked($postId, $userId)) {
      return Response::json([
        'success' => false,
        'message' => 'You have already liked this post'
      ], 409);
    }
    
    // Add like
    $success = $this->posts->addLike($postId, $userId);
    
    if ($success) {
      $likesCount = $this->posts->getLikesCount($postId);
      return Response::json([
        'success' => true,
        'message' => 'Post liked successfully',
        'data' => [
          'post_id' => $postId,
          'likes_count' => $likesCount,
          'is_liked' => true
        ]
      ]);
    }
    
    return Response::json([
      'success' => false,
      'message' => 'Failed to like post'
    ], 500);
  }
  
  // DELETE /stream/posts/{post_id}/like - Unlike a post
  public function unlikePost(array $params){
    $p = AuthCore::requireUser();
    $postId = (int)$params['id'];
    $userId = (int)$p['sub'];
    
    // Check if post exists
    $post = $this->posts->getById($postId, $userId);
    if (!$post) {
      return Response::json([
        'success' => false,
        'message' => 'Post not found'
      ], 404);
    }
    
    // Check if not liked
    if (!$this->posts->hasUserLiked($postId, $userId)) {
      return Response::json([
        'success' => false,
        'message' => 'You have not liked this post'
      ], 409);
    }
    
    // Remove like
    $success = $this->posts->removeLike($postId, $userId);
    
    if ($success) {
      $likesCount = $this->posts->getLikesCount($postId);
      return Response::json([
        'success' => true,
        'message' => 'Post unliked successfully',
        'data' => [
          'post_id' => $postId,
          'likes_count' => $likesCount,
          'is_liked' => false
        ]
      ]);
    }
    
    return Response::json([
      'success' => false,
      'message' => 'Failed to unlike post'
    ], 500);
  }
  
  // GET /stream/posts/{post_id}/likes - Get list of users who liked a post
  public function getPostLikes(array $params){
    $p = AuthCore::requireUser();
    $postId = (int)$params['id'];
    
    // Check if post exists
    $post = $this->posts->getById($postId, (int)$p['sub']);
    if (!$post) {
      return Response::json([
        'success' => false,
        'message' => 'Post not found'
      ], 404);
    }
    
    $page = (int)($_GET['page'] ?? 1);
    $limit = min((int)($_GET['limit'] ?? 20), 100); // Max 100 per page
    
    $result = $this->posts->getLikes($postId, $page, $limit);
    
    return Response::json([
      'success' => true,
      'data' => $result
    ]);
  }
}

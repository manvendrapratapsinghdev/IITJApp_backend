<?php
namespace Controllers;
use Core\Response;
use Core\Auth as AuthCore;
use Models\AnnouncementModel;

class AnnouncementController {
  private AnnouncementModel $notifications;
  public function __construct(){ $this->notifications = new AnnouncementModel(); }

  // GET /notifications - Get user's notifications
  public function getNotifications(){
    $p = AuthCore::requireUser();
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $unreadOnly = ($_GET['unread_only'] ?? 'false') === 'true';
    
    $result = $this->notifications->getAllForUser((int)$p['sub'], $page, $limit, $unreadOnly);
    
    return Response::json([
      'success' => true,
      'notifications' => $result['notifications'],
      'pagination' => $result['pagination']
    ]);
  }

  // PATCH /notifications/{notification_id}/read - Mark notification as read
  public function markAsRead(array $params){
    $p = AuthCore::requireUser();
    $notificationId = (int)$params['id'];
    
    $success = $this->notifications->markAsRead($notificationId, (int)$p['sub']);
    
    if ($success) {
      return Response::json([
        'success' => true,
        'message' => 'Notification marked as read',
        'notification' => [
          'notification_id' => $notificationId,
          'is_read' => true,
          'read_at' => date('Y-m-d\TH:i:s\Z')
        ]
      ]);
    }
    
    return Response::json([
      'success' => false,
      'message' => 'Failed to mark notification as read'
    ], 500);
  }

  // PATCH /notifications/read-all - Mark all notifications as read
  public function markAllAsRead(){
    $p = AuthCore::requireUser();
    
    $count = $this->notifications->markAllAsRead((int)$p['sub']);
    
    return Response::json([
      'success' => true,
      'message' => 'All notifications marked as read',
      'updated_count' => $count,
      'updated_at' => date('Y-m-d\TH:i:s\Z')
    ]);
  }

  // DELETE /notifications/{notification_id} - Delete notification
  public function deleteNotification(array $params){
    $p = AuthCore::requireUser();
    $notificationId = (int)$params['id'];
    
    $success = $this->notifications->delete($notificationId, (int)$p['sub']);
    
    if ($success) {
      return Response::json([
        'success' => true,
        'message' => 'Notification deleted successfully'
      ]);
    }
    
    return Response::json([
      'success' => false,
      'message' => 'Failed to delete notification'
    ], 500);
  }

  // GET /notifications/preferences - Get notification preferences
  public function getPreferences(){
    $p = AuthCore::requireUser();
    
    $preferences = $this->notifications->getPreferences((int)$p['sub']);
    
    return Response::json([
      'success' => true,
      'preferences' => $preferences
    ]);
  }

  // PUT /notifications/preferences - Update notification preferences
  public function updatePreferences(){
    $p = AuthCore::requireUser();
    $in = Response::input();
    
    $preferences = [
      'push_notifications' => $in['push_notifications'] ?? true,
      'email_notifications' => $in['email_notifications'] ?? false,
      'academic_notifications' => $in['notification_types']['academic'] ?? true,
      'social_notifications' => $in['notification_types']['social'] ?? true,
      'admin_notifications' => $in['notification_types']['admin'] ?? false,
      'system_notifications' => $in['notification_types']['system'] ?? true,
      'quiet_hours_enabled' => $in['quiet_hours']['enabled'] ?? false,
      'quiet_hours_start' => $in['quiet_hours']['start_time'] ?? null,
      'quiet_hours_end' => $in['quiet_hours']['end_time'] ?? null
    ];
    
    $success = $this->notifications->updatePreferences((int)$p['sub'], $preferences);
    
    if ($success) {
      $updatedPreferences = $this->notifications->getPreferences((int)$p['sub']);
      return Response::json([
        'success' => true,
        'message' => 'Notification preferences updated successfully',
        'preferences' => $updatedPreferences
      ]);
    }
    
    return Response::json([
      'success' => false,
      'message' => 'Failed to update notification preferences'
    ], 500);
  }

  // Legacy methods for backward compatibility
  public function index(){
    return $this->getNotifications();
  }

  public function create(){
    $p = AuthCore::requireUser();
    $in = Response::input();
    
    if (empty($in['title']) || empty($in['description'])) {
      return Response::json(['error' => 'title and description required'], 422);
    }
    
    $notificationData = [
      'title' => $in['title'],
      'description' => $in['description'],
      'type' => $in['type'] ?? 'social',
      'priority' => $in['priority'] ?? 'medium',
      'user_id' => (int)$p['sub'],
      'created_by' => (int)$p['sub']
    ];
    
    $id = $this->notifications->create($notificationData);
    return Response::json(['id' => $id]);
  }
}

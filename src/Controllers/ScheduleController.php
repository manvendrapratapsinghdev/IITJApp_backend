<?php
namespace Controllers;
use Core\Response;
use Core\Auth as AuthCore;
use Models\ScheduleModel;
use Models\SubjectModel;
use Models\UserModel;

class ScheduleController {
  private ScheduleModel $schedules;
  private SubjectModel $subjects;
  private UserModel $users;
  
  public function __construct(){ 
    $this->schedules = new ScheduleModel(); 
    $this->subjects = new SubjectModel();
    $this->users = new UserModel();
  }

  // GET /semester/schedules - Get schedules in tab-wise format (quiz and assignment tabs)
  public function getSchedules(){
    $p = AuthCore::requireUser();
    
    $upcomingOnly = ($_GET['upcoming_only'] ?? 'true') === 'true';
    $limit = (int)($_GET['limit'] ?? 50);
    
    // Get quiz schedules
    $quizSchedules = $this->schedules->getAll('quiz', null, $upcomingOnly, $limit);
    
    // Get assignment schedules
    $assignmentSchedules = $this->schedules->getAll('assignment', null, $upcomingOnly, $limit);
    
    return Response::json([
      'success' => true,
      'schedules' => [
        'quiz' => $quizSchedules,
        'assignment' => $assignmentSchedules
      ]
    ]);
  }

  // GET /semester/schedules/{schedule_id} - Get detailed schedule information
  public function getSchedule(array $params){
    $p = AuthCore::requireUser();
    
    $scheduleId = (int)$params['id'];
    $schedule = $this->schedules->getById($scheduleId);
    
    if (!$schedule) {
      return Response::json([
        'success' => false,
        'message' => 'Schedule not found'
      ], 404);
    }
    
    return Response::json([
      'success' => true,
      'schedule' => $schedule
    ]);
  }

  // POST /admin/schedules - Create new schedule (Admin only)
  public function createSchedule(){
    $p = AuthCore::requireUser();
    
    // Check permission - only admins and super_admins can create schedules
    if (!in_array($p['role'], ['admin', 'super_admin'])) {
      return Response::json([
        'success' => false,
        'message' => 'Only admins can create schedules',
        'statusCode' => 403
      ], 403);
    }
    
    $in = Response::input();
    
    // Validate required fields
    $requiredFields = ['type', 'title', 'subject_id', 'date'];
    foreach ($requiredFields as $field) {
      if (empty($in[$field])) {
        return Response::json([
          'success' => false,
          'message' => "Field $field is required"
        ], 422);
      }
    }
    
    // Validate type - only quiz and assignment allowed
    $allowedTypes = ['quiz', 'assignment'];
    if (!in_array($in['type'], $allowedTypes)) {
      return Response::json([
        'success' => false,
        'message' => 'Schedule type must be either quiz or assignment'
      ], 422);
    }
    
    // Validate subject exists
    $subject = $this->subjects->getById((int)$in['subject_id']);
    if (!$subject) {
      return Response::json([
        'success' => false,
        'message' => 'Subject not found'
      ], 404);
    }
    
    $scheduleData = [
      'type' => $in['type'],
      'title' => $in['title'],
      'description' => $in['description'] ?? '',
      'subject_id' => (int)$in['subject_id'],
      'date' => $in['date'],
      'duration_minutes' => (int)($in['duration_minutes'] ?? 60),
      'location' => $in['location'] ?? '',
      'instructions' => $in['instructions'] ?? '',
      'submission_link' => $in['submission_link'] ?? '',
      'max_marks' => (int)($in['max_marks'] ?? 100),
      'created_by' => (int)$p['sub']
    ];
    
    $scheduleId = $this->schedules->create($scheduleData);
    
    if ($scheduleId) {
      $schedule = $this->schedules->getById($scheduleId);
      return Response::json([
        'success' => true,
        'message' => 'Schedule created successfully',
        'schedule' => $schedule
      ], 201);
    }
    
    return Response::json([
      'success' => false,
      'message' => 'Failed to create schedule'
    ], 500);
  }

  // PUT /admin/schedules/{schedule_id} - Update schedule (Admin only)
  public function updateSchedule(array $params){
    $p = AuthCore::requireUser();
    
    // Check permission - only admins and super_admins can update schedules
    if (!in_array($p['role'], ['admin', 'super_admin'])) {
      return Response::json([
        'success' => false,
        'message' => 'Only admins can update schedules',
        'statusCode' => 403
      ], 403);
    }
    
    $scheduleId = (int)$params['id'];
    $schedule = $this->schedules->getById($scheduleId);
    
    if (!$schedule) {
      return Response::json([
        'success' => false,
        'message' => 'Schedule not found'
      ], 404);
    }
    
    $in = Response::input();
    
    // Validate type if provided - only quiz and assignment allowed
    if (isset($in['type']) && !in_array($in['type'], ['quiz', 'assignment'])) {
      return Response::json([
        'success' => false,
        'message' => 'Schedule type must be either quiz or assignment'
      ], 422);
    }
    
    // Validate subject if provided
    if (isset($in['subject_id'])) {
      $subject = $this->subjects->getById((int)$in['subject_id']);
      if (!$subject) {
        return Response::json([
          'success' => false,
          'message' => 'Subject not found'
        ], 404);
      }
    }
    
    $scheduleData = [
      'type' => $in['type'] ?? $schedule['type'],
      'title' => $in['title'] ?? $schedule['title'],
      'description' => $in['description'] ?? $schedule['description'],
      'subject_id' => isset($in['subject_id']) ? (int)$in['subject_id'] : $schedule['subject']['subject_id'],
      'date' => $in['date'] ?? $schedule['date'],
      'duration_minutes' => isset($in['duration_minutes']) ? (int)$in['duration_minutes'] : $schedule['duration_minutes'],
      'location' => $in['location'] ?? $schedule['location'],
      'instructions' => $in['instructions'] ?? $schedule['instructions'],
      'submission_link' => $in['submission_link'] ?? $schedule['submission_link'],
      'max_marks' => isset($in['max_marks']) ? (int)$in['max_marks'] : $schedule['max_marks']
    ];
    
    $updated = $this->schedules->update($scheduleId, $scheduleData);
    
    if ($updated) {
      $updatedSchedule = $this->schedules->getById($scheduleId);
      return Response::json([
        'success' => true,
        'message' => 'Schedule updated successfully',
        'schedule' => $updatedSchedule
      ]);
    }
    
    return Response::json([
      'success' => false,
      'message' => 'Failed to update schedule'
    ], 500);
  }

  // DELETE /admin/schedules/{schedule_id} - Delete schedule (Admin only)
  public function deleteSchedule(array $params){
    $p = AuthCore::requireUser();
    
    // Check permission - only admins and super_admins can delete schedules
    if (!in_array($p['role'], ['admin', 'super_admin'])) {
      return Response::json([
        'success' => false,
        'message' => 'Only admins can delete schedules',
        'statusCode' => 403
      ], 403);
    }
    
    $scheduleId = (int)$params['id'];
    $schedule = $this->schedules->getById($scheduleId);
    
    if (!$schedule) {
      return Response::json([
        'success' => false,
        'message' => 'Schedule not found'
      ], 404);
    }
    
    $deleted = $this->schedules->delete($scheduleId);
    
    if ($deleted) {
      return Response::json([
        'success' => true,
        'message' => 'Schedule deleted successfully'
      ]);
    }
    
    return Response::json([
      'success' => false,
      'message' => 'Failed to delete schedule'
    ], 500);
  }

  // GET /admin/schedules/subjects - Get subjects for schedule assignment
  public function getSubjectsForSchedule(){
    $p = AuthCore::requireUser();
    
    // Check permission
    if (!in_array($p['role'], ['admin', 'super_admin'])) {
      return Response::json([
        'success' => false,
        'message' => 'Only admins can access this resource',
        'statusCode' => 403
      ], 403);
    }
    
    $subjects = $this->subjects->getAll();
    
    return Response::json([
      'success' => true,
      'subjects' => $subjects
    ]);
  }

  // GET /today-classes - Get today's classes with status
  public function getTodayClasses() {
    try {
      // Get current day of week (0 = Sunday, 6 = Saturday)
      $currentDay = date('w');
      
      // Map to our days (assuming Saturday = 6, Sunday = 0)
      $isSaturday = ($currentDay == 6);
      $isSunday = ($currentDay == 0);
      
      if (!$isSaturday && !$isSunday) {
        return Response::json([
          'success' => true,
          'message' => 'No classes today',
          'saturday_classes' => [],
          'sunday_classes' => []
        ]);
      }

      $stmt = $this->users->getDatabase()->prepare("
        SELECT s.id as subject_id, s.name, s.saturday_status, s.sunday_status
        FROM subjects s
        WHERE s.class_schedule IS NOT NULL 
          AND (
            (JSON_EXTRACT(s.class_schedule, '$.saturday') IS NOT NULL AND ? = 1) 
            OR 
            (JSON_EXTRACT(s.class_schedule, '$.sunday') IS NOT NULL AND ? = 1)
          )
      ");
      $stmt->execute([$isSaturday ? 1 : 0, $isSunday ? 1 : 0]);
      $subjects = $stmt->fetchAll(\PDO::FETCH_ASSOC);
      
      $saturdayClasses = [];
      $sundayClasses = [];
      
      foreach ($subjects as $subject) {
        if ($isSaturday && $subject['saturday_status'] !== 'Not Confirm') {
          $saturdayClasses[] = [
            'name' => $subject['name'],
            'subject_id' => $subject['subject_id'],
            'status' => $subject['saturday_status']
          ];
        }
        if ($isSunday && $subject['sunday_status'] !== 'Not Confirm') {
          $sundayClasses[] = [
            'name' => $subject['name'],
            'subject_id' => $subject['subject_id'],
            'status' => $subject['sunday_status']
          ];
        }
      }
      
      return Response::json([
        'success' => true,
        'message' => 'Today\'s classes fetched successfully',
        'saturday_classes' => $saturdayClasses,
        'sunday_classes' => $sundayClasses
      ]);
    } catch (\Exception $e) {
      return Response::json([
        'success' => false,
        'message' => 'Failed to fetch today\'s classes: ' . $e->getMessage()
      ], 500);
    }
  }

  // Legacy methods for backward compatibility
  public function index(){ 
    return $this->getSchedules();
  }

  public function create(){
    return $this->createSchedule();
  }
}

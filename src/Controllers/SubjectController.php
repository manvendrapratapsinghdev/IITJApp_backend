<?php
namespace Controllers;
use Core\Response;
use Core\Auth as AuthCore;
use Models\SubjectModel;
use Models\ScheduleModel;

class SubjectController {
  private SubjectModel $subjects;
  private ScheduleModel $schedules;
  
  public function __construct(){ 
    $this->subjects = new SubjectModel(); 
    $this->schedules = new ScheduleModel();
  }

  // GET /semester/subjects - Get list of subjects
  // Query param: semester (optional) - filter by semester
  public function getSubjects(){
    $p = AuthCore::requireUser();
    $semester = $_GET['semester'] ?? null;
    $subjects = $this->subjects->getAll($semester);
    
    return Response::json([
      'success' => true,
      'subjects' => $subjects
    ]);
  }

  // GET /semester/subjects/{subject_id} - Get detailed subject information
  public function getSubjectDetails(array $params){
    $p = AuthCore::requireUser();
    $subjectId = (int)$params['id'];
    $subject = $this->subjects->getByIdWithDetails($subjectId);
    
    if (!$subject) {
      return Response::json([
        'success' => false,
        'message' => 'Subject not found'
      ], 404);
    }
    
    return Response::json([
      'success' => true,
      'subject' => $subject
    ]);
  }

  // GET /semester/subjects/{subject_id}/notes - Get notes for a subject
  public function getSubjectNotes(array $params){
    $p = AuthCore::requireUser();
    $subjectId = (int)$params['id'];
    
    // Check if subject exists
    $subject = $this->subjects->getById($subjectId);
    if (!$subject) {
      return Response::json([
        'success' => false,
        'message' => 'Subject not found'
      ], 404);
    }
    
    $notes = $this->subjects->getNotes($subjectId);
    
    return Response::json([
      'success' => true,
      'notes' => $notes
    ]);
  }

  // DELETE /semester/subjects/{subject_id}/notes/{note_id} - Delete a note
  public function deleteNote(array $params){
    $p = AuthCore::requireUser();
    $subjectId = (int)$params['subject_id'];
    $noteId = (int)$params['note_id'];
    
    // Check if subject exists
    $subject = $this->subjects->getById($subjectId);
    if (!$subject) {
      return Response::json([
        'success' => false,
        'message' => 'Subject not found'
      ], 404);
    }
    
    // Check if note exists
    $note = $this->subjects->getNoteById($noteId);
    if (!$note) {
      return Response::json([
        'success' => false,
        'message' => 'Note not found'
      ], 404);
    }
    
    // Check if user has permission to delete this note
    // Only the note creator, faculty assigned to subject, or admins can delete
    $canDelete = false;
    if ($note['uploaded_by']['user_id'] === (int)$p['sub']) {
      $canDelete = true; // Note creator
    } elseif (in_array($p['role'], ['admin', 'super_admin'])) {
      $canDelete = true; // Admin
    } elseif ($p['role'] === 'faculty' && $subject['faculty_id'] === (int)$p['sub']) {
      $canDelete = true; // Faculty assigned to subject
    }
    
    if (!$canDelete) {
      return Response::json([
        'success' => false,
        'message' => 'You do not have permission to delete this note'
      ], 403);
    }
    
    $deleted = $this->subjects->deleteNote($noteId);
    
    if ($deleted) {
      // If note had a file, try to delete the file
      if ($note['file_url'] && strpos($note['file_url'], '/uploads/') === 0) {
        $filePath = __DIR__ . '/../../public' . $note['file_url'];
        if (file_exists($filePath)) {
          @unlink($filePath);
        }
      }
      
      return Response::json([
        'success' => true,
        'message' => 'Note deleted successfully'
      ]);
    }
    
    return Response::json([
      'success' => false,
      'message' => 'Failed to delete note'
    ], 500);
  }

  // POST /semester/subjects/{subject_id}/notes - Create note with optional file upload
  public function uploadNote(array $params){
    $p = AuthCore::requireUser();
    $subjectId = (int)$params['id'];
    
    // Check if subject exists
    $subject = $this->subjects->getById($subjectId);
    if (!$subject) {
      return Response::json([
        'success' => false,
        'message' => 'Subject not found'
      ], 404);
    }
    
    // Get input data from JSON or form data
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
    
    if (strpos($contentType, 'application/json') !== false) {
      // JSON request
      $input = Response::input();
      $title = $input['title'] ?? '';
      $description = $input['description'] ?? '';
      $link = $input['link'] ?? null;
      $hasFile = false;
    } else {
      // Form data request (with potential file upload)
      $title = $_POST['title'] ?? '';
      $description = $_POST['description'] ?? '';
      $link = $_POST['link'] ?? null;
      $hasFile = isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK;
    }
    
    // Validate required fields
    if (empty($title)) {
      return Response::json([
        'success' => false,
        'message' => 'Title is required'
      ], 422);
    }
    
    $fileUrl = null;
    $fileType = 'link';
    $fileSize = null;
    
    // Handle optional file upload
    if ($hasFile) {
      $file = $_FILES['file'];
      $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'text/plain'];
      
      if (!in_array($file['type'], $allowedTypes)) {
        return Response::json([
          'success' => false,
          'message' => 'Only PDF, DOC, DOCX, and TXT files are allowed'
        ], 422);
      }
      
      // Check file size (max 10MB)
      if ($file['size'] > 10 * 1024 * 1024) {
        return Response::json([
          'success' => false,
          'message' => 'File size must be less than 10MB'
        ], 422);
      }
      
      // Generate unique filename
      $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
      $filename = 'note_' . $subjectId . '_' . time() . '.' . $extension;
      $uploadDir = __DIR__ . '/../../uploads/notes/';
      
      // Create directory if it doesn't exist
      if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
      }
      
      $uploadPath = $uploadDir . $filename;
      
      if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
        $fileUrl = '/uploads/notes/' . $filename;
        $fileType = $extension;
        $fileSize = round($file['size'] / (1024 * 1024), 1) . ' MB';
      } else {
        return Response::json([
          'success' => false,
          'message' => 'Failed to upload file'
        ], 500);
      }
    } else {
      // No file upload - use the provided link or mark as text note
      $fileUrl = $link;
      $fileType = $link ? 'link' : 'text';
      $fileSize = '0 KB';
    }
    
    $noteData = [
      'title' => $title,
      'description' => $description,
      'file_url' => $fileUrl,
      'file_type' => $fileType,
      'file_size' => $fileSize,
      'subject_id' => $subjectId,
      'user_id' => (int)$p['sub']
    ];
    
    $noteId = $this->subjects->createNote($noteData);
    
    if ($noteId) {
      $note = $this->subjects->getNoteById($noteId);
      return Response::json([
        'success' => true,
        'message' => 'Note created successfully',
        'note' => $note
      ], 201);
    }
    
    return Response::json([
      'success' => false,
      'message' => 'Failed to create note'
    ], 500);
  }

  // GET /semester/schedules - Get schedules
  public function getSchedules(){
    $p = AuthCore::requireUser();
    $type = $_GET['type'] ?? null;
    $subject = $_GET['subject'] ?? null;
    $upcomingOnly = ($_GET['upcoming_only'] ?? 'true') === 'true';
    $limit = (int)($_GET['limit'] ?? 50);
    
    $schedules = $this->schedules->getAll($type, $subject, $upcomingOnly, $limit);
    
    return Response::json([
      'success' => true,
      'schedules' => $schedules
    ]);
  }

  // POST /semester/schedules - Create schedule (Faculty/Admin only)
  public function createSchedule(){
    $p = AuthCore::requireUser();
    
    // Check permission
    if (!in_array($p['role'], ['admin', 'super_admin', 'faculty'])) {
      return Response::json([
        'success' => false,
        'message' => 'Only faculty and admins can create schedules',
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
    
    // Validate type
    $allowedTypes = ['quiz', 'assignment', 'exam', 'class', 'event'];
    if (!in_array($in['type'], $allowedTypes)) {
      return Response::json([
        'success' => false,
        'message' => 'Invalid schedule type'
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
      'description' => $in['description'] ?? null,
      'subject_id' => (int)$in['subject_id'],
      'date' => $in['date'],
      'duration_minutes' => $in['duration_minutes'] ?? null,
      'location' => $in['location'] ?? null,
      'instructions' => $in['instructions'] ?? null,
      'submission_link' => $in['submission_link'] ?? null,
      'max_marks' => $in['max_marks'] ?? null,
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

  // Legacy methods for backward compatibility
  public function index(){ 
    return $this->getSubjects();
  }

  public function create(){
    $p = AuthCore::requireUser();
    $in = Response::input();
    if(empty($in['title'])) return Response::json(['error'=>'title required'],422);
    $id = $this->subjects->create($in['title'], $in['description'] ?? '');
    return Response::json(['id'=>$id]);
  }

  public function show(array $params){
    $p = AuthCore::requireUser();
    return $this->getSubjectDetails($params);
  }

  public function enroll(array $params){
    $p = AuthCore::requireUser();
    $ok = $this->subjects->enroll((int)$p['sub'], (int)$params['id']);
    return Response::json(['ok'=>$ok]);
  }

  public function notes(array $params){
    $p = AuthCore::requireUser();
    $notes = $this->subjects->getNotes((int)$params['id']);
    return Response::json(['notes'=>$notes]);
  }

  public function addNote(array $params){
    $p = AuthCore::requireUser();
    return $this->uploadNote($params);
  }

  // Admin CRUD Operations for Subjects
  
  // POST /admin/subjects - Create new subject (Admin only)
  public function createSubject(){
    $p = AuthCore::requireUser();
    
    // Check permission - only admins and super_admins can create subjects
    if (!in_array($p['role'], ['super_admin'])) {
      return Response::json([
        'success' => false,
        'message' => 'Only admins can create subjects',
        'statusCode' => 403
      ], 403);
    }
    
    $in = Response::input();
    
    // Validate required fields
    $requiredFields = ['code', 'name'];
    foreach ($requiredFields as $field) {
      if (empty($in[$field])) {
        return Response::json([
          'success' => false,
          'message' => "Field $field is required"
        ], 422);
      }
    }
    
    // Check if subject code already exists
    $existingSubject = $this->subjects->getByCode($in['code']);
    if ($existingSubject) {
      return Response::json([
        'success' => false,
        'message' => 'Subject with this code already exists'
      ], 422);
    }
    
    // Validate faculty_id if provided
    if (!empty($in['faculty_id'])) {
      $facultyUsers = $this->subjects->getFacultyUsers();
      $facultyIds = array_column($facultyUsers, 'id');
      if (!in_array((int)$in['faculty_id'], $facultyIds)) {
        return Response::json([
          'success' => false,
          'message' => 'Invalid faculty user'
        ], 422);
      }
    }
    
    $subjectData = [
      'code' => $in['code'],
      'name' => $in['name'],
      'order' => isset($in['order']) && $in['order'] !== '' ? (int)$in['order'] : null,
      'faculty_id' => !empty($in['faculty_id']) ? (int)$in['faculty_id'] : null,
      'credits' => (int)($in['credits'] ?? 3), // Default to 3 credits
      'semester' => $in['semester'] ?? 'General',
      'description' => $in['description'] ?? null,
      'syllabus_url' => $in['syllabus_url'] ?? null,
      'class_schedule' => !empty($in['class_schedule']) ? json_encode($in['class_schedule']) : null,
      'class_links' => !empty($in['class_links']) ? json_encode($in['class_links']) : null
    ];
    
    $subjectId = $this->subjects->createSubject($subjectData);
    
    if ($subjectId) {
      $subject = $this->subjects->getByIdWithDetails($subjectId);
      return Response::json([
        'success' => true,
        'message' => 'Subject created successfully',
        'subject' => $subject
      ], 201);
    }
    
    return Response::json([
      'success' => false,
      'message' => 'Failed to create subject'
    ], 500);
  }

  // PUT /admin/subjects/{id} - Update subject (Admin only)
  public function updateSubject(array $params){
    $p = AuthCore::requireUser();
    
    // Check permission - only admins and super_admins can update subjects
    if (!in_array($p['role'], ['admin', 'super_admin'])) {
      return Response::json([
        'success' => false,
        'message' => 'Only admins can update subjects',
        'statusCode' => 403
      ], 403);
    }
    
    $subjectId = (int)$params['id'];
    $subject = $this->subjects->getById($subjectId);
    
    if (!$subject) {
      return Response::json([
        'success' => false,
        'message' => 'Subject not found'
      ], 404);
    }
    
    $in = Response::input();
    
    // Validate required fields if provided
    if (isset($in['code']) && empty($in['code'])) {
      return Response::json([
        'success' => false,
        'message' => 'Code cannot be empty'
      ], 422);
    }
    
    if (isset($in['name']) && empty($in['name'])) {
      return Response::json([
        'success' => false,
        'message' => 'Name cannot be empty'
      ], 422);
    }
    
    // Check if new code conflicts with existing subjects
    if (!empty($in['code']) && $in['code'] !== $subject['code']) {
      $existingSubject = $this->subjects->getByCode($in['code']);
      if ($existingSubject && $existingSubject['id'] !== $subjectId) {
        return Response::json([
          'success' => false,
          'message' => 'Subject with this code already exists'
        ], 422);
      }
    }
    
    // Validate faculty_id if provided
    if (isset($in['faculty_id']) && !empty($in['faculty_id'])) {
      $facultyUsers = $this->subjects->getFacultyUsers();
      $facultyIds = array_column($facultyUsers, 'id');
      if (!in_array((int)$in['faculty_id'], $facultyIds)) {
        return Response::json([
          'success' => false,
          'message' => 'Invalid faculty user'
        ], 422);
      }
    }
    
    // Merge with existing data, including order field
    $subjectData = [
      'code' => $in['code'] ?? $subject['code'],
      'name' => $in['name'] ?? $subject['name'],
      // Use 'order' from input if provided (allow empty to set null), otherwise keep existing
      'order' => array_key_exists('order', $in)
                  ? ($in['order'] !== '' ? (int)$in['order'] : null)
                  : $subject['order'],
      'faculty_id' => isset($in['faculty_id']) ? (!empty($in['faculty_id']) ? (int)$in['faculty_id'] : null) : $subject['faculty_id'],
      'credits' => isset($in['credits']) ? (int)$in['credits'] : (int)$subject['credits'],
      'semester' => $in['semester'] ?? $subject['semester'],
      'description' => isset($in['description']) ? $in['description'] : $subject['description'],
      'syllabus_url' => isset($in['syllabus_url']) ? $in['syllabus_url'] : $subject['syllabus_url'],
      'class_schedule' => isset($in['class_schedule']) ? (!empty($in['class_schedule']) ? json_encode($in['class_schedule']) : null) : $subject['class_schedule'],
      'class_links' => isset($in['class_links']) ? (!empty($in['class_links']) ? json_encode($in['class_links']) : null) : $subject['class_links']
    ];
    
    $updated = $this->subjects->updateSubject($subjectId, $subjectData);
    
    if ($updated) {
      $subject = $this->subjects->getByIdWithDetails($subjectId);
      return Response::json([
        'success' => true,
        'message' => 'Subject updated successfully',
        'subject' => $subject
      ]);
    }
    
    return Response::json([
      'success' => false,
      'message' => 'Failed to update subject'
    ], 500);
  }

  // DELETE /admin/subjects/{id} - Delete subject (Admin only)
  public function deleteSubject(array $params){
    $p = AuthCore::requireUser();
    
    // Check permission - only admins and super_admins can delete subjects
    if (!in_array($p['role'], ['admin', 'super_admin'])) {
      return Response::json([
        'success' => false,
        'message' => 'Only admins can delete subjects',
        'statusCode' => 403
      ], 403);
    }
    
    $subjectId = (int)$params['id'];
    $subject = $this->subjects->getById($subjectId);
    
    if (!$subject) {
      return Response::json([
        'success' => false,
        'message' => 'Subject not found'
      ], 404);
    }
    
    $deleted = $this->subjects->deleteSubject($subjectId);
    
    if ($deleted) {
      return Response::json([
        'success' => true,
        'message' => 'Subject deleted successfully'
      ]);
    }
    
    return Response::json([
      'success' => false,
      'message' => 'Cannot delete subject. It may have enrolled students or other dependencies.'
    ], 422);
  }

  // GET /admin/subjects/faculty - Get faculty users for dropdown
  public function getFacultyUsers(){
    $p = AuthCore::requireUser();
    
    // Check permission - only admins and super_admins can view faculty list
    if (!in_array($p['role'], ['admin', 'super_admin'])) {
      return Response::json([
        'success' => false,
        'message' => 'Only admins can view faculty list',
        'statusCode' => 403
      ], 403);
    }
    
    $facultyUsers = $this->subjects->getFacultyUsers();
    
    return Response::json([
      'success' => true,
      'faculty_users' => $facultyUsers
    ]);
  }
}

<?php
namespace Models;
use Core\Database;
use PDO;

class SubjectModel {
  private PDO $db;
  public function __construct(){ $this->db = Database::pdo(); }

  public function getAll(?string $semester = null): array {
    $sql = "SELECT 
              s.id as subject_id,
              s.code,
              s.name,
              s.order,
              s.credits,
              s.semester,
              s.description,
              s.syllabus_url,
              s.class_schedule,
              s.class_links,
              u.id as faculty_id,
              u.name as faculty_name,
              u.email as faculty_email
            FROM subjects s
            LEFT JOIN users u ON s.faculty_id = u.id";
    
    if ($semester !== null) {
      $sql .= " WHERE s.semester = :semester";
    }
    
    $sql .= " ORDER BY 
              CASE WHEN s.order IS NULL THEN 1 ELSE 0 END, 
              s.order ASC, 
              s.name ASC";
    
    if ($semester !== null) {
      $stmt = $this->db->prepare($sql);
      $stmt->execute(['semester' => $semester]);
      $result = $stmt->fetchAll();
    } else {
      $result = $this->db->query($sql)->fetchAll();
    }
    $subjects = [];
    foreach ($result as $row) {
      $subjects[] = [
        'subject_id' => $row['subject_id'],
        'code' => $row['code'],
        'name' => $row['name'],
        'order' => isset($row['order']) ? (is_null($row['order']) ? null : (int)$row['order']) : null,
        'faculty' => $row['faculty_id'] ? [
          'user_id' => $row['faculty_id'],
          'name' => $row['faculty_name'],
          'email' => $row['faculty_email']
        ] : null,
        'credits' => (int)$row['credits'],
        'semester' => $row['semester'],
        'description' => $row['description'],
        'syllabus_url' => $row['syllabus_url'],
        'class_schedule' => $row['class_schedule'] ? json_decode($row['class_schedule'], true) : null,
      ];
    }
    return $subjects;
  }

  public function getById(int $id): ?array {
    $st = $this->db->prepare('SELECT * FROM subjects WHERE id = ?');
    $st->execute([$id]);
    $result = $st->fetch();
    if ($result && array_key_exists('order', $result)) {
      $result['order'] = is_null($result['order']) ? null : (int)$result['order'];
    }
    return $result ?: null;
  }

  public function getByIdWithDetails(int $id): ?array {
    $sql = "SELECT 
              s.id as subject_id,
              s.code,
              s.name,
              s.order,
              s.credits,
              s.semester,
              s.description,
              s.syllabus_url,
              s.class_schedule,
              s.class_links,
              s.created_at,
              u.id as faculty_id,
              u.name as faculty_name,
              u.email as faculty_email
            FROM subjects s
            LEFT JOIN users u ON s.faculty_id = u.id
            WHERE s.id = ?";
    $st = $this->db->prepare($sql);
    $st->execute([$id]);
    $subject = $st->fetch();
    if (!$subject) {
      return null;
    }
    // Get notes for this subject
    $notes = $this->getNotes($id);
    // Get upcoming schedules for this subject
    $schedulesSql = "SELECT 
                      sc.id as schedule_id,
                      sc.type,
                      sc.title,
                      sc.date
                    FROM schedules sc
                    WHERE sc.subject_id = ? AND sc.date >= NOW()
                    ORDER BY sc.date ASC
                    LIMIT 5";
    $schedulesStmt = $this->db->prepare($schedulesSql);
    $schedulesStmt->execute([$id]);
    $upcomingSchedules = $schedulesStmt->fetchAll();
    return [
      'subject_id' => $subject['subject_id'],
      'code' => $subject['code'],
      'name' => $subject['name'],
      'order' => isset($subject['order']) ? (is_null($subject['order']) ? null : (int)$subject['order']) : null,
      'faculty' => $subject['faculty_id'] ? [
        'user_id' => $subject['faculty_id'],
        'name' => $subject['faculty_name'],
        'email' => $subject['faculty_email']
      ] : null,
      'credits' => (int)$subject['credits'],
      'semester' => $subject['semester'],
      'description' => $subject['description'],
      'syllabus_url' => $subject['syllabus_url'],
      'class_schedule' => $subject['class_schedule'] ? json_decode($subject['class_schedule'], true) : null,
      'class_links' => $subject['class_links'] ? json_decode($subject['class_links'], true) : [],
      'notes' => $notes,
      'upcoming_schedules' => $upcomingSchedules,
      'created_at' => $subject['created_at']
    ];
  }

  public function createNote(array $data): ?int {
    $sql = 'INSERT INTO notes (title, description, file_url, file_type, file_size, user_id, subject_id) VALUES (?, ?, ?, ?, ?, ?, ?)';
    $st = $this->db->prepare($sql);
    
    if ($st->execute([
      $data['title'],
      $data['description'],
      $data['file_url'],
      $data['file_type'],
      $data['file_size'],
      $data['user_id'],
      $data['subject_id']
    ])) {
      $noteId = (int)$this->db->lastInsertId();
      
      // Send push notification for new notes
      $this->sendNotesNotification($noteId, $data);
      
      return $noteId;
    }
    
    return null;
  }
  
  private function sendNotesNotification(int $noteId, array $noteData): void {
    try {
      $firebaseService = new \Services\FirebaseService();
      $userModel = new \Models\UserModel();
      
      // Get uploader information
      $uploader = $userModel->findById($noteData['user_id']);
      if (!$uploader) {
        return;
      }
      
      // Get subject information
      $subject = $this->getById($noteData['subject_id']);
      if (!$subject) {
        return;
      }
      
      // Prepare note data for notification
      $note = [
        'id' => $noteId,
        'title' => $noteData['title'],
        'description' => $noteData['description'],
        'file_url' => $noteData['file_url'],
        'file_type' => $noteData['file_type']
      ];
      
      $uploaderInfo = [
        'user_id' => $uploader['id'],
        'name' => $uploader['name'],
        'role' => $uploader['role']
      ];
      
      $subjectInfo = [
        'id' => $subject['id'], // Fixed: use 'id' instead of 'subject_id'
        'name' => $subject['name'],
        'code' => $subject['code']
      ];
      
      // Send the notification
      $firebaseService->notifyNewNotes($note, $uploaderInfo, $subjectInfo);
    } catch (\Exception $e) {
      // Log error but don't fail the note creation
      error_log("Failed to send notes notification: " . $e->getMessage());
    }
  }

  public function getNoteById(int $id): ?array {
    $sql = "SELECT 
              n.id as note_id,
              n.title,
              n.description,
              n.file_url,
              n.file_type,
              n.file_size,
              n.created_at,
              u.id as user_id,
              u.name as user_name,
              u.role as user_role,
              s.id as subject_id,
              s.code as subject_code,
              s.name as subject_name
            FROM notes n
            JOIN users u ON n.user_id = u.id
            JOIN subjects s ON n.subject_id = s.id
            WHERE n.id = ?";
    
    $st = $this->db->prepare($sql);
    $st->execute([$id]);
    $note = $st->fetch();
    
    if (!$note) {
      return null;
    }
    
    return [
      'note_id' => $note['note_id'],
      'title' => $note['title'],
      'description' => $note['description'],
      'file_url' => $note['file_url'],
      'file_type' => $note['file_type'],
      'file_size' => $note['file_size'],
      'subject' => [
        'subject_id' => $note['subject_id'],
        'code' => $note['subject_code'],
        'name' => $note['subject_name']
      ],
      'uploaded_by' => [
        'user_id' => $note['user_id'],
        'name' => $note['user_name'],
        'role' => $note['user_role']
      ],
      'uploaded_at' => $note['created_at']
    ];
  }

  public function getNotes(int $subjectId): array {
    $sql = "SELECT 
              n.id as note_id,
              n.title,
              n.file_url,
              n.file_type,
              n.file_size,
              n.created_at,
              u.id as user_id,
              u.name as user_name,
              u.role as user_role
            FROM notes n
            JOIN users u ON n.user_id = u.id
            WHERE n.subject_id = ?
            ORDER BY n.created_at DESC";
    
    $st = $this->db->prepare($sql);
    $st->execute([$subjectId]);
    $notesData = $st->fetchAll();
    
    $notes = [];
    foreach ($notesData as $note) {
      $notes[] = [
        'note_id' => $note['note_id'],
        'id' => $note['note_id'], // Add id for compatibility
        'title' => $note['title'],
        'file_url' => $note['file_url'],
        'url' => $note['file_url'], // Add url for compatibility
        'file_type' => $note['file_type'],
        'file_size' => $note['file_size'],
        'uploaded_by' => [
          'user_id' => $note['user_id'],
          'name' => $note['user_name'],
          'role' => $note['user_role']
        ],
        'uploaded_at' => $note['created_at'],
        'created_at' => $note['created_at'] // Add created_at for compatibility
      ];
    }
    
    return $notes;
  }

  public function deleteNote(int $noteId): bool {
    $st = $this->db->prepare('DELETE FROM notes WHERE id = ?');
    return $st->execute([$noteId]);
  }

  // Legacy methods for backward compatibility
  public function all(): array { 
    $result = $this->getAll();
    return $result;
  }

  public function create(string $title,string $desc=''): int {
    $st=$this->db->prepare('INSERT INTO subjects (name,description) VALUES (?,?)');
    $st->execute([$title,$desc]); return (int)$this->db->lastInsertId();
  }

  public function find(int $id): ?array { 
    return $this->getById($id);
  }

  public function enroll(int $uid,int $sid): bool {
    $st=$this->db->prepare('INSERT IGNORE INTO enrollments (user_id,subject_id) VALUES (?,?)');
    return $st->execute([$uid,$sid]);
  }

  public function notes(int $sid): array {
    return $this->getNotes($sid);
  }

  public function addNote(int $uid,int $sid,string $content): int {
    // Legacy method - convert content to file-based note
    $noteData = [
      'title' => 'Legacy Note',
      'description' => $content,
      'file_url' => '',
      'file_type' => 'text',
      'file_size' => '0 KB',
      'user_id' => $uid,
      'subject_id' => $sid
    ];
    
    return $this->createNote($noteData) ?? 0;
  }

  // Admin CRUD Operations
  public function createSubject(array $data): ?int {
    $sql = 'INSERT INTO subjects (code, name, `order`, faculty_id, credits, semester, description, syllabus_url, class_schedule, class_links) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    $st = $this->db->prepare($sql);
    if ($st->execute([
      $data['code'],
      $data['name'],
      isset($data['order']) ? $data['order'] : null,
      $data['faculty_id'],
      $data['credits'],
      $data['semester'],
      $data['description'],
      $data['syllabus_url'],
      $data['class_schedule'],
      $data['class_links']
    ])) {
      return (int)$this->db->lastInsertId();
    }
    return null;
  }

  public function updateSubject(int $id, array $data): bool {
    $sql = 'UPDATE subjects SET code = ?, name = ?, `order` = ?, faculty_id = ?, credits = ?, semester = ?, description = ?, syllabus_url = ?, class_schedule = ?, class_links = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?';
    $st = $this->db->prepare($sql);
    return $st->execute([
      $data['code'],
      $data['name'],
      isset($data['order']) ? $data['order'] : null,
      $data['faculty_id'],
      $data['credits'],
      $data['semester'],
      $data['description'],
      $data['syllabus_url'],
      $data['class_schedule'],
      $data['class_links'],
      $id
    ]);
  }

  public function deleteSubject(int $id): bool {
    // Check if subject has enrollments
    $enrollmentCheck = $this->db->prepare('SELECT COUNT(*) FROM enrollments WHERE subject_id = ?');
    $enrollmentCheck->execute([$id]);
    $enrollmentCount = $enrollmentCheck->fetchColumn();
    
    if ($enrollmentCount > 0) {
      return false; // Cannot delete subject with enrollments
    }
    
    $st = $this->db->prepare('DELETE FROM subjects WHERE id = ?');
    return $st->execute([$id]);
  }

  public function getByCode(string $code): ?array {
    $st = $this->db->prepare('SELECT * FROM subjects WHERE code = ?');
    $st->execute([$code]);
    $result = $st->fetch();
    return $result ?: null;
  }

  public function getFacultyUsers(): array {
    $sql = "SELECT id, name, email FROM users WHERE role IN ('faculty', 'admin', 'super_admin') ORDER BY name";
    return $this->db->query($sql)->fetchAll();
  }
}

<?php
namespace Models;
use Core\Database;
use PDO;

class ScheduleModel {
  private PDO $db;
  public function __construct(){ $this->db = Database::pdo(); }

  public function getAll(?string $type = null, ?string $subject = null, bool $upcomingOnly = true, int $limit = 50): array {
    $whereClauses = [];
    $params = [];
    
    if ($type) {
      $whereClauses[] = 'sc.type = ?';
      $params[] = $type;
    }
    
    if ($subject) {
      $whereClauses[] = 's.code = ?';
      $params[] = $subject;
    }
    
    if ($upcomingOnly) {
      $whereClauses[] = 'sc.date >= NOW()';
    }
    
    $whereClause = empty($whereClauses) ? '' : 'WHERE ' . implode(' AND ', $whereClauses);
    
    $sql = "SELECT 
              sc.id as schedule_id,
              sc.type,
              sc.title,
              sc.description,
              sc.date,
              s.id as subject_id,
              s.name as subject_name
            FROM schedules sc
            LEFT JOIN subjects s ON sc.subject_id = s.id
            LEFT JOIN users f ON s.faculty_id = f.id
            JOIN users u ON sc.created_by = u.id
            $whereClause
            ORDER BY sc.date ASC
            LIMIT ?";
    
    $st = $this->db->prepare($sql);
    
    // Bind the dynamic parameters first
    for ($i = 0; $i < count($params); $i++) {
      $st->bindValue($i + 1, $params[$i]);
    }
    
    // Bind the limit parameter as an integer
    $st->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
    
    $st->execute();
    $schedules = $st->fetchAll();
    
    $result = [];
    foreach ($schedules as $schedule) {
      $result[] = [
        'schedule_id' => $schedule['schedule_id'],
        'type' => $schedule['type'],
        'title' => $schedule['title'],
        'description' => $schedule['description'],
        'subject' => $schedule['subject_id'] ? [
          'id' => $schedule['subject_id'],
          'name' => $schedule['subject_name'],
        ] : null,
        'date' => $schedule['date'],
      ];
    }
    
    return $result;
  }

  public function create(array $data): ?int {
    $sql = 'INSERT INTO schedules (type, title, description, subject_id, date, duration_minutes, location, instructions, submission_link, max_marks, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';
    
    $st = $this->db->prepare($sql);
    
    if ($st->execute([
      $data['type'],
      $data['title'],
      $data['description'],
      $data['subject_id'],
      $data['date'],
      $data['duration_minutes'],
      $data['location'],
      $data['instructions'],
      $data['submission_link'],
      $data['max_marks'],
      $data['created_by']
    ])) {
      return (int)$this->db->lastInsertId();
    }
    
    return null;
  }

  public function getById(int $id): ?array {
    $sql = "SELECT 
              sc.id as schedule_id,
              sc.type,
              sc.title,
              sc.description,
              sc.date,
              sc.duration_minutes,
              sc.location,
              sc.instructions,
              sc.submission_link,
              sc.max_marks,
              sc.created_at,
              s.id as subject_id,
              s.code as subject_code,
              s.name as subject_name,
              u.id as created_by_id,
              u.name as created_by_name,
              u.role as created_by_role
            FROM schedules sc
            LEFT JOIN subjects s ON sc.subject_id = s.id
            JOIN users u ON sc.created_by = u.id
            WHERE sc.id = ?";
    
    $st = $this->db->prepare($sql);
    $st->execute([$id]);
    $schedule = $st->fetch();
    
    if (!$schedule) {
      return null;
    }
    
    return [
      'schedule_id' => $schedule['schedule_id'],
      'type' => $schedule['type'],
      'title' => $schedule['title'],
      'description' => $schedule['description'],
      'subject' => $schedule['subject_id'] ? [
        'subject_id' => $schedule['subject_id'],
        'code' => $schedule['subject_code'],
        'name' => $schedule['subject_name']
      ] : null,
      'date' => $schedule['date'],
      'duration_minutes' => $schedule['duration_minutes'] ? (int)$schedule['duration_minutes'] : null,
      'location' => $schedule['location'],
      'instructions' => $schedule['instructions'],
      'submission_link' => $schedule['submission_link'],
      'max_marks' => $schedule['max_marks'] ? (int)$schedule['max_marks'] : null,
      'created_by' => [
        'user_id' => $schedule['created_by_id'],
        'name' => $schedule['created_by_name'],
        'role' => $schedule['created_by_role']
      ],
      'created_at' => $schedule['created_at']
    ];
  }

  public function update(int $id, array $data): bool {
    $sql = 'UPDATE schedules SET type = ?, title = ?, description = ?, subject_id = ?, date = ?, duration_minutes = ?, location = ?, instructions = ?, submission_link = ?, max_marks = ? WHERE id = ?';
    
    $st = $this->db->prepare($sql);
    
    return $st->execute([
      $data['type'],
      $data['title'],
      $data['description'],
      $data['subject_id'],
      $data['date'],
      $data['duration_minutes'],
      $data['location'],
      $data['instructions'],
      $data['submission_link'],
      $data['max_marks'],
      $id
    ]);
  }

  public function delete(int $id): bool {
    $st = $this->db->prepare('DELETE FROM schedules WHERE id = ?');
    return $st->execute([$id]);
  }

  // Legacy methods for backward compatibility
  public function allForUser(int $uid): array { 
    $st=$this->db->prepare('SELECT * FROM schedules WHERE created_by=? ORDER BY date'); 
    $st->execute([$uid]); 
    return $st->fetchAll(); 
  }
}

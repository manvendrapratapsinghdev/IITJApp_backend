<?php
namespace Models;
use Core\Database;
use PDO;

class UserModel {
  /**
   * Find user by BOTH email and Google ID
   * @param string $email
   * @param string $googleId
   * @return array|null
   */
  public function findByEmailAndGoogleId(string $email, string $googleId): ?array {
    $st = $this->db->prepare('SELECT * FROM users WHERE email=? AND google_id=? LIMIT 1');
    $st->execute([$email, $googleId]);
    $r = $st->fetch();
    return $r ?: null;
  }
  private PDO $db;
  public function __construct(){ $this->db = Database::pdo(); }
  public function create($name,$email,$phone,$hash,$role='user'): int {
    $st=$this->db->prepare('INSERT INTO users (name,email,phone,password_hash,role,is_onboarding_done) VALUES (?,?,?,?,?,?)');
    $st->execute([$name,$email,$phone,$hash,$role,0]);
    return (int)$this->db->lastInsertId();
  }
  public function findByEmail($email): ?array {
    $st=$this->db->prepare('SELECT * FROM users WHERE email=? LIMIT 1');
    $st->execute([$email]); $r=$st->fetch(); return $r?:null;
  }
  public function findById(int $id): ?array {
    $st=$this->db->prepare('SELECT id,name,email,phone,role,google_id,apple_user_id,email_verified,is_onboarding_done,bio,links,whatsapp,age,company,expertise,interests,experience,linkedin_url,github_url,admin_request,admin_status,is_deleted,is_blocked,deleted_at,blocked_at,device_token,created_at, auth_token FROM users WHERE id=?');
    $st->execute([$id]); $r=$st->fetch(); return $r?:null;
  }

  /**
   * Find user by ID including password hash - Use only for password verification
   * @param int $id User ID
   * @return array|null User data including password hash or null if not found
   */
  public function findByIdWithPassword(int $id): ?array {
    $st=$this->db->prepare('SELECT id,name,email,phone,role,google_id,apple_user_id,email_verified,is_onboarding_done,password_hash,bio,links,whatsapp,age,company,expertise,interests,experience,linkedin_url,github_url,admin_request,admin_status,is_deleted,is_blocked,deleted_at,blocked_at,device_token,created_at FROM users WHERE id=?');
    $st->execute([$id]); $r=$st->fetch(); return $r?:null;
  }
  public function updateBasic(int $id,string $name,string $phone): bool {
    $st=$this->db->prepare('UPDATE users SET name=?, phone=? WHERE id=?');
    return $st->execute([$name,$phone,$id]);
  }
  public function updateProfessional(int $id, ?string $bio, ?string $linksJson): bool {
    $st=$this->db->prepare('UPDATE users SET bio=?, links=? WHERE id=?');
    return $st->execute([$bio,$linksJson,$id]);
  }

  public function createWithGoogle($name,$email,$hash,$googleId,$role='user'): int {
    $st=$this->db->prepare('INSERT INTO users (name,email,phone,password_hash,google_id,role,is_onboarding_done) VALUES (?,?,?,?,?,?,?)');
    $st->execute([$name,$email,'',$hash,$googleId,$role,0]);
    return (int)$this->db->lastInsertId();
  }

  public function findByGoogleId(string $googleId): ?array {
    $st=$this->db->prepare('SELECT * FROM users WHERE google_id=? LIMIT 1');
    $st->execute([$googleId]); 
    $r=$st->fetch(); 
    return $r?:null;
  }

  public function updateGoogleId(int $id, string $googleId): bool {
    $st=$this->db->prepare('UPDATE users SET google_id=? WHERE id=?');
    return $st->execute([$googleId,$id]);
  }

  public function completeOnboarding(int $id): bool {
    $st=$this->db->prepare('UPDATE users SET is_onboarding_done=? WHERE id=?');
    return $st->execute([1,$id]);
  }

  public function resetOnboarding(int $id): bool {
    $st=$this->db->prepare('UPDATE users SET is_onboarding_done=? WHERE id=?');
    return $st->execute([0,$id]);
  }

  public function completeOnboardingWithData(int $id, array $data): bool {
    $sql = 'UPDATE users SET 
      name=?, phone=?, whatsapp=?, age=?, company=?, expertise=?, 
      interests=?, experience=?, linkedin_url=?, github_url=?, admin_request=?, admin_status=?,
      is_onboarding_done=1 
      WHERE id=?';
    
    $adminStatus = isset($data['admin_request']) && $data['admin_request'] ? 'pending' : null;
    $adminRequest = isset($data['admin_request']) && $data['admin_request'] ? 1 : 0;
    
    // Handle expertise field - convert array to comma-separated string if needed
    $expertise = $data['expertise'] ?? '';
    if (is_array($expertise)) {
      $expertise = implode(',', $expertise);
    }
    
    // Handle interests field - convert array to comma-separated string if needed (same as expertise)
    $interests = $data['interests'] ?? '';
    if (is_array($interests)) {
      $interests = implode(',', $interests);
    }
    
    $st=$this->db->prepare($sql);
    // Format date properly if provided
    $dob = !empty($data['age']) ? date_create_from_format('Y-m-d', $data['age'])->format('Y-m-d') : null;

    return $st->execute([
      $data['name'],
      $data['phone'],
      $data['whatsapp'],
      $dob,
      $data['company'],
      $expertise,
      $interests,
      $data['experience'],
      $data['linkedin_url'] ?? null,
      $data['github_url'] ?? null,
      $adminRequest,
      $adminStatus,
      $id
    ]);
  }

  // Token management methods
  public function saveToken(int $id, string $token): bool {
    $st=$this->db->prepare('UPDATE users SET auth_token=? WHERE id=?');
    return $st->execute([$token, $id]);
  }

  public function findByToken(string $token): ?array {
    $st=$this->db->prepare('SELECT * FROM users WHERE auth_token=? AND (token_expires_at IS NULL OR token_expires_at > NOW()) LIMIT 1');
    $st->execute([$token]); 
    $r=$st->fetch(); 
    return $r?:null;
  }

  public function clearToken(int $id): bool {
    $st=$this->db->prepare('UPDATE users SET auth_token=NULL, token_expires_at=NULL WHERE id=?');
    return $st->execute([$id]);
  }

  public function clearExpiredTokens(): bool {
    $st=$this->db->prepare('UPDATE users SET auth_token=NULL, token_expires_at=NULL WHERE token_expires_at IS NOT NULL AND token_expires_at <= NOW()');
    return $st->execute();
  }

  // Profile management methods
  public function updateProfile(int $id, array $data): bool {
    $setParts = [];
    $params = [];
    
    foreach ($data as $field => $value) {
      $setParts[] = "$field = ?";
      $params[] = $value;
    }
    
    if (empty($setParts)) return false;
    
    $params[] = $id;
    $sql = 'UPDATE users SET ' . implode(', ', $setParts) . ', updated_at = CURRENT_TIMESTAMP WHERE id = ?';
    
    $st = $this->db->prepare($sql);
    return $st->execute($params);
  }

  public function updateProfilePicture(int $id, string $profilePictureUrl): bool {
    $st = $this->db->prepare('UPDATE users SET profile_picture = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    return $st->execute([$profilePictureUrl, $id]);
  }

  // Admin request methods
  public function createAdminRequest(int $userId): bool {
    $st = $this->db->prepare('UPDATE users SET admin_request = 1, admin_status = "pending" WHERE id = ?');
    return $st->execute([$userId]);
  }

  public function getAdminRequest(int $userId): ?array {
    $st = $this->db->prepare('SELECT id as user_id, admin_request, admin_status, created_at as requested_at FROM users WHERE id = ? AND admin_request = 1');
    $st->execute([$userId]);
    $result = $st->fetch();
    return $result ?: null;
  }

    public function getAllAdminRequests($status = null, $page = 1, $limit = 20) {
        $offset = ($page - 1) * $limit;
        
        $sql = "SELECT id, name, email, phone, company, expertise, experience,
                       admin_request, admin_status, created_at
                FROM users
                WHERE admin_request = 1";
        
        $params = [];
        
        if ($status) {
            $sql .= " AND admin_status = ?";
            $params[] = $status;
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT " . (int)$offset . ", " . (int)$limit;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

  public function updateAdminRequest(int $userId, string $action, int $actionTakenBy, ?string $notes = null): bool {
    // Update admin status and promote to admin if approved
    if ($action === 'approve') {
      $st = $this->db->prepare('UPDATE users SET admin_status = "approved", role = "admin" WHERE id = ? AND admin_request = 1');
      return $st->execute([$userId]);
    } else if ($action === 'reject') {
      $st = $this->db->prepare('UPDATE users SET admin_status = "rejected" WHERE id = ? AND admin_request = 1');
      return $st->execute([$userId]);
    }
    return false;
  }

  public function promoteToAdmin(int $userId): bool {
    $st = $this->db->prepare('UPDATE users SET role = "admin", updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    return $st->execute([$userId]);
  }

  // Network methods - Enhanced to exclude deleted/blocked users and specific user
  public function getAllUsers(int $page = 1, int $limit = 20, array $filters = []): array {
    $offset = ($page - 1) * $limit;
    
    // Handle sorting
    $sort = $filters['sort'] ?? 'name';
    $order = strtoupper($filters['order'] ?? 'ASC');
    
    // Validate sort field to prevent SQL injection
    $allowedSorts = ['created_at', 'name', 'experience', 'company', 'role'];
    if (!in_array($sort, $allowedSorts)) {
      $sort = 'name';
    }
    
    // Validate order
    if (!in_array($order, ['ASC', 'DESC'])) {
      $order = 'ASC';
    }
    
    // Handle special sorting for experience (numeric)
    $orderClause = ($sort === 'experience') 
      ? "ORDER BY CAST(experience AS UNSIGNED) $order, name ASC"
      : "ORDER BY $sort $order, name ASC";
    
    // Hide super_admin users from non-super_admin viewers
    if (!empty($filters['viewer_role']) && $filters['viewer_role'] !== 'super_admin') {
      $filters['exclude_roles'] = ['super_admin'];
    }
    
    $whereClauses = [];
    $params = [];
    
    // Use centralized role filtering
    $this->buildRoleFiltering($filters, $whereClauses, $params);
    
    if (!empty($filters['search'])) {
      $whereClauses[] = '(name LIKE ? OR company LIKE ? OR expertise LIKE ? OR email LIKE ?)';
      $searchTerm = '%' . $filters['search'] . '%';
      $params[] = $searchTerm;
      $params[] = $searchTerm;
      $params[] = $searchTerm;
      $params[] = $searchTerm;
    }
    
    if (!empty($filters['company'])) {
      $whereClauses[] = 'company = ?';
      $params[] = $filters['company'];
    }
    
    if (!empty($filters['expertise'])) {
      $whereClauses[] = 'expertise LIKE ?';
      $params[] = '%' . $filters['expertise'] . '%';
    }
    
    if (!empty($filters['experience_min'])) {
      $whereClauses[] = 'CAST(experience AS UNSIGNED) >= ?';
      $params[] = $filters['experience_min'];
    }
    
    if (!empty($filters['experience_max'])) {
      $whereClauses[] = 'CAST(experience AS UNSIGNED) <= ?';
      $params[] = $filters['experience_max'];
    }
    
    if (!empty($filters['role'])) {
      if (is_array($filters['role'])) {
        // Multiple roles provided
        $rolePlaceholders = implode(',', array_fill(0, count($filters['role']), '?'));
        $whereClauses[] = "role IN ($rolePlaceholders)";
        $params = array_merge($params, $filters['role']);
      } else {
        // Single role provided (backward compatibility)
        $whereClauses[] = 'role = ?';
        $params[] = $filters['role'];
      }
    }
    
    $whereClause = implode(' AND ', $whereClauses);
    $sql = "SELECT id, name, email, phone, company, expertise, interests, experience, age, linkedin_url, github_url, whatsapp, profile_picture, bio, role, is_blocked, is_deleted, blocked_at, deleted_at, created_at
            FROM users 
            WHERE $whereClause 
            $orderClause 
            LIMIT " . (int)$offset . ", " . (int)$limit;
    
    $st = $this->db->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
  }

  public function getNetworkFilters(): array {
    // Get unique companies
    $companiesStmt = $this->db->query('SELECT company, COUNT(*) as count FROM users WHERE company IS NOT NULL AND company != "" AND is_onboarding_done = 1 GROUP BY company ORDER BY count DESC');
    $companies = $companiesStmt->fetchAll();
    
    // Get unique expertises (split by comma and count)
    $expertiseStmt = $this->db->query('SELECT expertise FROM users WHERE expertise IS NOT NULL AND expertise != "" AND is_onboarding_done = 1');
    $expertiseData = $expertiseStmt->fetchAll();
    $expertiseCount = [];
    
    foreach ($expertiseData as $row) {
      $expertises = array_map('trim', explode(',', $row['expertise']));
      foreach ($expertises as $expertise) {
        if (!empty($expertise)) {
          $expertiseCount[$expertise] = ($expertiseCount[$expertise] ?? 0) + 1;
        }
      }
    }
    
    arsort($expertiseCount);
    $expertises = [];
    foreach ($expertiseCount as $name => $count) {
      $expertises[] = ['name' => $name, 'count' => $count];
    }
    
    // Get experience stats
    $experienceStmt = $this->db->query('SELECT experience FROM users WHERE experience IS NOT NULL AND experience != "" AND is_onboarding_done = 1');
    $experienceData = $experienceStmt->fetchAll();
    $experienceValues = array_map(function($row) {
      return (int)filter_var($row['experience'], FILTER_SANITIZE_NUMBER_INT);
    }, $experienceData);
    
    // Filter out zero values and check if array is not empty
    $experienceValues = array_filter($experienceValues, function($val) { return $val > 0; });
    
    $experienceStats = !empty($experienceValues) ? [
      'min' => min($experienceValues),
      'max' => max($experienceValues),
      'average' => round(array_sum($experienceValues) / count($experienceValues), 1)
    ] : [
      'min' => 0,
      'max' => 0,
      'average' => 0
    ];
    
    // Get age stats
    $ageStmt = $this->db->query('SELECT age FROM users WHERE age IS NOT NULL AND is_onboarding_done = 1');
    $ageData = $ageStmt->fetchAll();
    $ageValues = array_map(function($row) { return (int)$row['age']; }, $ageData);
    
    // Filter out zero values and check if array is not empty
    $ageValues = array_filter($ageValues, function($val) { return $val > 0; });
    
    $ageStats = !empty($ageValues) ? [
      'min' => min($ageValues),
      'max' => max($ageValues),
      'average' => round(array_sum($ageValues) / count($ageValues), 1)
    ] : [
      'min' => 0,
      'max' => 0,
      'average' => 0
    ];
    
    return [
      'companies' => $companies,
      'expertises' => $expertises,
      'experience_stats' => $experienceStats,
      'age_stats' => $ageStats
    ];
  }

  public function getAdminList(): array {
    $st = $this->db->prepare('SELECT id, name, email, role, company, expertise, profile_picture, created_at FROM users WHERE role IN ("admin") ORDER BY role, name');
    $st->execute();
    return $st->fetchAll();
  }

  public function updateLastActive(int $userId): bool {
    $st = $this->db->prepare('UPDATE users SET last_active = CURRENT_TIMESTAMP WHERE id = ?');
    return $st->execute([$userId]);
  }

  // User deletion methods
  public function deleteUser(int $userId, int $deletedBy, ?string $reason = null): bool {
    try {
      $this->db->beginTransaction();
      
      // 1. Delete user's posts
      $st = $this->db->prepare('DELETE FROM posts WHERE user_id = ?');
      $st->execute([$userId]);
      
      // 2. Delete user's activities/connections
      $st = $this->db->prepare('DELETE FROM connections WHERE user_id = ? OR other_user_id = ?');
      $st->execute([$userId, $userId]);
      
      // 3. Clear onboarding data but keep basic account info
      $sql = 'UPDATE users SET 
        is_deleted = 1,
        deleted_at = CURRENT_TIMESTAMP,
        deleted_by = ?,
        delete_reason = ?,
        auth_token = NULL,
        token_expires_at = NULL,
        device_token = NULL,
        is_onboarding_done = 0,
        whatsapp = NULL,
        age = NULL,
        company = NULL,
        expertise = NULL,
        interests = NULL,
        experience = NULL,
        linkedin_url = NULL,
        github_url = NULL,
        bio = NULL,
        links = NULL,
        profile_picture = NULL,
        admin_request = 0,
        admin_status = NULL
        WHERE id = ? AND is_deleted = 0';
      
      $st = $this->db->prepare($sql);
      $result = $st->execute([$deletedBy, $reason, $userId]);
      
      $this->db->commit();
      return $result;
    } catch (\Exception $e) {
      $this->db->rollBack();
      return false;
    }
  }

  public function restoreUser(int $userId): bool {
    $sql = 'UPDATE users SET 
      is_deleted = 0, 
      deleted_at = NULL, 
      deleted_by = NULL, 
      delete_reason = NULL
      WHERE id = ? AND is_deleted = 1';
    
    $st = $this->db->prepare($sql);
    return $st->execute([$userId]);
  }

  // User blocking methods
  public function blockUser(int $userId, int $blockedBy, ?string $reason = null): bool {
    $sql = 'UPDATE users SET 
      is_blocked = 1, 
      blocked_at = CURRENT_TIMESTAMP, 
      blocked_by = ?, 
      block_reason = ?,
      auth_token = NULL,
      token_expires_at = NULL,
      device_token = NULL
      WHERE id = ? AND is_blocked = 0';
    
    $st = $this->db->prepare($sql);
    return $st->execute([$blockedBy, $reason, $userId]);
  }

  public function unblockUser(int $userId): bool {
    $sql = 'UPDATE users SET 
      is_blocked = 0, 
      blocked_at = NULL, 
      blocked_by = NULL, 
      block_reason = NULL
      WHERE id = ? AND is_blocked = 1';
    
    $st = $this->db->prepare($sql);
    return $st->execute([$userId]);
  }

  public function getTotalUsersCount(array $filters = []): int {
    $whereClauses = [];
    $params = [];
    
    // Use centralized role filtering
    $this->buildRoleFiltering($filters, $whereClauses, $params);
    
    if (!empty($filters['search'])) {
      $whereClauses[] = '(name LIKE ? OR company LIKE ? OR expertise LIKE ? OR email LIKE ?)';
      $searchTerm = '%' . $filters['search'] . '%';
      $params[] = $searchTerm;
      $params[] = $searchTerm;
      $params[] = $searchTerm;
      $params[] = $searchTerm;
    }
    
    if (!empty($filters['company'])) {
      $whereClauses[] = 'company = ?';
      $params[] = $filters['company'];
    }
    
    if (!empty($filters['expertise'])) {
      $whereClauses[] = 'expertise LIKE ?';
      $params[] = '%' . $filters['expertise'] . '%';
    }
    
    if (!empty($filters['experience_min'])) {
      $whereClauses[] = 'CAST(experience AS UNSIGNED) >= ?';
      $params[] = $filters['experience_min'];
    }
    
    if (!empty($filters['experience_max'])) {
      $whereClauses[] = 'CAST(experience AS UNSIGNED) <= ?';
      $params[] = $filters['experience_max'];
    }
    
    if (!empty($filters['role'])) {
      if (is_array($filters['role'])) {
        // Multiple roles provided
        $rolePlaceholders = implode(',', array_fill(0, count($filters['role']), '?'));
        $whereClauses[] = "role IN ($rolePlaceholders)";
        $params = array_merge($params, $filters['role']);
      } else {
        // Single role provided (backward compatibility)
        $whereClauses[] = 'role = ?';
        $params[] = $filters['role'];
      }
    }
    
    $whereClause = implode(' AND ', $whereClauses);
    $sql = "SELECT COUNT(*) as total FROM users WHERE $whereClause";
    
    $st = $this->db->prepare($sql);
    $st->execute($params);
    $result = $st->fetch();
    return (int)$result['total'];
  }

  public function getPublicProfile(int $userId): ?array {
    $st = $this->db->prepare('SELECT id, name, email, company, expertise, interests, experience, age, linkedin_url, github_url, whatsapp, profile_picture, bio, role, created_at, last_active FROM users WHERE id = ? AND is_onboarding_done = 1');
    $st->execute([$userId]);
    $user = $st->fetch();
    
    if (!$user) {
      return null;
    }
    
    // Get posts count
    $postsStmt = $this->db->prepare('SELECT COUNT(*) as count FROM posts WHERE user_id = ?');
    $postsStmt->execute([$userId]);
    $postsCount = $postsStmt->fetch()['count'];
    
    // Get connections count
    $connectionsStmt = $this->db->prepare('SELECT COUNT(*) as count FROM connections WHERE (user_id = ? OR other_user_id = ?) AND status = "accepted"');
    $connectionsStmt->execute([$userId, $userId]);
    $connectionsCount = $connectionsStmt->fetch()['count'];
    
    return [
      'user_id' => $user['id'],
      'name' => $user['name'],
      'email' => $user['email'],
      'company' => $user['company'],
      'expertise' => $user['expertise'],
      'interests' => $user['interests'],
      'experience' => $user['experience'],
      'age' => $user['age'],
      'linkedin_url' => $user['linkedin_url'],
      'github_url' => $user['github_url'],
      'whatsapp' => $user['whatsapp'],
      'profile_picture' => $user['profile_picture'],
      'bio' => $user['bio'],
      'role' => $user['role'],
      'created_at' => $user['created_at'],
      'last_active' => $user['last_active'],
      'posts_count' => (int)$postsCount,
      'connections_count' => (int)$connectionsCount
    ];
  }

  public function searchUsers(string $query, array $filters = [], string $sort = 'name', string $order = 'asc'): array {
    $whereClauses = ['is_onboarding_done = 1'];
    $params = [];
    
    // Add search query
    $whereClauses[] = '(name LIKE ? OR company LIKE ? OR expertise LIKE ? OR bio LIKE ?)';
    $searchTerm = '%' . $query . '%';
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    
    // Add filters
    if (!empty($filters['company']) && is_array($filters['company'])) {
      $companyPlaceholders = implode(',', array_fill(0, count($filters['company']), '?'));
      $whereClauses[] = "company IN ($companyPlaceholders)";
      $params = array_merge($params, $filters['company']);
    }
    
    if (!empty($filters['experience_min'])) {
      $whereClauses[] = 'CAST(experience AS UNSIGNED) >= ?';
      $params[] = $filters['experience_min'];
    }
    
    $whereClause = implode(' AND ', $whereClauses);
    $orderClause = "ORDER BY $sort $order";
    
    $sql = "SELECT id, name, company, expertise, experience, profile_picture, bio, created_at 
            FROM users 
            WHERE $whereClause 
            $orderClause 
            LIMIT 50";
    
    $st = $this->db->prepare($sql);
    $st->execute($params);
    $users = $st->fetchAll();
    
    $results = [];
    foreach ($users as $user) {
      // Calculate match score (simplified)
      $matchScore = 0;
      $matchedFields = [];
      
      if (stripos($user['name'], $query) !== false) {
        $matchScore += 0.4;
        $matchedFields[] = 'name';
      }
      if (stripos($user['expertise'], $query) !== false) {
        $matchScore += 0.3;
        $matchedFields[] = 'expertise';
      }
      if (stripos($user['bio'], $query) !== false) {
        $matchScore += 0.2;
        $matchedFields[] = 'bio';
      }
      if (stripos($user['company'], $query) !== false) {
        $matchScore += 0.1;
        $matchedFields[] = 'company';
      }
      
      $results[] = [
        'user_id' => $user['id'],
        'name' => $user['name'],
        'company' => $user['company'],
        'expertise' => $user['expertise'],
        'experience' => $user['experience'],
        'profile_picture' => $user['profile_picture'],
        'bio' => $user['bio'],
        'match_score' => max(0.1, $matchScore),
        'matched_fields' => $matchedFields
      ];
    }
    
    // Sort by match score
    usort($results, function($a, $b) {
      return $b['match_score'] <=> $a['match_score'];
    });
    
    return $results;
  }

  public function getConnection(int $userId, int $otherUserId): ?array {
    $st = $this->db->prepare('SELECT * FROM connections WHERE (user_id = ? AND other_user_id = ?) OR (user_id = ? AND other_user_id = ?) LIMIT 1');
    $st->execute([$userId, $otherUserId, $otherUserId, $userId]);
    $result = $st->fetch();
    return $result ?: null;
  }

  public function createConnection(int $userId, int $otherUserId): bool {
    $st = $this->db->prepare('INSERT INTO connections (user_id, other_user_id, status) VALUES (?, ?, "pending")');
    return $st->execute([$userId, $otherUserId]);
  }

  public function acceptConnection(int $userId, int $otherUserId): bool {
    $st = $this->db->prepare('UPDATE connections SET status = "accepted", updated_at = CURRENT_TIMESTAMP WHERE (user_id = ? AND other_user_id = ?) OR (user_id = ? AND other_user_id = ?)');
    return $st->execute([$userId, $otherUserId, $otherUserId, $userId]);
  }

  public function getTotalAdminRequestsCount(?string $status = null): int {
    $where = $status ? 'AND admin_status = ?' : '';
    $params = $status ? [$status] : [];
    
    $sql = "SELECT COUNT(*) as total FROM users WHERE admin_request = 1 $where";
    $st = $this->db->prepare($sql);
    $st->execute($params);
    $result = $st->fetch();
    return (int)$result['total'];
  }

  public function createSuperAdmin(array $data): ?int {
    $sql = 'INSERT INTO users (name, email, phone, password_hash, google_id, role, is_onboarding_done, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())';
    
    $passwordHash = !empty($data['password']) ? password_hash($data['password'], PASSWORD_DEFAULT) : null;
    
    $st = $this->db->prepare($sql);
    $success = $st->execute([
      $data['name'],
      $data['email'],
      $data['phone'],
      $passwordHash,
      $data['google_id'] ?? null,
      $data['role'],
      $data['is_onboarding_done'] ? 1 : 0
    ]);
    
    return $success ? $this->db->lastInsertId() : null;
  }

  public function findByRole(string $role): ?array {
    $st = $this->db->prepare('SELECT * FROM users WHERE role = ? LIMIT 1');
    $st->execute([$role]);
    $result = $st->fetch();
    return $result ?: null;
  }

  public function hardDelete(int $userId): bool {
    try {
      $this->db->beginTransaction();
      
      // Delete user record
      $sql = 'DELETE FROM users WHERE id = ?';
      $st = $this->db->prepare($sql);
      $result = $st->execute([$userId]);
      
      if (!$result) {
        throw new \Exception('Failed to delete user record');
      }
      
      // Delete all related records
      $st = $this->db->prepare('DELETE FROM connections WHERE user_id = ? OR other_user_id = ?');
      $st->execute([$userId, $userId]);
      
      // Posts (including announcements) are handled by CASCADE deletion
      
      $st = $this->db->prepare('DELETE FROM enrollments WHERE user_id = ?');
      $st->execute([$userId]);
      
      $this->db->commit();
      return true;
    } catch (\Exception $e) {
      $this->db->rollBack();
      error_log("[UserModel] hardDelete failed: " . $e->getMessage());
      return false;
    }
  }

  // Admin methods for user management
  public function getDeletedUsers(int $page = 1, int $limit = 20): array {
    $offset = ($page - 1) * $limit;
    $sql = "SELECT u.id, u.name, u.email, u.company, u.role, u.deleted_at, u.delete_reason,
                   deleter.name as deleted_by_name
            FROM users u
            LEFT JOIN users deleter ON u.deleted_by = deleter.id
            WHERE u.is_deleted = 1 
            ORDER BY u.deleted_at DESC 
            LIMIT " . (int)$offset . ", " . (int)$limit;
    
    $st = $this->db->prepare($sql);
    $st->execute();
    return $st->fetchAll();
  }
  
  public function getBlockedUsers(int $page = 1, int $limit = 20): array {
    $offset = ($page - 1) * $limit;
    $sql = "SELECT u.id, u.name, u.email, u.company, u.role, u.blocked_at, u.block_reason,
                   blocker.name as blocked_by_name
            FROM users u
            LEFT JOIN users blocker ON u.blocked_by = blocker.id
            WHERE u.is_blocked = 1 AND u.is_deleted = 0
            ORDER BY u.blocked_at DESC 
            LIMIT " . (int)$offset . ", " . (int)$limit;
    
    $st = $this->db->prepare($sql);
    $st->execute();
    return $st->fetchAll();
  }
  
  public function getUserStatus(int $userId): ?array {
    $st = $this->db->prepare('SELECT id, name, email, is_deleted, is_blocked, deleted_at, blocked_at, delete_reason, block_reason FROM users WHERE id = ?');
    $st->execute([$userId]);
    $result = $st->fetch();
    return $result ?: null;
  }

  public function getDatabase(): PDO {
    return $this->db;
  }

  public function demoteAdmin(int $userId): bool {
    $st = $this->db->prepare('UPDATE users SET role = "user", admin_request = 0, admin_status = NULL WHERE id = ? AND role = "admin"');
    return $st->execute([$userId]);
  }

  // General method to change user roles with automatic admin request field handling
  public function changeUserRole(int $userId, string $newRole): bool {
    // Validate role
    $validRoles = ['user', 'admin', 'super_admin', 'faculty'];
    if (!in_array($newRole, $validRoles)) {
      return false;
    }
    
    // Get current user details
    $user = $this->findById($userId);
    if (!$user) {
      return false;
    }
    
    $currentRole = $user['role'];
    
        // If demoting from admin to user, reset admin request fields
    if ($currentRole === 'admin' && $newRole === 'user') {
      $st = $this->db->prepare('UPDATE users SET role = ?, admin_request = 0, admin_status = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
      return $st->execute([$newRole, $userId]);
    }
    
    // For other role changes, just update the role
    $st = $this->db->prepare('UPDATE users SET role = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    return $st->execute([$newRole, $userId]);
  }

  // Device token management methods
  public function updateDeviceToken(int $userId, ?string $deviceToken): bool {
    // First check if user is eligible to save device token
    $user = $this->findById($userId);
    if (!$user) {
      return false;
    }
    
    // Only save device token if user has completed onboarding and is not deleted/blocked
    if (!$user['is_onboarding_done'] || $user['is_deleted'] || $user['is_blocked']) {
      return false;
    }
    
    // 🔥 FIX: Clear this token from other users first (prevent duplicates)
    if (!empty($deviceToken)) {
      $this->clearDeviceTokenByToken($deviceToken);
      error_log("🔥 [UserModel] 🧹 Cleared duplicate token from other users before assigning to user {$userId}");
    }
    
    $st = $this->db->prepare('UPDATE users SET device_token = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    return $st->execute([$deviceToken, $userId]);
  }

  public function clearDeviceToken(int $userId): bool {
    $st = $this->db->prepare('UPDATE users SET device_token = NULL, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    return $st->execute([$userId]);
  }

  public function clearDeviceTokenByToken(string $deviceToken): bool {
    $st = $this->db->prepare('UPDATE users SET device_token = NULL, updated_at = CURRENT_TIMESTAMP WHERE device_token = ?');
    return $st->execute([$deviceToken]);
  }

  public function findByDeviceToken(string $deviceToken): ?array {
    $st = $this->db->prepare('SELECT * FROM users WHERE device_token = ? LIMIT 1');
    $st->execute([$deviceToken]);
    $result = $st->fetch();
    return $result ?: null;
  }

  public function getUsersWithDeviceTokens(array $userIds = []): array {
    if (empty($userIds)) {
      // Get all users with device tokens who are active and have completed onboarding
      $st = $this->db->prepare('SELECT id, name, email, device_token FROM users WHERE device_token IS NOT NULL AND is_onboarding_done = 1 AND is_deleted = 0 AND is_blocked = 0');
      $st->execute();
    } else {
      // Get specific users with device tokens
      $placeholders = implode(',', array_fill(0, count($userIds), '?'));
      $st = $this->db->prepare("SELECT id, name, email, device_token FROM users WHERE id IN ($placeholders) AND device_token IS NOT NULL AND is_onboarding_done = 1 AND is_deleted = 0 AND is_blocked = 0");
      $st->execute($userIds);
    }
    return $st->fetchAll();
  }

  /**
   * Clean up device tokens for users who are no longer eligible
   * (deleted, blocked, or haven't completed onboarding)
   */
  public function cleanupIneligibleDeviceTokens(): int {
    $st = $this->db->prepare('UPDATE users SET device_token = NULL WHERE device_token IS NOT NULL AND (is_onboarding_done = 0 OR is_deleted = 1 OR is_blocked = 1)');
    $st->execute();
    return $st->rowCount();
  }

  /**
   * Centralized method to build role-based filtering for WHERE clauses
   * 
   * @param array $filters - Array containing viewer_role, exclude_user_id, include_blocked, include_deleted
   * @param array $whereClauses - Reference to array where WHERE clauses will be added
   * @param array $params - Reference to array where parameters will be added
   * @return void
   */
  private function buildRoleFiltering(array $filters, array &$whereClauses, array &$params): void {
    // Always require completed onboarding
    $whereClauses[] = 'is_onboarding_done = 1';
    
    $viewerRole = $filters['viewer_role'] ?? '';
    
    if ($viewerRole === 'super_admin') {
      // Super admin sees ALL users - no additional restrictions
      // Unless admin dashboard flags are set, still exclude blocked/deleted by default
      if (empty($filters['include_blocked'])) {
        $whereClauses[] = 'is_blocked = 0';
      }
      if (empty($filters['include_deleted'])) {
        $whereClauses[] = 'is_deleted = 0';
      }
    } else {
      // For admin, user, and faculty: only show admin + user roles, exclude blocked/deleted
      $whereClauses[] = 'is_blocked = 0';
      $whereClauses[] = 'is_deleted = 0';
      $whereClauses[] = "role IN (?, ?)";
      $params[] = 'admin';
      $params[] = 'user';
    }
    
    // Exclude specific user if provided (e.g., current user)
    if (!empty($filters['exclude_user_id'])) {
      $whereClauses[] = 'id != ?';
      $params[] = (int)$filters['exclude_user_id'];
    }
  }

  /**
   * Update user's password
   * @param int $userId User ID
   * @param string $passwordHash The new password hash
   * @return bool Success status
   */
  public function updatePassword(int $userId, string $passwordHash): bool {
    $st = $this->db->prepare('UPDATE users SET password_hash = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?');
    return $st->execute([$passwordHash, $userId]);
  }

  // ========== Apple Sign-In Methods ==========
  
  /**
   * Find user by Apple user ID
   * @param string $appleUserId Apple's unique user identifier
   * @return array|null User data or null if not found
   */
  public function findByAppleId(string $appleUserId): ?array {
    $st = $this->db->prepare('SELECT * FROM users WHERE apple_user_id = ? LIMIT 1');
    $st->execute([$appleUserId]);
    $r = $st->fetch();
    return $r ?: null;
  }

  /**
   * Find user by BOTH email and Apple ID
   * @param string $email
   * @param string $appleUserId
   * @return array|null
   */
  public function findByEmailAndAppleId(string $email, string $appleUserId): ?array {
    $st = $this->db->prepare('SELECT * FROM users WHERE email = ? AND apple_user_id = ? LIMIT 1');
    $st->execute([$email, $appleUserId]);
    $r = $st->fetch();
    return $r ?: null;
  }

  /**
   * Create new user with Apple Sign-In
   * @param string $name User's name
   * @param string $email User's email
   * @param string $hash Password hash
   * @param string $appleUserId Apple user ID
   * @param string $role User role (default: 'user')
   * @param bool $emailVerified Email verification status (default: false)
   * @return int New user ID
   */
  public function createWithApple(string $name, string $email, string $hash, string $appleUserId, string $role = 'user', bool $emailVerified = false): int {
    $st = $this->db->prepare('INSERT INTO users (name, email, phone, password_hash, apple_user_id, role, email_verified, is_onboarding_done) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $st->execute([$name, $email, '', $hash, $appleUserId, $role, $emailVerified ? 1 : 0, 0]);
    return (int)$this->db->lastInsertId();
  }

  /**
   * Update Apple user ID for existing user
   * @param int $id User ID
   * @param string $appleUserId Apple user ID
   * @return bool Success status
   */
  public function updateAppleId(int $id, string $appleUserId): bool {
    $st = $this->db->prepare('UPDATE users SET apple_user_id = ? WHERE id = ?');
    return $st->execute([$appleUserId, $id]);
  }

  /**
   * Update email verification status
   * @param int $id User ID
   * @param bool $verified Verification status
   * @return bool Success status
   */
  public function updateEmailVerified(int $id, bool $verified): bool {
    $st = $this->db->prepare('UPDATE users SET email_verified = ? WHERE id = ?');
    return $st->execute([$verified ? 1 : 0, $id]);
  }

  // ========== OTP Management Methods ==========

  /**
   * Store OTP for email verification
   * @param string $email Email address
   * @param string $otp 6-digit OTP code
   * @param int $expirationMinutes Expiration time in minutes (default: 10)
   * @return bool Success status
   */
  public function storeOTP(string $email, string $otp, int $expirationMinutes = 10): bool {
    // Delete any existing OTP for this email
    $this->deleteOTP($email);
    
    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expirationMinutes} minutes"));
    $st = $this->db->prepare('INSERT INTO email_verifications (email, otp, expires_at) VALUES (?, ?, ?)');
    return $st->execute([$email, $otp, $expiresAt]);
  }

  /**
   * Verify OTP for email
   * @param string $email Email address
   * @param string $otp OTP code to verify
   * @return array|null Returns array with status, or null if not found
   */
  public function verifyOTP(string $email, string $otp): ?array {
    $st = $this->db->prepare('SELECT * FROM email_verifications WHERE email = ? ORDER BY created_at DESC LIMIT 1');
    $st->execute([$email]);
    $record = $st->fetch();
    
    if (!$record) {
      return ['valid' => false, 'error' => 'No verification code found'];
    }

    // Check if expired
    if (strtotime($record['expires_at']) < time()) {
      return ['valid' => false, 'error' => 'Verification code expired'];
    }

    // Check if max attempts exceeded
    if ($record['attempts'] >= 3) {
      return ['valid' => false, 'error' => 'Too many failed attempts'];
    }

    // Verify OTP
    if ($record['otp'] === $otp) {
      // Success - delete the OTP
      $this->deleteOTP($email);
      return ['valid' => true];
    } else {
      // Increment attempts
      $this->incrementOTPAttempts($record['id']);
      return ['valid' => false, 'error' => 'Invalid verification code', 'attempts_remaining' => 3 - ($record['attempts'] + 1)];
    }
  }

  /**
   * Increment OTP verification attempts
   * @param int $id Verification record ID
   * @return bool Success status
   */
  private function incrementOTPAttempts(int $id): bool {
    $st = $this->db->prepare('UPDATE email_verifications SET attempts = attempts + 1 WHERE id = ?');
    return $st->execute([$id]);
  }

  /**
   * Delete OTP for email
   * @param string $email Email address
   * @return bool Success status
   */
  public function deleteOTP(string $email): bool {
    $st = $this->db->prepare('DELETE FROM email_verifications WHERE email = ?');
    return $st->execute([$email]);
  }

  /**
   * Clean up expired OTPs
   * @return int Number of deleted records
   */
  public function cleanupExpiredOTPs(): int {
    $st = $this->db->prepare('DELETE FROM email_verifications WHERE expires_at < NOW()');
    $st->execute();
    return $st->rowCount();
  }

  // ========== Rate Limiting Methods ==========

  /**
   * Check if rate limit exceeded for email
   * @param string $email Email address
   * @param int $maxRequests Maximum requests allowed (default: 3)
   * @param int $windowHours Time window in hours (default: 1)
   * @return bool True if rate limit exceeded
   */
  public function checkRateLimit(string $email, int $maxRequests = 3, int $windowHours = 1): bool {
    $st = $this->db->prepare('SELECT * FROM otp_rate_limits WHERE email = ?');
    $st->execute([$email]);
    $record = $st->fetch();

    if (!$record) {
      // No record exists, create one
      $st = $this->db->prepare('INSERT INTO otp_rate_limits (email, request_count) VALUES (?, 1)');
      $st->execute([$email]);
      return false;
    }

    $windowStart = strtotime($record['window_start']);
    $windowEnd = $windowStart + ($windowHours * 3600);

    if (time() > $windowEnd) {
      // Window expired, reset counter
      $st = $this->db->prepare('UPDATE otp_rate_limits SET request_count = 1, window_start = NOW() WHERE email = ?');
      $st->execute([$email]);
      return false;
    }

    if ($record['request_count'] >= $maxRequests) {
      return true; // Rate limit exceeded
    }

    // Increment counter
    $st = $this->db->prepare('UPDATE otp_rate_limits SET request_count = request_count + 1 WHERE email = ?');
    $st->execute([$email]);
    return false;
  }

  /**
   * Clear rate limit for email
   * @param string $email Email address
   * @return bool Success status
   */
  public function clearRateLimit(string $email): bool {
    $st = $this->db->prepare('DELETE FROM otp_rate_limits WHERE email = ?');
    return $st->execute([$email]);
  }

  /**
   * Clean up old rate limit records
   * @param int $hours Age threshold in hours (default: 24)
   * @return int Number of deleted records
   */
  public function cleanupOldRateLimits(int $hours = 24): int {
    $st = $this->db->prepare('DELETE FROM otp_rate_limits WHERE window_start < DATE_SUB(NOW(), INTERVAL ? HOUR)');
    $st->execute([$hours]);
    return $st->rowCount();
  }
}

<?php
namespace Controllers;
use Core\Response;
use Core\Auth as AuthCore;
use Models\NetworkModel;
use Models\UserModel;

class NetworkController {
  private NetworkModel $net;
  private UserModel $users;
  
  public function __construct(){ 
    $this->net = new NetworkModel(); 
    $this->users = new UserModel();
  }

  // GET /network/users - Get paginated list of users
  public function getUsers(){
    $p = AuthCore::requireUser();
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $sort = $_GET['sort'] ?? 'name';
    $order = $_GET['order'] ?? 'asc';
    
    $filters = [
      'exclude_user_id' => (int)$p['sub'], // Exclude current user
      'viewer_role' => $p['role'], // Pass the user's role for permission filtering
      'sort' => $sort,
      'order' => $order
    ];
    
    if (!empty($_GET['search'])) $filters['search'] = $_GET['search'];
    if (!empty($_GET['company'])) $filters['company'] = $_GET['company'];
    if (!empty($_GET['expertise'])) $filters['expertise'] = $_GET['expertise'];
    if (!empty($_GET['experience_min'])) $filters['experience_min'] = (int)$_GET['experience_min'];
    if (!empty($_GET['experience_max'])) $filters['experience_max'] = (int)$_GET['experience_max'];
    if (!empty($_GET['role'])) {
      // Handle single role or comma-separated multiple roles to filter users by role
      $roleParam = $_GET['role'];
      if (strpos($roleParam, ',') !== false) {
        // Multiple roles provided as comma-separated string
        $filters['filter_roles'] = array_map('trim', explode(',', $roleParam));
      } else {
        // Single role provided
        $filters['filter_roles'] = [trim($roleParam)];
      }
    }
    
    // For admin dashboard, allow including all users (blocked and deleted)
    if (!empty($_GET['include_all'])) {
      $filters['include_blocked'] = true;
      $filters['include_deleted'] = true;
    }
    
    $users = $this->users->getAllUsers($page, $limit, $filters);
    
    // Only load filter data on first page for performance optimization
    $filtersData = ($page == 1) ? $this->users->getNetworkFilters() : null;
    
    // Calculate pagination
    $totalUsers = $this->users->getTotalUsersCount($filters);
    $totalPages = ceil($totalUsers / $limit);
    
    // Prepare sorting information
    // $sortingInfo = [
    //   'applied_sort' => $sort,
    //   'applied_order' => $order,
    //   'available_sorts' => [
    //     ['key' => 'created_at', 'label' => 'Join Date', 'description' => 'Sort by when user joined'],
    //     ['key' => 'name', 'label' => 'Name', 'description' => 'Sort alphabetically by name'],
    //     ['key' => 'experience', 'label' => 'Experience', 'description' => 'Sort by years of experience'],
    //     ['key' => 'company', 'label' => 'Company', 'description' => 'Sort alphabetically by company']
    //   ],
    //   'available_orders' => [
    //     ['key' => 'asc', 'label' => 'Ascending', 'description' => 'Lowest to highest / A to Z'],
    //     ['key' => 'desc', 'label' => 'Descending', 'description' => 'Highest to lowest / Z to A']
    //   ]
    // ];
    
    return Response::json([
      'success' => true,
      'users' => $users,
      'pagination' => [
        'current_page' => $page,
        'total_pages' => $totalPages,
        'total_users' => $totalUsers,
        'has_next' => $page < $totalPages,
        'has_prev' => $page > 1
      ],
      'filters' => $filtersData,
      // 'sorting' => $sortingInfo
    ]);
  }

  // GET /network/users/{user_id} - Get detailed user profile
  public function getUserProfile(array $params){
    $p = AuthCore::requireUser();
    $userId = (int)$params['id'];
    $user = $this->users->getPublicProfile($userId);
    
    if (!$user) {
      return Response::json([
        'success' => false,
        'message' => 'User not found'
      ], 404);
    }
    
    // Hide super admin profiles from non-super-admin users
    if ($user['role'] === 'super_admin' && $p['role'] !== 'super_admin') {
      return Response::json([
        'success' => false,
        'message' => 'User not found'
      ], 404);
    }
    
    return Response::json([
      'success' => true,
      'user' => $user
    ]);
  }

  // GET /network/search - Advanced search for users
  public function searchUsers(){
    $query = $_GET['q'] ?? '';
    $filters = [];
    $sort = $_GET['sort'] ?? 'name';
    $order = $_GET['order'] ?? 'asc';
    
    if (!empty($_GET['filters'])) {
      $filters = json_decode($_GET['filters'], true) ?? [];
    }
    
    if (empty($query)) {
      return Response::json([
        'success' => false,
        'message' => 'Search query is required'
      ], 422);
    }
    
    $startTime = microtime(true);
    $results = $this->users->searchUsers($query, $filters, $sort, $order);
    $searchTime = round((microtime(true) - $startTime) * 1000);
    
    return Response::json([
      'success' => true,
      'results' => $results,
      'total_results' => count($results),
      'search_time_ms' => $searchTime
    ]);
  }

  // GET /network/filters - Get filter options
  public function getFilters(){
    $filters = $this->users->getNetworkFilters();
    
    return Response::json([
      'success' => true,
      'filters' => $filters
    ]);
  }

  // Legacy methods for backward compatibility
  public function connectOrAccept(array $params){ 
    $p = AuthCore::requireUser(); 
    $ok=$this->net->connectOrAccept((int)$p['sub'], (int)$params['user_id']); 
    return Response::json(['ok'=>$ok]); 
  }

  public function list(){ 
    $p = AuthCore::requireUser(); 
    return Response::json(['connections'=>$this->net->listForUser((int)$p['sub'])]); 
  }
}

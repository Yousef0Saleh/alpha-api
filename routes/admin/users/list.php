<?php
/**
 * Admin Users List API
 * عرض قائمة المستخدمين مع البحث والفلترة
 */

// Set CORS headers FIRST before any output
require_once __DIR__ . '/../../../config/cors.php';
header('Content-Type: application/json; charset=UTF-8');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// Start session AFTER headers
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Check admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
  http_response_code(403);
  echo json_encode(['status' => 'error', 'message' => 'مش مصرح ليك'], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  require_once __DIR__ . '/../../../config/db.php';

  // Get query parameters
  $search = isset($_GET['search']) ? trim($_GET['search']) : '';
  $role = isset($_GET['role']) ? trim($_GET['role']) : '';
  $grade = isset($_GET['grade']) ? trim($_GET['grade']) : '';
  $is_banned = isset($_GET['is_banned']) ? $_GET['is_banned'] : '';
  $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
  $per_page = isset($_GET['per_page']) ? min(100, max(10, intval($_GET['per_page']))) : 20;
  $offset = ($page - 1) * $per_page;

  // Build WHERE clause
  $where = [];
  $params = [];

  if ($search !== '') {
    $where[] = "(name LIKE :search OR email LIKE :search)";
    $params[':search'] = "%$search%";
  }

  if ($role !== '' && in_array($role, ['student', 'admin'])) {
    $where[] = "role = :role";
    $params[':role'] = $role;
  }

  if ($grade !== '' && in_array($grade, ['first', 'second', 'third'])) {
    $where[] = "grade = :grade";
    $params[':grade'] = $grade;
  }

  if ($is_banned !== '') {
    $where[] = "is_banned = :is_banned";
    $params[':is_banned'] = $is_banned === 'true' ? 1 : 0;
  }

  $where_sql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

  // Get total count
  $count_sql = "SELECT COUNT(*) as total FROM users $where_sql";
  $stmt = $pdo->prepare($count_sql);
  $stmt->execute($params);
  $total = $stmt->fetch()['total'];

  // Get users with pagination
  $sql = "
        SELECT 
            id, name, email, grade, role, is_banned, created_at, last_activity
        FROM users
        $where_sql
        ORDER BY created_at DESC
        LIMIT :limit OFFSET :offset
    ";

  $stmt = $pdo->prepare($sql);
  foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
  }
  $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  $stmt->execute();
  $users = $stmt->fetchAll();

  http_response_code(200);
  echo json_encode([
    'status' => 'success',
    'data' => [
      'users' => $users,
      'pagination' => [
        'total' => (int) $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total / $per_page)
      ]
    ]
  ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
  error_log("[Admin Users List Error] " . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'status' => 'error',
    'message' => 'حصل خطأ في تحميل المستخدمين'
  ], JSON_UNESCAPED_UNICODE);
}

<?php
/**
 * Admin Activity Logs API
 */

// CORS headers FIRST
require_once __DIR__ . '/../../../config/cors.php';
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}


// Session management
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
  http_response_code(403);
  echo json_encode(['status' => 'error', 'message' => 'مش مصرح ليك'], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  require_once __DIR__ . '/../../../config/db.php';

  $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
  $per_page = 50;
  $offset = ($page - 1) * $per_page;

  try {
    $count_stmt = $pdo->query("SELECT COUNT(*) as total FROM activity_logs");
    $total = $count_stmt->fetch()['total'];

    $stmt = $pdo->query("
            SELECT 
                al.*,
                u.name as user_name,
                a.name as admin_name
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            LEFT JOIN users a ON al.admin_id = a.id
            ORDER BY al.created_at DESC
            LIMIT $per_page OFFSET $offset
        ");
    $logs = $stmt->fetchAll();
  } catch (PDOException $e) {
    $total = 0;
    $logs = [];
  }

  http_response_code(200);
  echo json_encode([
    'status' => 'success',
    'data' => [
      'logs' => $logs,
      'pagination' => [
        'total' => (int) $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total / $per_page)
      ]
    ]
  ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
  error_log("[Admin Activity Logs Error] " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['status' => 'error', 'message' => 'حصل خطأ'], JSON_UNESCAPED_UNICODE);
}

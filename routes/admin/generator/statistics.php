<?php
/**
 * Admin Exam Generator Statistics API
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

  // General Stats
  $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_generated,
            COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as today_generated,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as week_generated,
            COUNT(DISTINCT user_id) as active_users
        FROM generated_exams
    ");
  $stats = $stmt->fetch();

  // Recent Generated Exams
  $stmt = $pdo->query("
        SELECT 
            ge.id,
            ge.filename,
            ge.created_at,
            u.name as user_name,
            u.email as user_email
        FROM generated_exams ge
        JOIN users u ON ge.user_id = u.id
        ORDER BY ge.created_at DESC
        LIMIT 10
    ");
  $recent_generated = $stmt->fetchAll();

  http_response_code(200);
  echo json_encode([
    'status' => 'success',
    'data' => array_merge($stats, ['recent_generated' => $recent_generated])
  ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
  error_log("[Admin Exam Generator Stats Error] " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['status' => 'error', 'message' => 'حصل خطأ'], JSON_UNESCAPED_UNICODE);
}

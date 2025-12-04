<?php
/**
 * Admin Exams List API
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

  $grade = isset($_GET['grade']) ? trim($_GET['grade']) : '';
  $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
  $per_page = isset($_GET['per_page']) ? min(100, max(10, intval($_GET['per_page']))) : 20;
  $offset = ($page - 1) * $per_page;

  $where = [];
  $params = [];

  if ($grade !== '' && in_array($grade, ['first', 'second', 'third'])) {
    $where[] = "grade = :grade";
    $params[':grade'] = $grade;
  }

  $where_sql = count($where) > 0 ? 'WHERE ' . implode(' AND ', $where) : '';

  $count_sql = "SELECT COUNT(*) as total FROM exams $where_sql";
  $stmt = $pdo->prepare($count_sql);
  $stmt->execute($params);
  $total = $stmt->fetch()['total'];

  $sql = "
        SELECT 
            e.id,
            e.title,
            e.duration,
            e.grade,
            e.created_at,
            JSON_LENGTH(e.questions_json) as questions_count,
            COUNT(DISTINCT er.user_id) as students_count,
            COUNT(DISTINCT CASE WHEN er.submitted = 1 THEN er.id END) as completed_count
        FROM exams e
        LEFT JOIN exam_results er ON e.id = er.exam_id
        $where_sql
        GROUP BY e.id
        ORDER BY e.created_at DESC
        LIMIT :limit OFFSET :offset
    ";

  $stmt = $pdo->prepare($sql);
  foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
  }
  $stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
  $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
  $stmt->execute();
  $exams = $stmt->fetchAll();

  http_response_code(200);
  echo json_encode([
    'status' => 'success',
    'data' => [
      'exams' => $exams,
      'pagination' => [
        'total' => (int) $total,
        'page' => $page,
        'per_page' => $per_page,
        'total_pages' => ceil($total / $per_page)
      ]
    ]
  ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
  error_log("[Admin Exams List Error] " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['status' => 'error', 'message' => 'حصل خطأ'], JSON_UNESCAPED_UNICODE);
}

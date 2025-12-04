<?php
/**
 * Admin Delete Exam API
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

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!is_array($data)) {
  http_response_code(400);
  echo json_encode(['status' => 'error', 'message' => 'Invalid JSON'], JSON_UNESCAPED_UNICODE);
  exit;
}

$exam_id = isset($data['exam_id']) ? intval($data['exam_id']) : 0;

if ($exam_id <= 0) {
  http_response_code(400);
  echo json_encode(['status' => 'error', 'message' => 'معرف الامتحان مطلوب'], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  require_once __DIR__ . '/../../../config/db.php';
  require_once __DIR__ . '/../middleware/check_admin.php';

  $stmt = $pdo->prepare("SELECT id, title FROM exams WHERE id = :exam_id");
  $stmt->execute([':exam_id' => $exam_id]);
  $exam = $stmt->fetch();

  if (!$exam) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'الامتحان مش موجود'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // تسجيل النشاط قبل الحذف
  logActivity(
    $pdo,
    'exam_deleted',
    "حذف الامتحان '{$exam['title']}' (ID: $exam_id)",
    'exam',
    $exam_id
  );

  $stmt = $pdo->prepare("DELETE FROM exams WHERE id = :exam_id");
  $stmt->execute([':exam_id' => $exam_id]);

  http_response_code(200);
  echo json_encode(['status' => 'success', 'message' => 'تم حذف الامتحان بنجاح'], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
  error_log("[Admin Delete Exam Error] " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['status' => 'error', 'message' => 'حصل خطأ'], JSON_UNESCAPED_UNICODE);
}

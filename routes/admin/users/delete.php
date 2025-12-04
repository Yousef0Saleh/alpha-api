<?php
/**
 * Admin Delete User API
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

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  echo json_encode(['status' => 'error', 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
  exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!is_array($data)) {
  http_response_code(400);
  echo json_encode(['status' => 'error', 'message' => 'Invalid JSON'], JSON_UNESCAPED_UNICODE);
  exit;
}

$user_id = isset($data['user_id']) ? intval($data['user_id']) : 0;

if ($user_id <= 0) {
  http_response_code(400);
  echo json_encode(['status' => 'error', 'message' => 'معرف المستخدم مطلوب'], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  require_once __DIR__ . '/../../../config/db.php';
  require_once __DIR__ . '/../middleware/check_admin.php';

  $stmt = $pdo->prepare("SELECT id, name, role, email FROM users WHERE id = :user_id");
  $stmt->execute([':user_id' => $user_id]);
  $user = $stmt->fetch();

  if (!$user) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'المستخدم مش موجود'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($user['role'] === 'admin') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'مينفعش تمسح حساب أدمن'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($user_id === $_SESSION['user']['id']) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'مينفعش تمسح حسابك'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // تسجيل النشاط قبل الحذف
  logActivity(
    $pdo,
    'user_deleted',
    "حذف المستخدم {$user['name']} ({$user['email']}) - ID: $user_id",
    'user',
    $user_id
  );

  $stmt = $pdo->prepare("DELETE FROM users WHERE id = :user_id");
  $stmt->execute([':user_id' => $user_id]);

  http_response_code(200);
  echo json_encode([
    'status' => 'success',
    'message' => 'تم حذف المستخدم بنجاح'
  ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
  error_log("[Admin Delete User Error] " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['status' => 'error', 'message' => 'حصل خطأ في الحذف'], JSON_UNESCAPED_UNICODE);
}

<?php
/**
 * Admin Update User API
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

$user_id = isset($data['user_id']) ? intval($data['user_id']) : 0;
$name = isset($data['name']) ? trim($data['name']) : '';
$grade = isset($data['grade']) ? trim($data['grade']) : null;
$role = isset($data['role']) ? trim($data['role']) : '';

if ($user_id <= 0 || $name === '' || !in_array($role, ['student', 'admin'])) {
  http_response_code(400);
  echo json_encode(['status' => 'error', 'message' => 'بيانات غير صحيحة'], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($grade !== null && !in_array($grade, ['first', 'second', 'third'])) {
  http_response_code(400);
  echo json_encode(['status' => 'error', 'message' => 'الصف الدراسي غير صحيح'], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  require_once __DIR__ . '/../../../config/db.php';
  require_once __DIR__ . '/../middleware/check_admin.php';

  $stmt = $pdo->prepare("SELECT id, name, role, grade FROM users WHERE id = :user_id");
  $stmt->execute([':user_id' => $user_id]);
  $old_user = $stmt->fetch();

  if (!$old_user) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'المستخدم مش موجود'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $stmt = $pdo->prepare("UPDATE users SET name = :name, grade = :grade, role = :role WHERE id = :user_id");
  $stmt->execute([':name' => $name, ':grade' => $grade, ':role' => $role, ':user_id' => $user_id]);

  // If user is editing their own profile, update session data
  if (isset($_SESSION['user']) && $_SESSION['user']['id'] == $user_id) {
    $_SESSION['user']['name'] = $name;
    $_SESSION['user']['grade'] = $grade;
    $_SESSION['user']['role'] = $role;
  }

  // تسجيل التغييرات
  $changes = [];
  if ($old_user['name'] !== $name)
    $changes[] = "الاسم من '{$old_user['name']}' إلى '$name'";
  if ($old_user['grade'] !== $grade)
    $changes[] = "الصف من '{$old_user['grade']}' إلى '$grade'";
  if ($old_user['role'] !== $role)
    $changes[] = "الدور من '{$old_user['role']}' إلى '$role'";

  if (!empty($changes)) {
    $description = "تحديث بيانات المستخدم {$old_user['name']} (ID: $user_id): " . implode(', ', $changes);
    logActivity(
      $pdo,
      'user_updated',
      $description,
      'user',
      $user_id
    );
  }

  http_response_code(200);
  echo json_encode(['status' => 'success', 'message' => 'تم تحديث البيانات بنجاح'], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
  error_log("[Admin Update User Error] " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['status' => 'error', 'message' => 'حصل خطأ'], JSON_UNESCAPED_UNICODE);
}

<?php
/**
 * Admin Middleware - التحقق من صلاحيات الأدمن
 * يجب استدعاء هذا الملف في بداية كل صفحة أدمن
 */

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// CRITICAL: Set CORS headers FIRST before any other logic
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../../config/cors.php';
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

/**
 * التحقق من أن المستخدم مسجل دخول وله صلاحيات أدمن
 * @return bool
 */
function checkAdmin()
{
  if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode([
      'status' => 'error',
      'message' => 'لازم تسجل دخولك الأول'
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($_SESSION['user']['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode([
      'status' => 'error',
      'message' => 'مش مصرح ليك تدخل الصفحة دي'
    ], JSON_UNESCAPED_UNICODE);
    exit;
  }

  return true;
}

/**
 * تسجيل نشاط في سجل النشاطات
 * @param PDO $pdo
 * @param string $activity_type نوع النشاط
 * @param string $description الوصف
 * @param string|null $target_type نوع الهدف (user, exam, conversation)
 * @param int|null $target_id معرف الهدف
 */
function logActivity($pdo, $activity_type, $description, $target_type = null, $target_id = null)
{
  try {
    $admin_id = $_SESSION['user']['id'] ?? null;
    $user_id = $target_type === 'user' ? $target_id : null;
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? null;
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? null;

    $stmt = $pdo->prepare("
            INSERT INTO activity_logs 
            (admin_id, user_id, activity_type, description, target_type, target_id, ip_address, user_agent)
            VALUES (:admin_id, :user_id, :activity_type, :description, :target_type, :target_id, :ip_address, :user_agent)
        ");

    $stmt->execute([
      ':admin_id' => $admin_id,
      ':user_id' => $user_id,
      ':activity_type' => $activity_type,
      ':description' => $description,
      ':target_type' => $target_type,
      ':target_id' => $target_id,
      ':ip_address' => $ip_address,
      ':user_agent' => substr($user_agent, 0, 255) // Limit to 255 chars
    ]);
  } catch (PDOException $e) {
    // فقط نسجل الخطأ، مش هنعطل البرنامج
    error_log("Failed to log activity: " . $e->getMessage());
  }
}

/**
 * دالة مساعدة للرد بـ JSON
 * @param int $code
 * @param array $data
 */
function respond($code, $data)
{
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

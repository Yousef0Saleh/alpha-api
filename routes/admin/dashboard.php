<?php
/**
 * Admin Dashboard API
 * جلب الإحصائيات العامة للوحة التحكم
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Set CORS headers FIRST
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../config/cors.php';
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// Check if user is admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
  http_response_code(403);
  echo json_encode([
    'status' => 'error',
    'message' => 'مش مصرح ليك تدخل الصفحة دي'
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  require_once __DIR__ . '/../../config/db.php';

  // 1. إحصائيات المستخدمين
  $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_users,
            COUNT(CASE WHEN role = 'admin' THEN 1 END) as admin_count,
            COUNT(CASE WHEN role = 'student' THEN 1 END) as student_count,
            COUNT(CASE WHEN is_banned = TRUE THEN 1 END) as banned_count,
            COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as new_today,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as new_this_week
        FROM users
    ");
  $user_stats = $stmt->fetch();

  // 2. إحصائيات الامتحانات
  $stmt = $pdo->query("
        SELECT 
            COUNT(DISTINCT e.id) as total_exams,
            COUNT(DISTINCT er.id) as total_attempts,
            COUNT(DISTINCT CASE WHEN er.submitted = 1 THEN er.id END) as completed_attempts,
            COUNT(DISTINCT CASE WHEN er.submitted = 0 THEN er.id END) as in_progress_attempts
        FROM exams e
        LEFT JOIN exam_results er ON e.id = er.exam_id
    ");
  $exam_stats = $stmt->fetch();

  // 3. إحصائيات المحادثات
  $stmt = $pdo->query("
        SELECT 
            COUNT(DISTINCT c.id) as total_conversations,
            COUNT(DISTINCT m.id) as total_messages,
            COUNT(DISTINCT CASE WHEN m.role = 'user' THEN m.id END) as user_messages,
            COUNT(DISTINCT CASE WHEN m.role = 'assistant' THEN m.id END) as assistant_messages,
            COUNT(DISTINCT CASE WHEN DATE(c.created_at) = CURDATE() THEN c.id END) as conversations_today,
            COUNT(DISTINCT CASE WHEN DATE(m.created_at) = CURDATE() THEN m.id END) as messages_today
        FROM conversations c
        LEFT JOIN messages m ON c.id = m.conversation_id
    ");
  $chat_stats = $stmt->fetch();

  // 4. إحصائيات الملخصات
  $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_summaries,
            COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as summaries_today,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as summaries_this_week
        FROM file_summaries
    ");
  $summary_stats = $stmt->fetch();

  // 5. إحصائيات مولد الامتحانات
  $stmt = $pdo->query("
        SELECT 
            COUNT(*) as total_generated,
            COUNT(CASE WHEN DATE(created_at) = CURDATE() THEN 1 END) as generated_today,
            COUNT(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as generated_this_week
        FROM generated_exams
    ");
  $generator_stats = $stmt->fetch();

  // 6. آخر 10 مستخدمين مسجلين
  $stmt = $pdo->query("
        SELECT id, name, email, grade, role, created_at
        FROM users
        ORDER BY created_at DESC
        LIMIT 10
    ");
  $recent_users = $stmt->fetchAll();

  // 7. آخر 10 نشاطات (fallback if table doesn't exist)
  try {
    $stmt = $pdo->query("
            SELECT 
                al.*,
                u.name as user_name,
                a.name as admin_name
            FROM activity_logs al
            LEFT JOIN users u ON al.user_id = u.id
            LEFT JOIN users a ON al.admin_id = a.id
            ORDER BY al.created_at DESC
            LIMIT 10
        ");
    $recent_activities = $stmt->fetchAll();
  } catch (PDOException $e) {
    $recent_activities = [];
  }

  // 8. إحصائيات المستخدمين حسب الصف الدراسي
  $stmt = $pdo->query("
        SELECT 
            grade,
            COUNT(*) as count
        FROM users
        WHERE grade IS NOT NULL
        GROUP BY grade
    ");
  $users_by_grade = $stmt->fetchAll();

  http_response_code(200);
  echo json_encode([
    'status' => 'success',
    'data' => [
      'users' => $user_stats,
      'exams' => $exam_stats,
      'chat' => $chat_stats,
      'summaries' => $summary_stats,
      'generator' => $generator_stats,
      'recent_users' => $recent_users,
      'recent_activities' => $recent_activities,
      'users_by_grade' => $users_by_grade
    ]
  ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
  error_log("[Admin Dashboard Error] " . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'status' => 'error',
    'message' => 'حصل خطأ في تحميل البيانات',
    'debug' => $e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
  error_log("[Admin Dashboard Fatal Error] " . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'status' => 'error',
    'message' => 'خطأ في السيرفر',
    'debug' => $e->getMessage()
  ], JSON_UNESCAPED_UNICODE);
}

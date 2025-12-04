<?php
/**
 * Admin User Details API
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

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
  http_response_code(405);
  echo json_encode(['status' => 'error', 'message' => 'Method not allowed'], JSON_UNESCAPED_UNICODE);
  exit;
}

$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($user_id <= 0) {
  http_response_code(400);
  echo json_encode(['status' => 'error', 'message' => 'معرف المستخدم مطلوب'], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  require_once __DIR__ . '/../../../config/db.php';

  $stmt = $pdo->prepare("
        SELECT id, name, email, grade, role, is_banned, created_at, last_activity
        FROM users WHERE id = :user_id
    ");
  $stmt->execute([':user_id' => $user_id]);
  $user = $stmt->fetch();

  if (!$user) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'المستخدم مش موجود'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // Chat stats
  $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT c.id) as conversations_count,
            COUNT(DISTINCT m.id) as messages_count
        FROM conversations c
        LEFT JOIN messages m ON c.id = m.conversation_id
        WHERE c.user_id = :user_id
    ");
  $stmt->execute([':user_id' => $user_id]);
  $chat_stats = $stmt->fetch();

  // Exam stats
  $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_attempts,
            COUNT(CASE WHEN submitted = 1 THEN 1 END) as completed_exams
        FROM exam_results WHERE user_id = :user_id
    ");
  $stmt->execute([':user_id' => $user_id]);
  $exam_stats = $stmt->fetch();

  // Summary stats
  $stmt = $pdo->prepare("SELECT COUNT(*) as summaries_count FROM file_summaries WHERE user_id = :user_id");
  $stmt->execute([':user_id' => $user_id]);
  $summary_stats = $stmt->fetch();

  // Generated exams stats
  $stmt = $pdo->prepare("SELECT COUNT(*) as generated_exams_count FROM generated_exams WHERE user_id = :user_id");
  $stmt->execute([':user_id' => $user_id]);
  $generator_stats = $stmt->fetch();

  http_response_code(200);
  echo json_encode([
    'status' => 'success',
    'data' => [
      'user' => $user,
      'stats' => [
        'chat' => $chat_stats,
        'exams' => $exam_stats,
        'summaries' => $summary_stats,
        'generator' => $generator_stats
      ]
    ]
  ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
  error_log("[Admin User Details Error] " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['status' => 'error', 'message' => 'حصل خطأ'], JSON_UNESCAPED_UNICODE);
}

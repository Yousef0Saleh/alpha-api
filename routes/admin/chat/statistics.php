<?php
/**
 * Admin Chat Statistics API
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
            COUNT(DISTINCT c.id) as total_conversations,
            COUNT(DISTINCT m.id) as total_messages,
            COUNT(DISTINCT c.user_id) as active_users,
            COUNT(DISTINCT CASE WHEN DATE(c.created_at) = CURDATE() THEN c.id END) as today_conversations,
            COUNT(DISTINCT CASE WHEN DATE(m.created_at) = CURDATE() THEN m.id END) as today_messages
        FROM conversations c
        LEFT JOIN messages m ON c.id = m.conversation_id
    ");
  $stats = $stmt->fetch();

  // Recent Conversations
  $stmt = $pdo->query("
        SELECT 
            c.id,
            c.title,
            c.created_at,
            u.name as user_name,
            u.email as user_email,
            COUNT(m.id) as message_count
        FROM conversations c
        JOIN users u ON c.user_id = u.id
        LEFT JOIN messages m ON c.id = m.conversation_id
        GROUP BY c.id
        ORDER BY c.created_at DESC
        LIMIT 10
    ");
  $recent_conversations = $stmt->fetchAll();

  http_response_code(200);
  echo json_encode([
    'status' => 'success',
    'data' => array_merge($stats, ['recent_conversations' => $recent_conversations])
  ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
  error_log("[Admin Chat Stats Error] " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['status' => 'error', 'message' => 'حصل خطأ'], JSON_UNESCAPED_UNICODE);
}

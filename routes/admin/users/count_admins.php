<?php
/**
 * Admin Count API
 * Returns the total number of admin users in the system
 * Used to prevent unsafe admin demotions
 */

// CORS headers FIRST
require_once __DIR__ . '/../../../config/cors.php';
header('Content-Type: application/json; charset=UTF-8');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// Start session AFTER headers
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Check admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
  http_response_code(403);
  echo json_encode(['status' => 'error', 'message' => 'مش مصرح ليك'], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  require_once __DIR__ . '/../../../config/db.php';

  // Count total admins
  $stmt = $pdo->prepare("SELECT COUNT(*) as admin_count FROM users WHERE role = 'admin'");
  $stmt->execute();
  $result = $stmt->fetch(PDO::FETCH_ASSOC);

  http_response_code(200);
  echo json_encode([
    'status' => 'success',
    'data' => [
      'admin_count' => (int) $result['admin_count']
    ]
  ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
  error_log("[Admin Count Error] " . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'status' => 'error',
    'message' => 'حصل خطأ'
  ], JSON_UNESCAPED_UNICODE);
}

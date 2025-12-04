<?php
/**
 * Simple Test - No middleware
 */

// Start session
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Set CORS headers
header('Content-Type: application/json; charset=UTF-8');
require_once __DIR__ . '/../../../config/cors.php';
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// Simple response
echo json_encode([
  'status' => 'success',
  'message' => 'CORS working!',
  'session' => isset($_SESSION['user']) ? [
    'name' => $_SESSION['user']['name'],
    'role' => $_SESSION['user']['role'] ?? 'student'
  ] : null
], JSON_UNESCAPED_UNICODE);

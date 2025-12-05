<?php
/**
 * Check Authentication Status
 * Returns current user session information
 */

// CORS headers MUST come FIRST
require_once __DIR__ . '/../../config/cors.php';
header('Content-Type: application/json; charset=UTF-8');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// Start session
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Check if user is logged in
if (isset($_SESSION['user'])) {
  http_response_code(200);
  echo json_encode([
    'loggedIn' => true,
    'user' => [
      'id' => $_SESSION['user']['id'],
      'name' => $_SESSION['user']['name'],
      'email' => $_SESSION['user']['email'],
      'role' => $_SESSION['user']['role'],
      'grade' => $_SESSION['user']['grade'] ?? null
    ]
  ], JSON_UNESCAPED_UNICODE);
} else {
  http_response_code(200);
  echo json_encode([
    'loggedIn' => false,
    'user' => null
  ], JSON_UNESCAPED_UNICODE);
}

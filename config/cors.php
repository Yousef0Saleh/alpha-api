<?php
/**
 * Centralized CORS Configuration
 * Supports multiple allowed origins for development and production
 */

// Get the origin from the request
$origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';

// Build allowed origins from environment variables
$allowedOrigins = array_filter([
  getenv('FRONTEND_URL'),           // Production frontend URL
  getenv('FRONTEND_URL_DEV'),       // Development frontend URL
  // Development defaults (only if no env vars set)
  !getenv('FRONTEND_URL') && !getenv('FRONTEND_URL_DEV') ? 'http://192.168.1.5:3000' : null,
]);

// Check if the origin is in the allowed list
if (in_array($origin, $allowedOrigins)) {
  header("Access-Control-Allow-Origin: $origin");
} elseif (count($allowedOrigins) > 0) {
  // If we have allowed origins but request is from unknown origin, use the first allowed origin
  // This ensures CORS headers are always present but controlled
  header("Access-Control-Allow-Origin: " . reset($allowedOrigins));
} else {
  // No allowed origins configured - reject (production safety)
  http_response_code(403);
  error_log("[CORS] No allowed origins configured and request from: $origin");
  echo json_encode(['status' => 'error', 'message' => 'CORS configuration error']);
  exit;
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF-Token');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Max-Age: 86400'); // 24 hours

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

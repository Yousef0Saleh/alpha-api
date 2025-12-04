<?php
// routes/get_csrf.php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/cors.php';
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '192.168.1.5',
    'secure' => false, // true في الإنتاج مع HTTPS
    'httponly' => false, // false لأننا نحتاج قراءة التوكن من JS
    'samesite' => 'Lax',
]);
session_start();

try {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    echo json_encode(['status' => 'ok', 'csrf_token' => $_SESSION['csrf_token']]);
} catch (Exception $e) {
    error_log("[get_csrf ERROR] " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Unable to generate CSRF token']);
}

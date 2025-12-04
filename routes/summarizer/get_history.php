<?php
// routes/summarizer/get_history.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/cors.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/history_errors.log');

require_once __DIR__ . '/../../config/db.php';

// Authentication check
$user_id = $_SESSION['user']['id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'يجب تسجيل الدخول']);
    exit;
}

try {
    // Get last 10 summaries for user
    $stmt = $pdo->prepare("
        SELECT 
            id,
            filename,
            file_type,
            file_size,
            detail_level,
            summary_json,
            created_at
        FROM file_summaries
        WHERE user_id = ?
        ORDER BY created_at DESC
        LIMIT 10
    ");

    $stmt->execute([$user_id]);
    $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'history' => $history,
        'count' => count($history)
    ]);

} catch (PDOException $e) {
    error_log("[get_history] Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'خطا في قاعدة البيانات']);
} catch (Exception $e) {
    error_log("[get_history] Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'حدث خطا غير متوقع']);
}
?>

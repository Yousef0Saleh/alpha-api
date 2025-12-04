<?php
// alpha/backend/routes/chat/create_conversation.php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/cors.php';
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
    exit;

require_once __DIR__ . '/../../config/db.php';

// CSRF check
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit;
}

$user_id = $_SESSION['user']['id'] ?? null;

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$title = $input['title'] ?? 'محادثة جديدة';

try {
    $stmt = $pdo->prepare("INSERT INTO conversations (user_id, title) VALUES (?, ?)");
    $stmt->execute([$user_id, $title]);

    $conversation_id = $pdo->lastInsertId();

    echo json_encode([
        'status' => 'success',
        'conversation_id' => $conversation_id,
        'title' => $title
    ]);

} catch (PDOException $e) {
    error_log("[create_conversation] Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
?>

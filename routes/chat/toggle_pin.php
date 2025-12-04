<?php
// alpha/backend/routes/chat/toggle_pin.php
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
$conversation_id = intval($input['conversation_id'] ?? 0);

if (!$conversation_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing conversation_id']);
    exit;
}

try {
    // Verify ownership
    $stmt = $pdo->prepare("SELECT is_pinned FROM conversations WHERE id = ? AND user_id = ?");
    $stmt->execute([$conversation_id, $user_id]);
    $conv = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$conv) {
        echo json_encode(['status' => 'error', 'message' => 'Conversation not found']);
        exit;
    }

    $new_pinned = !$conv['is_pinned'];

    $stmt = $pdo->prepare("UPDATE conversations SET is_pinned = ? WHERE id = ?");
    $stmt->execute([$new_pinned, $conversation_id]);

    echo json_encode(['status' => 'success', 'is_pinned' => $new_pinned]);

} catch (PDOException $e) {
    error_log("[toggle_pin] Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
?>

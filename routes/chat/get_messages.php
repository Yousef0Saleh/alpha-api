<?php
// alpha/backend/routes/chat/get_messages.php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/cors.php';
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
    exit;

require_once __DIR__ . '/../../config/db.php';

$user_id = $_SESSION['user']['id'] ?? null;
$conversation_id = intval($_GET['conversation_id'] ?? 0);

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

if (!$conversation_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing conversation_id']);
    exit;
}

try {
    // Verify conversation belongs to user
    $stmt = $pdo->prepare("SELECT id FROM conversations WHERE id = ? AND user_id = ?");
    $stmt->execute([$conversation_id, $user_id]);

    if (!$stmt->fetch()) {
        echo json_encode(['status' => 'error', 'message' => 'Conversation not found']);
        exit;
    }

    // Get messages
    $stmt = $pdo->prepare("
        SELECT id, role, content, created_at 
        FROM messages 
        WHERE conversation_id = ? 
        ORDER BY created_at ASC
    ");
    $stmt->execute([$conversation_id]);

    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'status' => 'success',
        'messages' => $messages
    ]);

} catch (PDOException $e) {
    error_log("[get_messages] Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
?>

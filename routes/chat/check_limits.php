<?php
// alpha/backend/routes/chat/check_limits.php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/cors.php';
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
    exit;

require_once __DIR__ . '/../../config/db.php';

$user_id = $_SESSION['user']['id'] ?? null;

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

try {
    $today = date('Y-m-d');

    // Get or create usage record
    $stmt = $pdo->prepare("SELECT * FROM chat_usage_limits WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $usage = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usage) {
        $stmt = $pdo->prepare("INSERT INTO chat_usage_limits (user_id, messages_today, files_today, last_reset_date) VALUES (?, 0, 0, ?)");
        $stmt->execute([$user_id, $today]);
        $usage = ['messages_today' => 0, 'files_today' => 0, 'last_reset_date' => $today];
    } else {
        // Reset if new day
        if ($usage['last_reset_date'] !== $today) {
            $stmt = $pdo->prepare("UPDATE chat_usage_limits SET messages_today = 0, files_today = 0, last_reset_date = ? WHERE user_id = ?");
            $stmt->execute([$today, $user_id]);
            $usage['messages_today'] = 0;
            $usage['files_today'] = 0;
        }
    }

    echo json_encode([
        'status' => 'success',
        'limits' => [
            'messages_used' => intval($usage['messages_today']),
            'messages_limit' => 20,
            'files_used' => intval($usage['files_today']),
            'files_limit' => 3,
            'can_send_message' => intval($usage['messages_today']) < 20,
            'can_upload_file' => intval($usage['files_today']) < 3
        ]
    ]);

} catch (PDOException $e) {
    error_log("[check_limits] Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
?>

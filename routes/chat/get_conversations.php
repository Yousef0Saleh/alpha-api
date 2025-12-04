<?php
// alpha/backend/routes/chat/get_conversations.php
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

$search = $_GET['search'] ?? '';

try {
    if ($search) {
        $stmt = $pdo->prepare("
            SELECT id, title, created_at, updated_at, is_pinned 
            FROM conversations 
            WHERE user_id = ? AND title LIKE ? 
            ORDER BY is_pinned DESC, updated_at DESC
        ");
        $stmt->execute([$user_id, "%$search%"]);
    } else {
        $stmt = $pdo->prepare("
            SELECT id, title, created_at, updated_at, is_pinned 
            FROM conversations 
            WHERE user_id = ? 
            ORDER BY is_pinned DESC, updated_at DESC
        ");
        $stmt->execute([$user_id]);
    }

    $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by date
    $grouped = [
        'pinned' => [],
        'today' => [],
        'yesterday' => [],
        'this_week' => [],
        'older' => []
    ];

    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $week_ago = date('Y-m-d', strtotime('-7 days'));

    foreach ($conversations as $conv) {
        $date = date('Y-m-d', strtotime($conv['updated_at']));

        if ($conv['is_pinned']) {
            $grouped['pinned'][] = $conv;
        } elseif ($date === $today) {
            $grouped['today'][] = $conv;
        } elseif ($date === $yesterday) {
            $grouped['yesterday'][] = $conv;
        } elseif ($date >= $week_ago) {
            $grouped['this_week'][] = $conv;
        } else {
            $grouped['older'][] = $conv;
        }
    }

    echo json_encode([
        'status' => 'success',
        'conversations' => $grouped
    ]);

} catch (PDOException $e) {
    error_log("[get_conversations] Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
?>

<?php
// routes/save_progress.php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/cors.php';
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
    exit(0);

require_once __DIR__ . '/../../config/db.php';

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit;
}

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'سجل دخولك الاول']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$exam_id = intval($input['exam_id'] ?? 0);
$raw_answers = $input['answers_json'] ?? [];
$raw_actions = $input['actions_json'] ?? [];
$user_id = $_SESSION['user']['id'];

if (!$exam_id) {
    echo json_encode(['status' => 'error', 'message' => 'معرف الإختبار مش موجود']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT id, submitted FROM exam_results WHERE user_id = ? AND exam_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$user_id, $exam_id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        echo json_encode(['status' => 'error', 'message' => 'Exam not started']);
        exit;
    }

    if (intval($existing['submitted']) === 1) {
        echo json_encode(['status' => 'error', 'message' => 'الإختبار اتسلم فعلا']);
        exit;
    }

    $stmt2 = $pdo->prepare("SELECT questions_json FROM exams WHERE id = ?");
    $stmt2->execute([$exam_id]);
    $exam = $stmt2->fetch();

    if (!$exam) {
        echo json_encode(['status' => 'error', 'message' => 'الإختبار مش موجود']);
        exit;
    }

    $questions = json_decode($exam['questions_json'], true);
    $validatedAnswers = [];

    if (is_array($raw_answers) || is_object($raw_answers)) {
        foreach ($raw_answers as $qId => $optIndex) {
            $qIdInt = intval($qId);
            $found = null;
            foreach ($questions as $q) {
                if (isset($q['id']) && intval($q['id']) === $qIdInt) {
                    $found = $q;
                    break;
                }
            }
            if (!$found)
                continue;

            $optIdxInt = intval($optIndex);
            if (!isset($found['options']) || !is_array($found['options']))
                continue;
            if ($optIdxInt < 0 || $optIdxInt >= count($found['options']))
                continue;

            $validatedAnswers[$qIdInt] = $optIdxInt;
        }
    }

    $answers_json = json_encode($validatedAnswers, JSON_UNESCAPED_UNICODE);
    $actions_json = json_encode($raw_actions, JSON_UNESCAPED_UNICODE);

    $update = $pdo->prepare("UPDATE exam_results SET answers_json = ?, actions_json = ? WHERE id = ? AND submitted = 0");
    $update->execute([$answers_json, $actions_json, $existing['id']]);

    echo json_encode(['status' => 'success', 'message' => 'Progress saved']);

} catch (PDOException $e) {
    error_log("[save_progress DB ERROR] " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
?>

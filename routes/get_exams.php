<?php
session_start();

// إخفاء الأخطاء في Production
ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/cors.php';
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'سجل دخولك اولا'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user_id = $_SESSION['user']['id'];
$user_grade = $_SESSION['user']['grade'];

try {
    $stmt = $pdo->prepare("SELECT id, title, duration, questions_json FROM exams WHERE grade = ? ORDER BY id DESC");
    $stmt->execute([$user_grade]);
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];

    foreach ($exams as $exam) {
        $questions = json_decode($exam['questions_json'], true);
        $questions_count = is_array($questions) ? count($questions) : 0;

        $checkStmt = $pdo->prepare("
            SELECT id, start_time, submitted, answers_json 
            FROM exam_results 
            WHERE user_id = ? AND exam_id = ? 
            ORDER BY id DESC 
            LIMIT 1
        ");
        $checkStmt->execute([$user_id, $exam['id']]);
        $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

        $status = 'not_started';
        $score = null;
        $total_questions = null;

        if ($existing) {
            if (intval($existing['submitted']) === 1) {
                $status = 'completed';

                $answers = json_decode($existing['answers_json'], true);
                if (is_array($answers) && is_array($questions)) {
                    $correct = 0;
                    foreach ($questions as $q) {
                        if (!isset($q['id']) || !isset($q['correct_answer'])) {
                            continue;
                        }

                        $qid = $q['id'];
                        $correct_idx = $q['correct_answer'];

                        if (isset($answers[$qid]) && intval($answers[$qid]) === intval($correct_idx)) {
                            $correct++;
                        }
                    }
                    $score = $correct;
                    $total_questions = count($questions);
                }
            } else {
                $status = 'completed';
            }
        }

        $examData = [
            'id' => intval($exam['id']),
            'title' => $exam['title'],
            'duration' => intval($exam['duration']),
            'questions_count' => $questions_count,
            'exam_status' => $status,
        ];

        if ($status === 'completed' && $score !== null) {
            $examData['score'] = $score;
            $examData['total_questions'] = $total_questions;
        }

        $result[] = $examData;
    }

    echo json_encode([
        'status' => 'success',
        'exams' => $result
    ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    // Log للأخطاء بس مش هنظهرها للـ user
    error_log("[get_exams ERROR] " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'حدث خطأ، حاول مرة تانية'
    ], JSON_UNESCAPED_UNICODE);
}

?>

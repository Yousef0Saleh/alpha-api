<?php
/**
 * Admin Exam Statistics API
 */

// CORS headers FIRST
require_once __DIR__ . '/../../../config/cors.php';
header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}


// Session management
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'admin') {
  http_response_code(403);
  echo json_encode(['status' => 'error', 'message' => 'مش مصرح ليك'], JSON_UNESCAPED_UNICODE);
  exit;
}

$exam_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($exam_id <= 0) {
  http_response_code(400);
  echo json_encode(['status' => 'error', 'message' => 'معرف الامتحان مطلوب'], JSON_UNESCAPED_UNICODE);
  exit;
}

try {
  require_once __DIR__ . '/../../../config/db.php';

  $stmt = $pdo->prepare("SELECT id, title, duration, grade, questions_json FROM exams WHERE id = :exam_id");
  $stmt->execute([':exam_id' => $exam_id]);
  $exam = $stmt->fetch();

  if (!$exam) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'الامتحان مش موجود'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $questions = json_decode($exam['questions_json'], true);
  $questions_count = is_array($questions) ? count($questions) : 0;

  $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT user_id) as total_students,
            COUNT(*) as total_attempts,
            COUNT(CASE WHEN submitted = 1 THEN 1 END) as completed_count,
            COUNT(CASE WHEN submitted = 0 THEN 1 END) as in_progress_count
        FROM exam_results WHERE exam_id = :exam_id
    ");
  $stmt->execute([':exam_id' => $exam_id]);
  $general_stats = $stmt->fetch();

  $stmt = $pdo->prepare("
        SELECT er.answers_json, u.name as student_name, er.submitted_at
        FROM exam_results er
        JOIN users u ON er.user_id = u.id
        WHERE er.exam_id = :exam_id AND er.submitted = 1
        ORDER BY er.submitted_at DESC
    ");
  $stmt->execute([':exam_id' => $exam_id]);
  $results = $stmt->fetchAll();

  $scores = [];
  $question_stats = array_fill(0, $questions_count, ['correct' => 0, 'total' => 0]);

  foreach ($results as $result) {
    $answers = json_decode($result['answers_json'], true);
    $correct = 0;

    if (is_array($answers) && is_array($questions)) {
      foreach ($questions as $q) {
        $qid = $q['id'];
        $correct_answer = $q['correct_answer'] ?? null; // Support both key formats
        $q_index = $qid - 1;

        if ($q_index >= 0 && $q_index < $questions_count) {
          $question_stats[$q_index]['total']++;

          if (isset($answers[$qid]) && intval($answers[$qid]) === intval($correct_answer)) {
            $correct++;
            $question_stats[$q_index]['correct']++;
          }
        }
      }
    }

    $scores[] = [
      'student_name' => $result['student_name'],
      'score' => $correct,
      'submitted_at' => $result['submitted_at']
    ];
  }

  $score_values = array_map(fn($s) => $s['score'], $scores);
  $avg_score = count($score_values) > 0 ? array_sum($score_values) / count($score_values) : 0;
  $max_score = count($score_values) > 0 ? max($score_values) : 0;
  $min_score = count($score_values) > 0 ? min($score_values) : 0;

  $hard_questions = [];
  foreach ($question_stats as $index => $stat) {
    if ($stat['total'] > 0) {
      $success_rate = ($stat['correct'] / $stat['total']) * 100;
      $hard_questions[] = [
        'question_num' => $index + 1,
        'question_text' => $questions[$index]['question'] ?? '',
        'success_rate' => round($success_rate, 1)
      ];
    }
  }

  usort($hard_questions, fn($a, $b) => $a['success_rate'] <=> $b['success_rate']);
  $hardest_questions = array_slice($hard_questions, 0, 5);

  http_response_code(200);
  echo json_encode([
    'status' => 'success',
    'data' => [
      'exam' => [
        'id' => $exam['id'],
        'title' => $exam['title'],
        'duration' => $exam['duration'],
        'grade' => $exam['grade'],
        'questions_count' => $questions_count
      ],
      'general' => $general_stats,
      'scores' => [
        'average' => round($avg_score, 2),
        'max' => $max_score,
        'min' => $min_score,
        'total_questions' => $questions_count,
        'completion_rate' => $general_stats['total_attempts'] > 0
          ? round(($general_stats['completed_count'] / $general_stats['total_attempts']) * 100, 1)
          : 0
      ],
      'hardest_questions' => $hardest_questions,
      'recent_scores' => array_slice($scores, 0, 10)
    ]
  ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
  error_log("[Admin Exam Statistics Error] " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['status' => 'error', 'message' => 'حصل خطأ'], JSON_UNESCAPED_UNICODE);
}

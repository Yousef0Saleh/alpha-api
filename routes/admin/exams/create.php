<?php
/**
 * Admin Create Exam API - 4 options multiple choice only
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

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!is_array($data)) {
  http_response_code(400);
  echo json_encode(['status' => 'error', 'message' => 'Invalid JSON'], JSON_UNESCAPED_UNICODE);
  exit;
}

$title = isset($data['title']) ? trim($data['title']) : '';
$duration = isset($data['duration']) ? intval($data['duration']) : 0;
$grade = isset($data['grade']) ? trim($data['grade']) : '';
$questions = isset($data['questions']) && is_array($data['questions']) ? $data['questions'] : [];

if ($title === '' || $duration <= 0 || !in_array($grade, ['first', 'second', 'third']) || count($questions) === 0) {
  http_response_code(400);
  echo json_encode(['status' => 'error', 'message' => 'بيانات غير كاملة'], JSON_UNESCAPED_UNICODE);
  exit;
}

$validated_questions = [];
foreach ($questions as $index => $q) {
  $question_num = $index + 1;

  if (!isset($q['question']) || trim($q['question']) === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => "نص السؤال $question_num مطلوب"], JSON_UNESCAPED_UNICODE);
    exit;
  }

  if (!isset($q['options']) || !is_array($q['options']) || count($q['options']) !== 4) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => "السؤال $question_num لازم يكون له 4 اختيارات بالضبط"], JSON_UNESCAPED_UNICODE);
    exit;
  }

  foreach ($q['options'] as $opt_index => $option) {
    if (trim($option) === '') {
      http_response_code(400);
      echo json_encode(['status' => 'error', 'message' => "الاختيار " . ($opt_index + 1) . " في السؤال $question_num فاضي"], JSON_UNESCAPED_UNICODE);
      exit;
    }
  }

  if (!isset($q['correct_answer']) || !in_array($q['correct_answer'], [0, 1, 2, 3])) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => "الإجابة الصحيحة للسؤال $question_num غير صحيحة"], JSON_UNESCAPED_UNICODE);
    exit;
  }

  $validated_questions[] = [
    'id' => $index + 1,
    'question' => trim($q['question']),
    'options' => array_map('trim', $q['options']),
    'answer' => intval($q['correct_answer'])
  ];
}

try {
  require_once __DIR__ . '/../../../config/db.php';
  require_once __DIR__ . '/../middleware/check_admin.php';

  $stmt = $pdo->prepare("
        INSERT INTO exams (title, duration, grade, questions_json)
        VALUES (:title, :duration, :grade, :questions_json)
    ");

  $stmt->execute([
    ':title' => $title,
    ':duration' => $duration,
    ':grade' => $grade,
    ':questions_json' => json_encode($validated_questions, JSON_UNESCAPED_UNICODE)
  ]);

  $exam_id = $pdo->lastInsertId();

  // تسجيل النشاط
  $questions_count = count($validated_questions);
  logActivity(
    $pdo,
    'exam_created',
    "إنشاء امتحان جديد '$title' للصف '$grade' ($questions_count سؤال، $duration دقيقة)",
    'exam',
    $exam_id
  );

  http_response_code(201);
  echo json_encode([
    'status' => 'success',
    'message' => 'تم إنشاء الامتحان بنجاح',
    'exam_id' => $exam_id
  ], JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
  error_log("[Admin Create Exam Error] " . $e->getMessage());
  http_response_code(500);
  echo json_encode(['status' => 'error', 'message' => 'حصل خطأ'], JSON_UNESCAPED_UNICODE);
}

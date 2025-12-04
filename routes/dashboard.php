<?php
// Prevent any HTML output from errors
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

require_once __DIR__ . '/../config/cors.php';
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

// Start session
session_start();

// Check authentication using session
if (!isset($_SESSION['user'])) {
  http_response_code(401);
  echo json_encode(['error' => 'غير مصرح']);
  exit;
}

$userId = (int) $_SESSION['user']['id'];

// Get user role for potential future use
try {
  $stmt = $pdo->prepare("SELECT id, role FROM users WHERE id = ?");
  $stmt->execute([$userId]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$user) {
    http_response_code(403);
    echo json_encode(['error' => 'غير مصرح']);
    exit;
  }

  // Get dashboard statistics
  $stats = [];

  // 1. Total exams taken
  $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM exam_results WHERE user_id = ? AND submitted = 1");
  $stmt->execute([$userId]);
  $stats['exams_taken'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

  // 2. Average score
  $stmt = $pdo->prepare("
        SELECT AVG(
            CAST(JSON_UNQUOTE(JSON_EXTRACT(ai_analysis, '$.score.percentage')) AS DECIMAL(5,2))
        ) as avg_score
        FROM exam_results 
        WHERE user_id = ? 
          AND submitted = 1 
          AND ai_analysis IS NOT NULL
          AND JSON_EXTRACT(ai_analysis, '$.score.percentage') IS NOT NULL
    ");
  $stmt->execute([$userId]);
  $avgScore = $stmt->fetch(PDO::FETCH_ASSOC)['avg_score'];
  $stats['average_score'] = $avgScore ? round($avgScore, 2) : 0;

  // 3. Total study materials (summaries + generated exams)
  $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM file_summaries WHERE user_id = ?");
  $stmt->execute([$userId]);
  $summariesCount = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

  $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM generated_exams WHERE user_id = ?");
  $stmt->execute([$userId]);
  $generatedCount = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

  $stats['study_materials'] = $summariesCount + $generatedCount;

  // 4. Chat conversations count
  $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM conversations WHERE user_id = ?");
  $stmt->execute([$userId]);
  $stats['conversations'] = (int) $stmt->fetch(PDO::FETCH_ASSOC)['total'];

  // 5. Recent exam results (last 5 with performance data)
  $stmt = $pdo->prepare("
        SELECT 
            er.id,
            er.exam_id,
            er.submitted_at,
            e.title as exam_title,
            JSON_EXTRACT(er.ai_analysis, '$.score.percentage') as score_percentage,
            JSON_EXTRACT(er.ai_analysis, '$.score.correct') as correct_answers,
            JSON_EXTRACT(er.ai_analysis, '$.score.total') as total_questions
        FROM exam_results er
        LEFT JOIN exams e ON er.exam_id = e.id
        WHERE er.user_id = ? AND er.submitted = 1
        ORDER BY er.submitted_at DESC
        LIMIT 10
    ");
  $stmt->execute([$userId]);
  $recentExams = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Format recent exams
  $stats['recent_exams'] = array_map(function ($exam) {
    return [
      'id' => (int) $exam['id'],
      'exam_id' => (int) $exam['exam_id'],
      'exam_title' => $exam['exam_title'] ?? 'امتحان',
      'score' => $exam['score_percentage'] ? (float) $exam['score_percentage'] : 0,
      'correct' => $exam['correct_answers'] ? (int) $exam['correct_answers'] : 0,
      'total' => $exam['total_questions'] ? (int) $exam['total_questions'] : 0,
      'date' => $exam['submitted_at']
    ];
  }, $recentExams);

  // 6. Recent activities (mixed from different tables)
  $activities = [];

  // Recent summaries
  $stmt = $pdo->prepare("
        SELECT 'summary' as type, filename as title, created_at 
        FROM file_summaries 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 3
    ");
  $stmt->execute([$userId]);
  $activities = array_merge($activities, $stmt->fetchAll(PDO::FETCH_ASSOC));

  // Recent generated exams
  $stmt = $pdo->prepare("
        SELECT 'generated_exam' as type, filename as title, created_at 
        FROM generated_exams 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 3
    ");
  $stmt->execute([$userId]);
  $activities = array_merge($activities, $stmt->fetchAll(PDO::FETCH_ASSOC));

  // Recent conversations
  $stmt = $pdo->prepare("
        SELECT 'conversation' as type, title, created_at 
        FROM conversations 
        WHERE user_id = ? 
        ORDER BY created_at DESC 
        LIMIT 3
    ");
  $stmt->execute([$userId]);
  $activities = array_merge($activities, $stmt->fetchAll(PDO::FETCH_ASSOC));

  // Sort all activities by date
  usort($activities, function ($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
  });

  // Take only the most recent 4
  $stats['recent_activities'] = array_slice(array_map(function ($activity) {
    return [
      'type' => $activity['type'],
      'title' => $activity['title'],
      'date' => $activity['created_at']
    ];
  }, $activities), 0, 4);

  // 7. Performance trend (scores over time)
  $stats['performance_trend'] = array_values($stats['recent_exams']);

  echo json_encode([
    'success' => true,
    'data' => $stats
  ]);

} catch (PDOException $e) {
  error_log("Dashboard Error: " . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'error' => 'حدث خطأ في قاعدة البيانات',
    'debug' => $e->getMessage() // Temporary for debugging
  ]);
}

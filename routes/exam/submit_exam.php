<?php

// routes/submit_exam.php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/cors.php';
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
    exit(0);

require_once __DIR__ . '/../../config/db.php';

// CSRF check
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
    $pdo->beginTransaction();

    // Lock latest result
    $stmt = $pdo->prepare("SELECT id, start_time, submitted FROM exam_results WHERE user_id = ? AND exam_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $stmt->execute([$user_id, $exam_id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'Exam not started']);
        exit;
    }

    if (intval($existing['submitted']) === 1) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'الإختبار اتسلم فعلا']);
        exit;
    }

    // get exam details
    $stmt2 = $pdo->prepare("SELECT duration, questions_json FROM exams WHERE id = ?");
    $stmt2->execute([$exam_id]);
    $exam = $stmt2->fetch();

    if (!$exam) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'الإختبار مش موجود اصلا']);
        exit;
    }

    $start_time = strtotime($existing['start_time']);
    $duration_sec = intval($exam['duration']) * 60;
    $elapsed = time() - $start_time;

    // ✅ FIX #1: Grace period للتسليم (10 ثواني)
    // عشان لو في delay بسيط في الشبكة
    $grace_period = 10;

    if ($elapsed > ($duration_sec + $grace_period)) {
        // الوقت خلص بزيادة كبيرة = نسجل warning
        // لكن منرفضش التسليم عشان متضيعش إجابات الطالب
        error_log("[submit_exam WARNING] User $user_id submitted exam $exam_id after time expired (elapsed: $elapsed sec, duration: $duration_sec sec)");

        // نكمل التسليم عادي - مش نرفضه
    }

    // Server-side validation of answers against questions_json
    $questions = json_decode($exam['questions_json'], true);
    $validatedAnswers = [];

    if (is_array($raw_answers) || is_object($raw_answers)) {
        foreach ($raw_answers as $qId => $optIndex) {
            $qIdInt = intval($qId);
            // find question by id
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

    // update only if not submitted (avoid overwrite)
    $update = $pdo->prepare("UPDATE exam_results SET answers_json = ?, actions_json = ?, submitted = 1, submitted_at = NOW() WHERE id = ? AND submitted = 0");
    $update->execute([$answers_json, $actions_json, $existing['id']]);

    if ($update->rowCount() === 0) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'تعارض في الإرسال أو تم الإرسال بالفعل']);
        exit;
    }

    $pdo->commit();

    // ✅ نرجع success مع flag للتسليم المتأخر
    echo json_encode([
        'status' => 'success',
        'message' => 'تم التسليم',
        'late_submission' => $elapsed > $duration_sec // true لو التسليم كان بعد الوقت
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    error_log("[submit_exam DB ERROR] " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}
?>

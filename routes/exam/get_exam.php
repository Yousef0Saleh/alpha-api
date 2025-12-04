<?php

// routes/get_exam.php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/cors.php';
require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['user'])) {
    echo json_encode(['status' => 'error', 'message' => 'سجل دخولك اولا']);
    exit;
}

if (!isset($_GET['exam_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'معرف الاختبار مطلوب']);
    exit;
}

$exam_id = intval($_GET['exam_id']);
$user_id = $_SESSION['user']['id'];
$user_grade = $_SESSION['user']['grade'];

try {
    $stmt = $pdo->prepare("SELECT id, title, duration, questions_json, grade FROM exams WHERE id = ?");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch();
    if (!$exam) {
        echo json_encode(['status' => 'error', 'message' => 'الإختبار غير موجود']);
        exit;
    }

    if ($exam['grade'] != $user_grade) {
        echo json_encode(['status' => 'error', 'message' => 'الإختبار دا مش لصفك الدراسي']);
        exit;
    }

    $checkStmt = $pdo->prepare("SELECT id, start_time, submitted, ai_analysis, actions_json FROM exam_results WHERE user_id = ? AND exam_id = ? ORDER BY id DESC LIMIT 1");
    $checkStmt->execute([$user_id, $exam_id]);
    $existing = $checkStmt->fetch();

    $status = 'not_started';
    $ai_analysis = null;

    if ($existing) {
        if (intval($existing['submitted']) === 1) {
            $status = 'completed';

            if (!empty($existing['ai_analysis'])) {
                $ai_analysis = $existing['ai_analysis'];
            }
        } else {
            // FIX #4: فحص آخر نشاط (heartbeat)
            $actions = json_decode($existing['actions_json'], true);
            $last_activity = 0;

            if (is_array($actions) && count($actions) > 0) {
                // البحث عن آخر timestamp
                foreach ($actions as $action) {
                    if (isset($action['timestamp'])) {
                        $ts = intval($action['timestamp']) / 1000; // تحويل من milliseconds
                        if ($ts > $last_activity) {
                            $last_activity = $ts;
                        }
                    }
                }
            }

            $now = time();
            $inactive_duration = $now - $last_activity;

            // لو آخر نشاط كان قبل 5 دقايق = الصفحة معلقة
            if ($last_activity > 0 && $inactive_duration > 300) { // 5 minutes
                error_log("[get_exam] User $user_id exam $exam_id inactive for $inactive_duration seconds - auto-submitting");

                $stmt3 = $pdo->prepare("UPDATE exam_results SET submitted = 1, submitted_at = NOW() WHERE id = ?");
                $stmt3->execute([$existing['id']]);

                $status = 'in_progress'; // نخليه in_progress عشان الفرونت إند يعرف
            } else {
                // الامتحان لسه شغال فعلاً
                $stmt3 = $pdo->prepare("UPDATE exam_results SET submitted = 1, submitted_at = NOW() WHERE id = ?");
                $stmt3->execute([$existing['id']]);

                $status = 'completed';
            }
        }
    }

    $questions = json_decode($exam['questions_json'], true);
    if (!is_array($questions))
        $questions = [];

    $response = [
        'status' => 'success',
        'exam' => [
            'id' => $exam['id'],
            'title' => $exam['title'],
            'duration' => intval($exam['duration']),
            'questions' => $questions,
            'exam_status' => $status,
        ]
    ];

    if ($status === 'completed' && $ai_analysis !== null) {
        $response['exam']['ai_analysis'] = $ai_analysis;
    }

    echo json_encode($response);

} catch (PDOException $e) {
    error_log("[get_exam DB ERROR] " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}

?>
<?php

// routes/start_exam.php
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
$user_id = $_SESSION['user']['id'];

if (!$exam_id) {
    echo json_encode(['status' => 'error', 'message' => 'معرف الإختبار مش موجود']);
    exit;
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("SELECT id, duration FROM exams WHERE id = ?");
    $stmt->execute([$exam_id]);
    $exam = $stmt->fetch();
    if (!$exam) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'الإختبار مش موجود']);
        exit;
    }

    $check = $pdo->prepare("SELECT * FROM exam_results WHERE exam_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1 FOR UPDATE");
    $check->execute([$exam_id, $user_id]);
    $existing = $check->fetch();

    if ($existing) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => 'انت بدأت أو سلمت الإختبار دا قبل كدا']);
        exit;
    }

    $duration_sec = intval($exam['duration']) * 60;

    $insert = $pdo->prepare("INSERT INTO exam_results (exam_id, user_id, start_time, submitted, created_at) VALUES (?, ?, NOW(), 0, NOW())");
    $insert->execute([$exam_id, $user_id]);
    $newId = $pdo->lastInsertId();
    $pdo->commit();

    echo json_encode([
        'status' => 'started',
        'message' => 'Exam started',
        'time_left' => $duration_sec,
        'result_id' => $newId
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction())
        $pdo->rollBack();
    error_log("[start_exam DB ERROR] " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error']);
}

<?php
session_start();

header('Content-Type: application/json');
require_once __DIR__ . '/../config/cors.php';
if (isset($_SESSION['user'])) {
    session_destroy();
    echo json_encode(['status' => 'success', 'message' => 'تم تسجيل الخروج']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'انت مش مسجل دخول اساسا']);
}

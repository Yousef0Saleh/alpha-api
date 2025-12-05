<?php
require_once __DIR__ . '/../config/cors.php';
header("Content-Type: application/json; charset=UTF-8");

// Check remember me from request body BEFORE starting session
$rawInput = file_get_contents('php://input');
$tempData = json_decode($rawInput, true);
$rememberMe = isset($tempData['rememberMe']) && $tempData['rememberMe'] === true;

// Set session cookie params BEFORE session_start
if ($rememberMe) {
    session_set_cookie_params([
        'lifetime' => 30 * 24 * 60 * 60, // 30 days
        'path' => '/',
        'domain' => '',
        'secure' => false, // set to true if using HTTPS
        'httponly' => true,
        'samesite' => 'Lax'
    ]);
}

require_once __DIR__ . '/../config/db.php';
session_start();

$input = json_decode($rawInput, true);

if (!isset($input['email']) || !isset($input['password'])) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'من فضلك ادخل البريد الإلكتروني وكلمة المرور'
    ]);
    exit;
}

$email = trim($input['email']);
$password = $input['password'];

// التحقق من البريد الإلكتروني
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة'
    ]);
    exit;
}

// التحقق من الحظر
if ($user['is_banned']) {
    http_response_code(403);
    echo json_encode([
        'status' => 'error',
        'message' => 'تم حظر حسابك. يرجى التواصل مع الإدارة'
    ]);
    exit;
}

// التحقق من كلمة المرور
if (!password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode([
        'status' => 'error',
        'message' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة'
    ]);
    exit;
}

// تحديث آخر نشاط
$stmt = $pdo->prepare("UPDATE users SET last_activity = NOW() WHERE id = ?");
$stmt->execute([$user['id']]);

// إنشاء الـ session
$_SESSION['user'] = [
    'id' => $user['id'],
    'name' => $user['name'],
    'email' => $user['email'],
    'role' => $user['role'],
    'grade' => $user['grade']
];

// استجابة ناجحة
http_response_code(200);
echo json_encode([
    'status' => 'success',
    'message' => 'تم تسجيل الدخول بنجاح',
    'user' => $_SESSION['user']
]);

<?php
// alpha/backend/routes/chat/upload_file.php
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/cors.php';
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
    exit;

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/env.php';

// CSRF check
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit;
}

$user_id = $_SESSION['user']['id'] ?? null;

if (!$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'Not logged in']);
    exit;
}

$conversation_id = intval($_POST['conversation_id'] ?? 0);

if (!$conversation_id) {
    echo json_encode(['status' => 'error', 'message' => 'Missing conversation_id']);
    exit;
}

if (!isset($_FILES['file'])) {
    echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
    exit;
}

$file = $_FILES['file'];

// Check file size (max 10MB)
if ($file['size'] > 10 * 1024 * 1024) {
    echo json_encode(['status' => 'error', 'message' => 'الملف أكبر من 10 ميجا']);
    exit;
}

// Allowed types
$allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, $allowed_types)) {
    echo json_encode(['status' => 'error', 'message' => 'نوع الملف غير مسموح (PDF, JPG, PNG فقط)']);
    exit;
}

try {
    $today = date('Y-m-d');

    // Check file limit
    $stmt = $pdo->prepare("SELECT * FROM chat_usage_limits WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $usage = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usage) {
        $stmt = $pdo->prepare("INSERT INTO chat_usage_limits (user_id, messages_today, files_today, last_reset_date) VALUES (?, 0, 0, ?)");
        $stmt->execute([$user_id, $today]);
        $usage = ['files_today' => 0, 'last_reset_date' => $today];
    } else {
        if ($usage['last_reset_date'] !== $today) {
            $stmt = $pdo->prepare("UPDATE chat_usage_limits SET files_today = 0, last_reset_date = ? WHERE user_id = ?");
            $stmt->execute([$today, $user_id]);
            $usage['files_today'] = 0;
        }
    }

    if (intval($usage['files_today']) >= 3) {
        echo json_encode(['status' => 'error', 'message' => 'وصلت للحد اليومي (3 ملفات). ارجع بكرة!']);
        exit;
    }

    // Save file locally
    $upload_dir = __DIR__ . '/../../uploads/chat/';
    if (!is_dir($upload_dir))
        mkdir($upload_dir, 0755, true);

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('chat_') . '.' . $ext;
    $filepath = $upload_dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $filepath)) {
        echo json_encode(['status' => 'error', 'message' => 'فشل حفظ الملف']);
        exit;
    }

    // Upload to Gemini Files API
    $apiKey = getenv('GEMINI_API_KEY');
    $gemini_uri = null;

    if ($apiKey) {
        $upload_url = "https://generativelanguage.googleapis.com/upload/v1beta/files?key=$apiKey";

        $ch = curl_init($upload_url);
        $cfile = new CURLFile($filepath, $mime, $file['name']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: multipart/form-data']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, ['file' => $cfile]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            $result = json_decode($response, true);
            if (isset($result['file']['uri'])) {
                $gemini_uri = $result['file']['uri'];
            }
        }
    }

    // Save to database
    $stmt = $pdo->prepare("
        INSERT INTO chat_file_uploads 
        (user_id, conversation_id, file_name, file_path, file_type, file_size, gemini_uri) 
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $user_id,
        $conversation_id,
        $file['name'],
        $filename,
        $mime,
        $file['size'],
        $gemini_uri
    ]);

    // Increment usage
    $stmt = $pdo->prepare("UPDATE chat_usage_limits SET files_today = files_today + 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);

    echo json_encode([
        'status' => 'success',
        'file_id' => $pdo->lastInsertId(),
        'file_name' => $file['name'],
        'gemini_uri' => $gemini_uri
    ]);

} catch (Exception $e) {
    error_log("[upload_file] Error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Server error']);
}
?>

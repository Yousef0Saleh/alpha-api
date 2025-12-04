<?php
// routes/summarizer/summarize_file.php

// Start session and handle CORS
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/cors.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Production error handling - no sensitive data exposure
error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/summarize_errors.log');

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/env.php';

// CSRF validation
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'طلب غير صالح']);
    exit;
}

// Authentication check
$user_id = $_SESSION['user']['id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'يجب تسجيل الدخول']);
    exit;
}

// File upload validation
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'فشل رفع الملف']);
    exit;
}

$file = $_FILES['file'];
$detail_level = $_POST['detail_level'] ?? 'medium';
$target_length = intval($_POST['target_length'] ?? 200);

// Validate detail level
$allowed_levels = ['short', 'medium', 'detailed'];
if (!in_array($detail_level, $allowed_levels)) {
    $detail_level = 'medium';
}

// Validate target length
if ($target_length < 50)
    $target_length = 50;
if ($target_length > 500)
    $target_length = 500;

// File type validation
$allowed_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
$file_type = mime_content_type($file['tmp_name']);

if (!in_array($file_type, $allowed_types)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'نوع الملف غير مدعوم']);
    exit;
}

// File size validation (10MB max)
$max_size = 10 * 1024 * 1024;
if ($file['size'] > $max_size) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'حجم الملف كبير جدا']);
    exit;
}

// Sanitize filename
$original_filename = basename($file['name']);
$safe_filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $original_filename);

try {
    // Read and encode file
    $file_content = file_get_contents($file['tmp_name']);
    if ($file_content === false) {
        throw new Exception('Failed to read file');
    }

    $base64_content = base64_encode($file_content);

    // Determine MIME type for Gemini
    $gemini_mime_type = $file_type;
    if ($file_type === 'image/jpg') {
        $gemini_mime_type = 'image/jpeg';
    }

    // Detail level instructions
    $detail_instructions = [
        'short' => 'قدم ملخص قصير ومركز جدا (3-5 نقاط رئيسية فقط)',
        'medium' => 'قدم ملخص متوسط متوازن (5-8 نقاط رئيسية مع تفاصيل معقولة)',
        'detailed' => 'قدم ملخص مفصل وشامل (8-12 نقطة رئيسية مع شرح وافي)'
    ];

    // AI Prompt
    $prompt = <<<EOT
انت مساعد ذكي متخصص في تلخيص المحتوى التعليمي والاكاديمي فقط.

**خطوة 1: التحقق من المحتوى (CRITICAL)**
قبل التلخيص، تأكد ان الملف يحتوي على محتوى تعليمي/دراسي فقط مثل:
✓ كتب او مذكرات دراسية
✓ محاضرات او شروحات اكاديمية
✓ ملخصات علمية او بحثية
✓ مواد تعليمية
✓ نصوص دراسية
✓ ابحاث علمية
✓ مقالات اكاديمية

**محتوى غير مقبول (يجب رفضه فورا):**
✗ صور شخصية (سيلفي، وجوه، اشخاص بدون نص)
✗ صور عائلية او اجتماعية
✗ صور طعام او حيوانات بدون سياق علمي
✗ لقطات شاشة لمحادثات او سوشيال ميديا
✗ فواتير او وثائق شخصية
✗ اعلانات او محتوى تسويقي
✗ اي محتوى غير تعليمي او اكاديمي

**اذا كان المحتوى غير تعليمي:**
ارجع: {"error": "محتوى غير تعليمي", "message": "الملف لا يحتوي على محتوى دراسي. ارفع ملف يحتوي على مادة تعليمية او اكاديمية"}

**خطوة 2: التلخيص**
اذا كان المحتوى تعليمي صالح:

مستوى التفصيل المطلوب: {$detail_instructions[$detail_level]}
الطول المستهدف: حوالي $target_length كلمة (تقريبا، لا تلتزم بدقة شديدة)

التعليمات:
1. اقرا وافهم كل المحتوى الدراسي بدقة
2. استخرج النقاط الرئيسية التعليمية بشكل منظم
3. قدم ملخص تفصيلي يغطي كل المفاهيم المهمة
4. حدد الاستنتاجات والدروس المستفادة الاكاديمية
5. اذكر المواضيع الدراسية المغطاة في المحتوى
6. استخدم العامية المصرية في كل الردود

متطلبات مهمة:
- كن دقيق وشامل في التحليل الاكاديمي
- نظم المعلومات الدراسية بشكل واضح ومنطقي
- ركز على الافكار الاساسية والمفاهيم العلمية
- تجنب التكرار والمعلومات غير المفيدة
- استخدم لغة بسيطة وواضحة

الرد المطلوب (JSON فقط بدون اي تنسيق markdown):
{
  "main_points": [
    "النقطة الاولى التعليمية بالعامية المصرية",
    "النقطة الثانية التعليمية بالعامية المصرية",
    "النقطة الثالثة التعليمية بالعامية المصرية"
  ],
  "detailed_summary": "ملخص تفصيلي كامل للمحتوى الدراسي بالعامية المصرية",
  "key_takeaways": [
    "استنتاج او درس علمي مستفاد بالعامية المصرية",
    "استنتاج اكاديمي اخر بالعامية المصرية"
  ],
  "topics_covered": [
    "موضوع دراسي اول",
    "موضوع علمي تاني",
    "موضوع اكاديمي تالت"
  ]
}

ملاحظة مهمة: ارجع JSON صالح فقط بدون اي تنسيق markdown
EOT;

    // Get API key
    $apiKey = getenv('GEMINI_API_KEY');
    if (!$apiKey) {
        error_log("[summarize_file] API key not configured");
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'خطا في اعدادات النظام']);
        exit;
    }

    // Gemini API endpoint
    $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key=$apiKey";

    $postData = [
        "contents" => [
            [
                "parts" => [
                    [
                        "inline_data" => [
                            "mime_type" => $gemini_mime_type,
                            "data" => $base64_content
                        ]
                    ],
                    ["text" => $prompt]
                ]
            ]
        ],
        "generationConfig" => [
            "temperature" => 0.4,
            "topK" => 40,
            "topP" => 0.95,
            "maxOutputTokens" => 8192,
            "responseMimeType" => "application/json"
        ]
    ];

    // Call Gemini API
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError) {
        error_log("[summarize_file] CURL error: " . $curlError);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'فشل الاتصال بخدمة الذكاء الاصطناعي']);
        exit;
    }

    if ($httpCode !== 200) {
        error_log("[summarize_file] API error: HTTP $httpCode");
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'خطا في خدمة الذكاء الاصطناعي']);
        exit;
    }

    $ai_result = json_decode($response, true);

    if (!isset($ai_result['candidates'][0]['content']['parts'][0]['text'])) {
        error_log("[summarize_file] Invalid API response structure");
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'رد غير صالح من الذكاء الاصطناعي']);
        exit;
    }

    $raw_output = $ai_result['candidates'][0]['content']['parts'][0]['text'];

    // Clean markdown code blocks
    $raw_output = preg_replace('/```json\s*/', '', $raw_output);
    $raw_output = preg_replace('/```\s*$/', '', $raw_output);
    $raw_output = trim($raw_output);

    $parsed = json_decode($raw_output, true);

    if (!$parsed || !isset($parsed['main_points']) || !isset($parsed['detailed_summary'])) {
        error_log("[summarize_file] JSON parse failed or invalid structure");
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'فشل تحليل نتيجة الذكاء الاصطناعي']);
        exit;
    }

    // Prepare result
    $result = [
        'original_filename' => $safe_filename,
        'file_type' => $file_type === 'application/pdf' ? 'PDF' : 'صورة',
        'file_size' => number_format($file['size'] / 1024, 2) . ' كيلوبايت',
        'summary' => $parsed,
        'metadata' => [
            'language' => 'العربية',
            'detail_level' => $detail_level,
            'target_length' => $target_length
        ]
    ];

    // Save to database
    try {
        $stmt = $pdo->prepare("
            INSERT INTO file_summaries 
            (user_id, filename, file_type, file_size, detail_level, summary_json, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $user_id,
            $safe_filename,
            $file_type,
            $file['size'],
            $detail_level,
            json_encode($parsed, JSON_UNESCAPED_UNICODE)
        ]);
    } catch (PDOException $e) {
        error_log("[summarize_file] Database save error: " . $e->getMessage());
        // Don't fail the request if DB save fails
    }

    // Success response
    http_response_code(200);
    echo json_encode(['status' => 'success', 'result' => $result]);

} catch (Exception $e) {
    error_log("[summarize_file] Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'حدث خطا غير متوقع']);
}
?>

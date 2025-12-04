<?php
// routes/exam_generator/generate_exam.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/cors.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/exam_generator_errors.log');

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
$question_count = intval($_POST['question_count'] ?? 10);
$difficulty = $_POST['difficulty'] ?? 'mixed';
$question_types_json = $_POST['question_types'] ?? '["mcq","true_false"]';
$question_types = json_decode($question_types_json, true);

// Validate parameters
if ($question_count < 5 || $question_count > 50) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'عدد الاسئلة يجب ان يكون بين 5 و 50']);
    exit;
}

$allowed_difficulties = ['easy', 'medium', 'hard', 'mixed'];
if (!in_array($difficulty, $allowed_difficulties)) {
    $difficulty = 'mixed';
}

$allowed_types = ['mcq', 'true_false', 'short_answer', 'essay'];
$question_types = array_intersect($question_types, $allowed_types);
if (empty($question_types)) {
    $question_types = ['mcq', 'true_false'];
}

// File type validation
$allowed_file_types = ['application/pdf', 'image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
$file_type = mime_content_type($file['tmp_name']);

if (!in_array($file_type, $allowed_file_types)) {
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

    // Build difficulty instruction
    $difficulty_instruction = '';
    if ($difficulty === 'easy') {
        $difficulty_instruction = 'اجعل كل الاسئلة سهلة ومباشرة';
    } elseif ($difficulty === 'medium') {
        $difficulty_instruction = 'اجعل كل الاسئلة متوسطة الصعوبة';
    } elseif ($difficulty === 'hard') {
        $difficulty_instruction = 'اجعل كل الاسئلة صعبة وتحتاج تفكير عميق';
    } else {
        $difficulty_instruction = 'نوع في صعوبة الاسئلة بين سهل ومتوسط وصعب';
    }

    // Build question types instruction
    $types_labels = [
        'mcq' => 'اختيار من متعدد (4 خيارات)',
        'true_false' => 'صح او خطأ',
        'short_answer' => 'اجابة قصيرة',
        'essay' => 'اسئلة مقالية'
    ];
    $selected_types = array_map(function ($t) use ($types_labels) {
        return $types_labels[$t] ?? $t;
    }, $question_types);
    $types_instruction = 'انواع الاسئلة المطلوبة: ' . implode(', ', $selected_types);

    // AI Prompt with content validation
    $prompt = <<<EOT
انت مدرس خبير متخصص في اعداد الامتحانات التعليمية. مهمتك تحليل المحتوى الدراسي وانشاء امتحان شامل ودقيق.

**خطوة 1: التحقق من المحتوى (CRITICAL)**
قبل توليد الاسئلة، تأكد ان الملف يحتوي على محتوى تعليمي/دراسي فقط مثل:
✓ كتب او مذكرات دراسية
✓ محاضرات او شروحات
✓ ملخصات علمية
✓ مواد اكاديمية
✓ نصوص تعليمية

**محتوى غير مقبول (يجب رفضه فورا):**
✗ صور شخصية (سيلفي، وجوه، اشخاص بدون نص)
✗ صور عائلية او اجتماعية
✗ صور طعام او حيوانات بدون سياق علمي
✗ لقطات شاشة لمحادثات او سوشيال ميديا
✗ اي محتوى غير تعليمي

**اذا كان المحتوى غير تعليمي:**
ارجع: {"error": "المحتوى غير تعليمي", "message": "الملف لا يحتوي على محتوى دراسي. ارفع ملف يحتوي على مادة تعليمية"}

**خطوة 2: توليد الامتحان**
اذا كان المحتوى تعليمي صالح:

متطلبات الامتحان:
- عدد الاسئلة: {$question_count} سؤال
- $difficulty_instruction
- $types_instruction
- تأكد ان كل الاسئلة من المحتوى الموجود في الملف
- اجعل الاسئلة متنوعة وتغطي كل اجزاء المحتوى
- لكل سؤال اختيار من متعدد، قدم 4 خيارات معقولة
- اضف توضيح بسيط لكل سؤال يشرح الاجابة

تعليمات مهمة:
1. اقرا وافهم كل المحتوى بدقة
2. ولد اسئلة متنوعة تغطي كل المواضيع
3. اجعل الاسئلة واضحة ومباشرة
4. تأكد من دقة الاجابات
5. وزع الصعوبة حسب المطلوب

صيغة الرد (JSON فقط):
{
  "questions": [
    {
      "type": "mcq",
      "question": "السؤال",
      "options": ["خيار 1", "خيار 2", "خيار 3", "خيار 4"],
      "correct_answer": "الخيار الصحيح",
      "explanation": "توضيح الاجابة",
      "difficulty": "easy"
    }
  ],
  "subject_area": "المادة او المجال"
}

ملاحظة: ارجع JSON صالح فقط بدون markdown
EOT;

    // Get API key
    $apiKey = getenv('GEMINI_API_KEY');
    if (!$apiKey) {
        error_log("[generate_exam] API key not configured");
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
            "temperature" => 0.7,
            "topK" => 40,
            "topP" => 0.95,
            "maxOutputTokens" => 8192,
            "responseMimeType" => "application/json"
        ],
        "safetySettings" => [
            [
                "category" => "HARM_CATEGORY_HARASSMENT",
                "threshold" => "BLOCK_MEDIUM_AND_ABOVE"
            ],
            [
                "category" => "HARM_CATEGORY_HATE_SPEECH",
                "threshold" => "BLOCK_MEDIUM_AND_ABOVE"
            ],
            [
                "category" => "HARM_CATEGORY_SEXUALLY_EXPLICIT",
                "threshold" => "BLOCK_MEDIUM_AND_ABOVE"
            ],
            [
                "category" => "HARM_CATEGORY_DANGEROUS_CONTENT",
                "threshold" => "BLOCK_MEDIUM_AND_ABOVE"
            ]
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
        error_log("[generate_exam] CURL error: " . $curlError);
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'فشل الاتصال بخدمة الذكاء الاصطناعي']);
        exit;
    }

    if ($httpCode !== 200) {
        error_log("[generate_exam] API error: HTTP $httpCode - Response: " . substr($response, 0, 500));
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'خطا في خدمة الذكاء الاصطناعي']);
        exit;
    }

    $ai_result = json_decode($response, true);

    if (!isset($ai_result['candidates'][0]['content']['parts'][0]['text'])) {
        error_log("[generate_exam] Invalid API response structure");
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

    // Check if content was rejected
    if (isset($parsed['error'])) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => $parsed['message'] ?? 'المحتوى غير مناسب لتوليد امتحان'
        ]);
        exit;
    }

    if (!$parsed || !isset($parsed['questions']) || !is_array($parsed['questions'])) {
        error_log("[generate_exam] JSON parse failed or invalid structure");
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'فشل تحليل نتيجة الذكاء الاصطناعي']);
        exit;
    }

    // Validate questions structure
    $valid_questions = [];
    foreach ($parsed['questions'] as $q) {
        if (isset($q['question']) && isset($q['correct_answer']) && isset($q['type'])) {
            // Ensure options exist for MCQ
            if ($q['type'] === 'mcq' && (!isset($q['options']) || count($q['options']) < 2)) {
                continue;
            }
            $valid_questions[] = $q;
        }
    }

    if (empty($valid_questions)) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'فشل توليد اسئلة صالحة']);
        exit;
    }

    // Prepare result
    $question_type_counts = array_count_values(array_column($valid_questions, 'type'));
    $difficulty_counts = array_count_values(array_filter(array_column($valid_questions, 'difficulty')));

    $result = [
        'original_filename' => $safe_filename,
        'file_type' => $file_type === 'application/pdf' ? 'PDF' : 'صورة',
        'questions' => $valid_questions,
        'exam_info' => [
            'total_questions' => count($valid_questions),
            'question_types' => array_keys($question_type_counts),
            'difficulty_levels' => array_keys($difficulty_counts),
            'subject_area' => $parsed['subject_area'] ?? 'غير محدد'
        ]
    ];

    // Save to database
    try {
        $stmt = $pdo->prepare("
            INSERT INTO generated_exams 
            (user_id, filename, file_type, question_count, difficulty, question_types, exam_json, created_at) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $user_id,
            $safe_filename,
            $file_type,
            count($valid_questions),
            $difficulty,
            json_encode($question_types),
            json_encode($valid_questions, JSON_UNESCAPED_UNICODE)
        ]);
    } catch (PDOException $e) {
        error_log("[generate_exam] Database save error: " . $e->getMessage());
        // Don't fail the request if DB save fails
    }

    // Success response
    http_response_code(200);
    echo json_encode(['status' => 'success', 'result' => $result]);

} catch (Exception $e) {
    error_log("[generate_exam] Exception: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'حدث خطا غير متوقع']);
}
?>

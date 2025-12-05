<?php

// alpha/backend/routes/chat/send_message.php
session_start();
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
require_once __DIR__ . '/../../config/cors.php';
header('X-Accel-Buffering: no');
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/env.php';

// Disable ALL output buffering
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', false);
@ini_set('implicit_flush', true);
@ob_end_clean();
while (@ob_end_flush())
    ;

$user_id = $_SESSION['user']['id'] ?? null;

if (!$user_id) {
    echo "data: " . json_encode(['error' => 'Not logged in']) . "\n\n";
    flush();
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$conversation_id = intval($input['conversation_id'] ?? 0);
$user_message = trim($input['message'] ?? '');

if (!$conversation_id || !$user_message) {
    echo "data: " . json_encode(['error' => 'Missing data']) . "\n\n";
    flush();
    exit;
}

try {
    $today = date('Y-m-d');

    // Check limits
    $stmt = $pdo->prepare("SELECT * FROM chat_usage_limits WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $usage = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$usage) {
        $stmt = $pdo->prepare("INSERT INTO chat_usage_limits (user_id, messages_today, last_reset_date) VALUES (?, 0, ?)");
        $stmt->execute([$user_id, $today]);
        $usage = ['messages_today' => 0, 'last_reset_date' => $today];
    } else {
        if ($usage['last_reset_date'] !== $today) {
            $stmt = $pdo->prepare("UPDATE chat_usage_limits SET messages_today = 0, last_reset_date = ? WHERE user_id = ?");
            $stmt->execute([$today, $user_id]);
            $usage['messages_today'] = 0;
        }
    }

    if (intval($usage['messages_today']) >= 20) {
        echo "data: " . json_encode(['error' => 'وصلت للحد اليومي (20 رسالة). ارجع بكرة!']) . "\n\n";
        flush();
        exit;
    }

    // Verify conversation
    $stmt = $pdo->prepare("SELECT title FROM conversations WHERE id = ? AND user_id = ?");
    $stmt->execute([$conversation_id, $user_id]);
    $conv = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$conv) {
        echo "data: " . json_encode(['error' => 'Conversation not found']) . "\n\n";
        flush();
        exit;
    }

    // Save user message
    $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, role, content) VALUES (?, 'user', ?)");
    $stmt->execute([$conversation_id, $user_message]);

    // Auto-generate title if first message
    if ($conv['title'] === 'محادثة جديدة') {
        $auto_title = mb_substr($user_message, 0, 50) . (mb_strlen($user_message) > 50 ? '...' : '');
        $stmt = $pdo->prepare("UPDATE conversations SET title = ? WHERE id = ?");
        $stmt->execute([$auto_title, $conversation_id]);
    }

    // Get conversation history (last 10 messages for context)
    $stmt = $pdo->prepare("SELECT role, content FROM messages WHERE conversation_id = ? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$conversation_id]);
    $history = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

    // Build Gemini messages
    $messages = [];
    foreach ($history as $msg) {
        $messages[] = [
            'role' => $msg['role'] === 'assistant' ? 'model' : 'user',
            'parts' => [['text' => $msg['content']]]
        ];
    }

    // System instruction
    $system_instruction = "أنت مساعد دراسي ذكي اسمك 'مساعد الدراسة'. متخصص في مساعدة الطلاب المصريين.
- تجاوب دايمًا بالعربية المصرية (العامية).
- تشرح بطريقة بسيطة وواضحة مع أمثلة عملية.
- تساعد في فهم المواد الدراسية وحل المسائل وشرح المفاهيم.
- ممنوع تساعد في الغش أو حل امتحانات حية.
- لو الطالب سألك سؤال مش دراسي، وجهه بلطف للموضوعات الدراسية.
- استخدم Markdown للتنسيق: **bold**, *italic*, `code`, lists, etc.
- ملاحظة مهمة: مقدرش أشوف الصور أو الملفات. لو الطالب بيسأل عن صورة أو ملف، اطلب منه يكتب المحتوى نص.";

    $apiKey = getenv('GEMINI_API_KEY');
    if (!$apiKey) {
        echo "data: " . json_encode(['error' => 'API key not configured']) . "\n\n";
        flush();
        exit;
    }

    // Models to try
    $models_to_try = [
        'gemini-2.5-flash',
        'gemini-2.0-flash'
    ];

    $full_response = '';
    $success = false;

    foreach ($models_to_try as $model) {
        if ($success)
            break;

        $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:streamGenerateContent?key=$apiKey&alt=sse";

        $postData = [
            'contents' => $messages,
            'systemInstruction' => [
                'parts' => [['text' => $system_instruction]]
            ],
            'generationConfig' => [
                'temperature' => 0.8,
                'topK' => 40,
                'topP' => 0.95,
                'maxOutputTokens' => 2048
            ]
        ];

        // Test first
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $test_response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // If test failed, try next model
        if ($httpCode !== 200) {
            error_log("[send_message] Model $model unavailable (HTTP: $httpCode)");
            continue;
        }

        // If test passed, do streaming
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $buffer = '';
        $temp_response = '';

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($curl, $data) use (&$temp_response, &$buffer, &$success) {
            $buffer .= $data;
            $lines = explode("\n", $buffer);

            if (substr($data, -1) !== "\n") {
                $buffer = array_pop($lines);
            } else {
                $buffer = '';
            }

            foreach ($lines as $line) {
                $line = trim($line);
                if (empty($line))
                    continue;

                if (strpos($line, 'data: ') === 0) {
                    $json_str = substr($line, 6);
                    $parsed = @json_decode($json_str, true);

                    if (isset($parsed['candidates'][0]['content']['parts'][0]['text'])) {
                        $chunk = $parsed['candidates'][0]['content']['parts'][0]['text'];
                        $temp_response .= $chunk;
                        $success = true;

                        echo "data: " . json_encode(['chunk' => $chunk], JSON_UNESCAPED_UNICODE) . "\n\n";

                        if (ob_get_level() > 0) {
                            ob_flush();
                        }
                        flush();
                    }
                }
            }

            return strlen($data);
        });

        curl_exec($ch);
        curl_close($ch);

        if ($success && !empty($temp_response)) {
            $full_response = $temp_response;
            error_log("[send_message] ✅ Success with model: $model");
            break;
        }
    }

    // If all models failed
    if (empty($full_response)) {
        $full_response = "عذرًا، حصل خطأ في الاتصال بالـ AI. جرب تاني بعد شوية.";
        echo "data: " . json_encode(['chunk' => $full_response], JSON_UNESCAPED_UNICODE) . "\n\n";
        flush();
    }

    // Save assistant message
    $stmt = $pdo->prepare("INSERT INTO messages (conversation_id, role, content) VALUES (?, 'assistant', ?)");
    $stmt->execute([$conversation_id, $full_response]);

    // Update conversation timestamp
    $stmt = $pdo->prepare("UPDATE conversations SET updated_at = CURRENT_TIMESTAMP WHERE id = ?");
    $stmt->execute([$conversation_id]);

    // Increment usage
    $stmt = $pdo->prepare("UPDATE chat_usage_limits SET messages_today = messages_today + 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);

    // Send done signal
    echo "data: " . json_encode(['done' => true]) . "\n\n";
    flush();

} catch (Exception $e) {
    error_log("[send_message] Error: " . $e->getMessage());
    echo "data: " . json_encode(['error' => 'حصل خطأ، جرب تاني'], JSON_UNESCAPED_UNICODE) . "\n\n";
    flush();
}

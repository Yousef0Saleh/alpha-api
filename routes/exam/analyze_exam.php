<?php
// routes/analyze_exam.php - FIXED VERSION
session_start();
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../../config/cors.php';
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
    exit;

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/env.php';

// ============================
// CONFIGURATION
// ============================

$AI_MODELS = [
    'gemini-2.5-flash',
    'gemini-2.0-flash'
];

$MAX_RETRIES_PER_MODEL = 3;
$RETRY_DELAY_SECONDS = 2;

// ============================
// HELPER FUNCTIONS
// ============================

function testModel($apiKey, $modelName)
{
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key=$apiKey";

    $testData = [
        "contents" => [
            ["parts" => [["text" => "test"]]]
        ],
        "generationConfig" => [
            "maxOutputTokens" => 10
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return [
        'available' => empty($curlError) && $httpCode === 200,
        'httpCode' => $httpCode,
        'error' => $curlError
    ];
}

function callGeminiAPI($apiKey, $modelName, $prompt)
{
    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key=$apiKey";

    $postData = [
        "contents" => [
            [
                "parts" => [
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
        ]
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return [
        'success' => empty($curlError) && $httpCode === 200,
        'response' => $response,
        'httpCode' => $httpCode,
        'error' => $curlError
    ];
}

function parseAIResponse($rawResponse)
{
    $ai_result = json_decode($rawResponse, true);

    if (!isset($ai_result['candidates'][0]['content']['parts'][0]['text'])) {
        return [
            'success' => false,
            'error' => 'Invalid response structure',
            'debug' => 'Missing text in candidates'
        ];
    }

    $raw_output = $ai_result['candidates'][0]['content']['parts'][0]['text'];

    // Clean markdown code blocks
    $raw_output = preg_replace('/```json\s*/', '', $raw_output);
    $raw_output = preg_replace('/```\s*$/', '', $raw_output);
    $raw_output = trim($raw_output);

    $parsed = json_decode($raw_output, true);

    if (!$parsed) {
        return [
            'success' => false,
            'error' => 'JSON parse failed',
            'raw_output' => $raw_output
        ];
    }

    // Validate required fields
    $required_fields = ['score', 'behavior_analysis', 'recommendations', 'overall_summary'];
    foreach ($required_fields as $field) {
        if (!isset($parsed[$field])) {
            return [
                'success' => false,
                'error' => "Missing required field: $field",
                'parsed' => $parsed
            ];
        }
    }

    return [
        'success' => true,
        'data' => $parsed,
        'raw' => $raw_output
    ];
}

function analyzeWithRetry($apiKey, $models, $prompt, $maxRetriesPerModel, $retryDelay)
{
    $attemptLog = [];
    $totalAttempts = 0;

    foreach ($models as $modelName) {
        error_log("[AI] 🔄 Trying model: $modelName");

        $testResult = testModel($apiKey, $modelName);
        if (!$testResult['available']) {
            error_log("[AI] ❌ Model $modelName unavailable (HTTP {$testResult['httpCode']})");
            $attemptLog[] = [
                'model' => $modelName,
                'status' => 'unavailable',
                'error' => $testResult['error'] ?: "HTTP {$testResult['httpCode']}",
                'timestamp' => date('Y-m-d H:i:s')
            ];
            continue;
        }

        error_log("[AI] ✓ Model $modelName is available");

        for ($attempt = 1; $attempt <= $maxRetriesPerModel; $attempt++) {
            $totalAttempts++;
            $attemptInfo = [
                'model' => $modelName,
                'attempt' => $attempt,
                'total_attempt' => $totalAttempts,
                'timestamp' => date('Y-m-d H:i:s')
            ];

            error_log("[AI] 📝 Attempt $attempt/$maxRetriesPerModel with $modelName");

            $result = callGeminiAPI($apiKey, $modelName, $prompt);

            if (!$result['success']) {
                $attemptInfo['status'] = 'failed';
                $attemptInfo['error'] = $result['error'] ?: "HTTP {$result['httpCode']}";
                $attemptLog[] = $attemptInfo;

                error_log("[AI] ❌ API call failed: {$attemptInfo['error']}");

                if ($attempt < $maxRetriesPerModel) {
                    error_log("[AI] ⏳ Waiting {$retryDelay}s before retry...");
                    sleep($retryDelay);
                }
                continue;
            }

            $parsed = parseAIResponse($result['response']);

            if (!$parsed['success']) {
                $attemptInfo['status'] = 'parse_failed';
                $attemptInfo['error'] = $parsed['error'];
                $attemptLog[] = $attemptInfo;

                error_log("[AI] ❌ Parse failed: {$parsed['error']}");

                if ($attempt < $maxRetriesPerModel) {
                    error_log("[AI] ⏳ Waiting {$retryDelay}s before retry...");
                    sleep($retryDelay);
                }
                continue;
            }

            // Success!
            $attemptInfo['status'] = 'success';
            $attemptLog[] = $attemptInfo;

            error_log("[AI] ✅ SUCCESS with $modelName on attempt $attempt");

            return [
                'success' => true,
                'data' => $parsed['data'],
                'raw' => $parsed['raw'],
                'model_used' => $modelName,
                'attempt_number' => $attempt,
                'total_attempts' => $totalAttempts,
                'attempt_log' => $attemptLog
            ];
        }

        error_log("[AI] ⚠️ All retries failed for $modelName");
    }

    error_log("[AI] ❌ COMPLETE FAILURE");
    return [
        'success' => false,
        'error' => 'All models and retries exhausted',
        'total_attempts' => $totalAttempts,
        'attempt_log' => $attemptLog
    ];
}

// ✅ FIX: Better answer comparison
function compareAnswers($studentAnswerIndex, $correctAnswer, $options)
{
    // Case 1: Student didn't answer
    if ($studentAnswerIndex === null || !isset($options[$studentAnswerIndex])) {
        return false;
    }

    $studentAnswerText = trim($options[$studentAnswerIndex]);
    $correctAnswerText = trim($correctAnswer);

    // Case 2: Direct text comparison (most common)
    if (strcasecmp($studentAnswerText, $correctAnswerText) === 0) {
        return true;
    }

    // Case 3: Correct answer is an index (numeric)
    if (is_numeric($correctAnswer)) {
        $correctIndex = intval($correctAnswer);
        if ($studentAnswerIndex === $correctIndex) {
            return true;
        }
    }

    // Case 4: Find correct answer text in options and compare indices
    $correctIndex = array_search($correctAnswerText, $options);
    if ($correctIndex !== false && $studentAnswerIndex === $correctIndex) {
        return true;
    }

    return false;
}

// ============================
// MAIN EXECUTION
// ============================

$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$exam_id = intval($input['exam_id'] ?? 0);
$user_id = $_SESSION['user']['id'] ?? null;
$force_refresh = $input['force_refresh'] ?? false;

if (!$exam_id || !$user_id) {
    echo json_encode(['status' => 'error', 'message' => 'معلومات الامتحان ناقصة']);
    exit;
}

try {
    // Get student data
    $stmt = $pdo->prepare("SELECT id, answers_json, actions_json, submitted, ai_analysis, created_at FROM exam_results WHERE exam_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1");
    $stmt->execute([$exam_id, $user_id]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        echo json_encode(['status' => 'error', 'message' => 'نتيجة الامتحان غير موجودة']);
        exit;
    }

    if (intval($result['submitted']) !== 1) {
        echo json_encode(['status' => 'error', 'message' => 'لم يتم تسليم الامتحان بعد']);
        exit;
    }

    // Return cached analysis if exists
    if (!empty($result['ai_analysis']) && !$force_refresh) {
        $cached_analysis = json_decode($result['ai_analysis'], true);
        if ($cached_analysis) {
            echo json_encode([
                'status' => 'success',
                'analysis' => $cached_analysis,
                'cached' => true,
                'analyzed_at' => $result['created_at']
            ]);
            exit;
        }
    }

    // Get exam questions
    $stmt2 = $pdo->prepare("SELECT title, questions_json, duration FROM exams WHERE id = ?");
    $stmt2->execute([$exam_id]);
    $exam_data = $stmt2->fetch(PDO::FETCH_ASSOC);

    if (!$exam_data) {
        echo json_encode(['status' => 'error', 'message' => 'الاختبار غير موجود']);
        exit;
    }

    $exam_title = $exam_data['title'];
    $exam_duration = intval($exam_data['duration']);
    $questions = json_decode($exam_data['questions_json'], true);
    $answers = json_decode($result['answers_json'], true) ?: [];
    $actions = json_decode($result['actions_json'], true) ?: [];

    if (!is_array($questions) || empty($questions)) {
        echo json_encode(['status' => 'error', 'message' => 'بيانات الأسئلة غير صحيحة']);
        exit;
    }

    // ✅ FIXED: Better answer analysis
    $correct = 0;
    $total = count($questions);
    $wrong_questions = [];
    $unanswered_questions = [];

    foreach ($questions as $q) {
        $qId = intval($q['id']);
        $studentAnswerIndex = isset($answers[$qId]) ? intval($answers[$qId]) : null;
        $correctAnswer = $q['correct_answer'] ?? null;
        $options = $q['options'] ?? [];

        // Prepare correct answer text for AI
        $correctAnswerText = $options[intval($correctAnswer)] ?? null;

        error_log("[Analyze] Q{$qId}: student_idx={$studentAnswerIndex}, correct='{$correctAnswer}'");

        if (compareAnswers($studentAnswerIndex, $correctAnswerText, $options)) {
            $correct++;
            error_log("[Analyze] ✅ CORRECT");
        } elseif ($studentAnswerIndex !== null && isset($options[$studentAnswerIndex])) {
            // Wrong answer
            error_log("[Analyze] ❌ WRONG");
            $wrong_questions[] = [
                'id' => $qId,
                'question' => $q['question'],
                'student_answer' => $options[$studentAnswerIndex],
                'correct_answer' => $correctAnswerText
            ];
        } else {
            // Unanswered
            error_log("[Analyze] ⚠️ UNANSWERED");
            $unanswered_questions[] = [
                'id' => $qId,
                'question' => $q['question'],
                'correct_answer' => $correctAnswerText
            ];
        }
    }

    error_log("[Analyze] 📊 FINAL: {$correct}/{$total}");
    $accuracy = $total > 0 ? round(($correct / $total) * 100, 2) : 0;

    // Prepare AI prompt data
    $wrong_questions_json = json_encode($wrong_questions, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $unanswered_questions_json = json_encode($unanswered_questions, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $actions_json = json_encode($actions, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    $wrong_count = count($wrong_questions);
    $unanswered_count = count($unanswered_questions);

    $prompt = <<<EOT
أنت مساعد تعليمي ذكي متخصص في تحليل الامتحانات. هتكلم الطالب مباشرة باستخدام "أنت" و"إجابتك" بالعامية المصرية.

**بيانات الامتحان:**
- عنوان الامتحان: "$exam_title"
- مدة الامتحان: $exam_duration دقيقة
- إجمالي الأسئلة: $total
- الإجابات الصحيحة: $correct
- الإجابات الخاطئة: $wrong_count
- الأسئلة بدون إجابة: $unanswered_count
- درجتك النهائية: $accuracy%

**الأسئلة اللي جاوبت عليها غلط:**
$wrong_questions_json

**الأسئلة اللي مجاوبتش عليها:**
$unanswered_questions_json

**بيانات سلوكك أثناء الامتحان:**
$actions_json

---

**المطلوب منك - قدم تحليل شامل ومفصل جداً والاخذ ب بيانات السلوك في الاعتبار:**

**1. شرح الإجابات الخاطئة (WRONG ANSWERS EXPLANATION):**
لكل سؤال جاوبت عليه غلط، لازم تعمل الآتي:
- اشرح بالتفصيل ليه الإجابة الصحيحة هي الصح
- وضح ليه إجابتك اللي اخترتها كانت غلط
- اذكر المفاهيم الأساسية اللي الطالب محتاج يراجعها
- قدم نصيحة أو طريقة لتذكر الإجابة الصحيحة (memory tip أو mnemonic)
- اتكلم بشكل مباشر: "أنت اخترت X، لكن الإجابة الصحيحة هي Y لأن..."

مثال للشرح المطلوب:
"أنت اخترت 'القاهرة عاصمة السودان'، لكن دي إجابة غلط. الإجابة الصحيحة هي 'الخرطوم' لأن الخرطوم هي العاصمة الرسمية للسودان منذ سنة 1956. ممكن تكون اتلخبطت لأن القاهرة عاصمة مصر وهي دولة مجاورة. عشان تفتكر، فكر في كلمة 'خرطوم الفيل' - الخرطوم هي العاصمة السودانية. راجع جغرافيا الدول الأفريقية وعواصمها."

**2. شرح الأسئلة اللي مجاوبتش عليها (UNANSWERED QUESTIONS):**
لكل سؤال مجاوبتش عليه، قدم:
- الإجابة الصحيحة مع شرح تفصيلي ليه هي صح
- أهمية المفهوم ده وليه مهم تعرفه
- إزاي تتعامل مع أسئلة زي دي في المستقبل
- اتكلم مباشر: "أنت مجاوبتش على السؤال ده. الإجابة الصحيحة هي..."

**3. تحليل السلوك أثناء الامتحان (DETAILED BEHAVIOR ANALYSIS):**

**أ. تحليل السرعة (Speed Analysis):**
- هل الطالب كان بيجاوب بسرعة زيادة عن اللزوم (rushing)?
- ولا كان بياخد وقت كتير وبيفكر زيادة (overthinking)?
- ولا كان عنده توازن كويس في السرعة؟
- احسب متوسط الوقت لكل سؤال وقارنه بالوقت المثالي
- قدم نصيحة محددة: "أنت قضيت متوسط X ثانية في السؤال. للأداء الأمثل، المفروض تقضي Y ثانية."

**ب. مستوى الثقة (Confidence Level):**
- هل الطالب كان واثق من إجاباته (قليل التغيير في الإجابات)?
- ولا كان متردد وغير متأكد (كتير التغيير في الإجابات)?
- هل كان بيشكك في نفسه كتير؟
- نصيحة: "غيرت إجاباتك X مرة، ده معناه [واثق/متردد/متوسط]. وده معناه..."

**ج. نمط التنقل (Navigation Pattern):**
- هل جاوب على الأسئلة بالترتيب (1→2→3...)?
- ولا كان بينط بين الأسئلة بشكل عشوائي؟
- ولا كان بيتخطى الأسئلة الصعبة ويرجعلها تاني؟
- ايه اللي النمط ده بيقوله عن استراتيجيته وإدارة وقته؟

**د. إدارة الوقت (Time Management):**
- متوسط الوقت اللي قضاه في كل سؤال
- هل قضى وقت أطول في أسئلة معينة؟
- هل اتسرع في الآخر بسبب ضغط الوقت؟
- توصيات محددة لتوزيع الوقت بشكل أفضل

**هـ. تغيير الإجابات (Answer Changes):**
- كام مرة غير إجاباته؟
- تحليل النمط: هل كان بيغير من صح لغلط، ولا من غلط لصح؟
- ده بيقول ايه عن ثقته في نفسه واتخاذ القرار؟

**4. كشف الشبهات (CHEATING SUSPICION DETECTION):**

دور على العلامات دي:
- أوقات استجابة سريعة جداً (الإجابة على أسئلة معقدة في 1-2 ثانية)
- تغير مفاجئ في الأداء (بطيء ثم فجأة سريع مع كل الإجابات صح)
- فترات طويلة من عدم النشاط تليها إجابات كتيرة مرة واحدة
- أنماط تنقل غريبة (الإجابة على الأسئلة بشكل غير منطقي)
- تبديل التابات أو تغيير نافذة المتصفح (لو ظاهر في الأفعال)

**مستويات التقييم:**
- **none**: مفيش سلوك مشبوه. أنماط امتحان طبيعية.
- **ضعيف**: بعض الحاجات الغريبة البسيطة اللي ممكن تكون صدفة.
- **متوسط**: مؤشرات متعددة بتشير لاحتمال مساعدة خارجية.
- **عالي**: دليل قوي على سلوك غش.

**كن عادل وموضوعي**: متتهمش من غير دليل واضح. اشرح الأنماط المحددة اللي أثارت القلق.

**5. التوصيات الشخصية (PERSONALIZED RECOMMENDATIONS):**
قدم 3-5 توصيات عملية وقابلة للتطبيق:
- استراتيجيات مذاكرة للمواضيع اللي واجه فيها صعوبة
- تقنيات إدارة الوقت
- استراتيجيات حل الامتحانات (مثل: "اقرأ الأسئلة بعناية"، "احذف الإجابات الخاطئة الأول")
- نصايح لبناء الثقة
- مصادر متخصصة للمراجعة
- اتكلم مباشر: "أنصحك تركز على..."، "عشان تتحسن، لازم..."

**6. الملخص العام (OVERALL SUMMARY):**
اكتب ملخص شامل ومشجع (3-4 جمل):
- تقييم الأداء العام
- نقاط القوة اللي أظهرها الطالب
- المجالات الرئيسية للتحسين
- بيان ختامي تحفيزي
- اتكلم مباشر: "أنت أديت كويس في..."، "التحدي الرئيسي بتاعك كان..."، "مع التدريب المركز، هتقدر..."

---

**متطلبات التنسيق:**
- ارجع JSON صحيح فقط، من غير أي تنسيق markdown أو code blocks
- استخدم لغة بسيطة وواضحة الطلاب يفهموها
- كن مشجع وبناء، مش محبط
- كل النصوص لازم تخاطب الطالب مباشرة باستخدام "أنت" و"إجابتك"
- قدم رؤى محددة وقابلة للتطبيق، مش نصايح عامة
- **اكتب كل النصوص بالعامية المصرية - المفاتيح تبقى بالإنجليزي، بس القيم بالعامية المصرية**

**هيكل JSON المطلوب:**
{
  "score": {
    "correct": $correct,
    "total": $total,
    "percentage": $accuracy
  },
  "wrong_answers_explanation": [
    {
      "question_id": 1,
      "question": "نص السؤال",
      "student_answer": "إجابتك",
      "correct_answer": "الإجابة الصحيحة",
      "explanation": "[ترجم للعامية المصرية: أنت اخترت [X] لأنك ممكن تكون فكرت [السبب]. لكن الإجابة الصحيحة هي [Y] لأن [شرح تفصيلي]. عشان تفتكر ده، جرب [نصيحة للتذكر]. لازم تراجع [موضوع محدد].]"
    }
  ],
  "unanswered_explanation": [
    {
      "question_id": 1,
      "question": "نص السؤال",
      "correct_answer": "الإجابة الصحيحة",
      "explanation": "[ترجم للعامية المصرية: أنت مجاوبتش على السؤال ده. الإجابة الصحيحة هي [X] لأن [شرح تفصيلي]. المفهوم ده مهم لأن [السبب]. المرة الجاية، تعامل مع الأسئلة زي دي بـ [استراتيجية].]"
    }
  ],
  "behavior_analysis": {
    "speed": "سريع / طبيعي / بطيئ",
    "confidence": "واثق / متردد / معتدل",
    "navigation_pattern": "تسلسلي / عشوائي / استراتيجي",
    "answer_changes": 5,
    "average_time_per_question": "X ثانية",
    "details": "[ترجم للعامية المصرية: أنت خلصت الامتحان بسرعة [fast/balanced/slow]، وقضيت متوسط [X] ثانية في كل سؤال. غيرت إجاباتك [Y] مرة، وده معناه [مستوى الثقة]. نمط التنقل بتاعك كان [النمط]، وده بيشير لـ [التحليل]. [ملاحظات محددة]. [نصيحة محددة].]"
  },
  "cheating_suspicion": {
    "level": "none / منخفض / متوسط / عالي",
    "indicators": ["[عامية مصرية]", "[عامية مصرية]"],
    "explanation": "[ترجم للعامية المصرية: بناءً على سلوكك في الامتحان، [التقييم]. [تفاصيل عن الأنماط].]"
  },
  "recommendations": [
    "[ترجم للعامية المصرية: ركز على مراجعة [الموضوع]...]",
    "[ترجم للعامية المصرية: حسن إدارة وقتك...]",
    "[ترجم للعامية المصرية: اشتغل على ثقتك...]",
    "[توصية بالعامية المصرية]",
    "[توصية بالعامية المصرية]"
  ],
  "overall_summary": "[ترجم للعامية المصرية: أنت جبت $accuracy% في الامتحان ده... [الملخص الكامل]]"
}
EOT;

    // Get API key
    $apiKey = getenv('GEMINI_API_KEY');
    if (!$apiKey) {
        error_log("[Analyze] ❌ No API key");
        echo json_encode([
            'status' => 'error',
            'message' => 'مفتاح API غير متوفر',
            'error_code' => 'MISSING_API_KEY'
        ]);
        exit;
    }

    // Try analysis
    error_log("[Analyze] 🚀 Starting AI analysis...");

    $analysisResult = analyzeWithRetry(
        $apiKey,
        $AI_MODELS,
        $prompt,
        $MAX_RETRIES_PER_MODEL,
        $RETRY_DELAY_SECONDS
    );

    if (!$analysisResult['success']) {
        error_log("[Analyze] ❌ Failed after {$analysisResult['total_attempts']} attempts");

        echo json_encode([
            'status' => 'error',
            'message' => 'فشل التحليل بعد ' . $analysisResult['total_attempts'] . ' محاولات',
            'error_code' => 'AI_FAILED',
            'retry_available' => true,
            'debug' => $analysisResult['attempt_log']
        ]);
        exit;
    }

    // Save to database
    $stmt3 = $pdo->prepare("UPDATE exam_results SET ai_analysis = ? WHERE id = ?");
    $stmt3->execute([$analysisResult['raw'], $result['id']]);

    error_log("[Analyze] ✅ Success!");

    echo json_encode([
        'status' => 'success',
        'analysis' => $analysisResult['data'],
        'cached' => false,
        'metadata' => [
            'model_used' => $analysisResult['model_used'],
            'attempts' => $analysisResult['total_attempts']
        ]
    ]);

} catch (Exception $e) {
    error_log("[Analyze] ❌ ERROR: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'حدث خطأ غير متوقع',
        'error_code' => 'UNEXPECTED_ERROR'
    ]);
}


// ==========================================
// OLD VERSION
// ==========================================

// routes/analyze_exam.php - FIXED VERSION
// session_start();
// header('Content-Type: application/json; charset=utf-8');
// // // // if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS')
//     exit;

// require_once __DIR__ . '/../../config/db.php';
// require_once __DIR__ . '/../../config/env.php';

// // ============================
// // CONFIGURATION
// // ============================

// $AI_MODELS = [
//     'gemini-2.5-flash',
//     'gemini-2.0-flash'
// ];

// $MAX_RETRIES_PER_MODEL = 3;
// $RETRY_DELAY_SECONDS = 2;

// // ============================
// // HELPER FUNCTIONS
// // ============================

// function testModel($apiKey, $modelName)
// {
//     $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key=$apiKey";

//     $testData = [
//         "contents" => [
//             ["parts" => [["text" => "test"]]]
//         ],
//         "generationConfig" => [
//             "maxOutputTokens" => 10
//         ]
//     ];

//     $ch = curl_init($url);
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//     curl_setopt($ch, CURLOPT_POST, true);
//     curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
//     curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
//     curl_setopt($ch, CURLOPT_TIMEOUT, 5);
//     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

//     $response = curl_exec($ch);
//     $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//     $curlError = curl_error($ch);
//     curl_close($ch);

//     return [
//         'available' => empty($curlError) && $httpCode === 200,
//         'httpCode' => $httpCode,
//         'error' => $curlError
//     ];
// }

// function callGeminiAPI($apiKey, $modelName, $prompt)
// {
//     $url = "https://generativelanguage.googleapis.com/v1beta/models/{$modelName}:generateContent?key=$apiKey";

//     $postData = [
//         "contents" => [
//             [
//                 "parts" => [
//                     ["text" => $prompt]
//                 ]
//             ]
//         ],
//         "generationConfig" => [
//             "temperature" => 0.7,
//             "topK" => 40,
//             "topP" => 0.95,
//             "maxOutputTokens" => 8192,
//             "responseMimeType" => "application/json"
//         ]
//     ];

//     $ch = curl_init($url);
//     curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//     curl_setopt($ch, CURLOPT_POST, true);
//     curl_setopt($ch, CURLOPT_HTTPHEADER, ["Content-Type: application/json"]);
//     curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
//     curl_setopt($ch, CURLOPT_TIMEOUT, 60);
//     curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

//     $response = curl_exec($ch);
//     $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//     $curlError = curl_error($ch);
//     curl_close($ch);

//     return [
//         'success' => empty($curlError) && $httpCode === 200,
//         'response' => $response,
//         'httpCode' => $httpCode,
//         'error' => $curlError
//     ];
// }

// function parseAIResponse($rawResponse)
// {
//     $ai_result = json_decode($rawResponse, true);

//     if (!isset($ai_result['candidates'][0]['content']['parts'][0]['text'])) {
//         return [
//             'success' => false,
//             'error' => 'Invalid response structure',
//             'debug' => 'Missing text in candidates'
//         ];
//     }

//     $raw_output = $ai_result['candidates'][0]['content']['parts'][0]['text'];

//     // Clean markdown code blocks
//     $raw_output = preg_replace('/```json\s*/', '', $raw_output);
//     $raw_output = preg_replace('/```\s*$/', '', $raw_output);
//     $raw_output = trim($raw_output);

//     $parsed = json_decode($raw_output, true);

//     if (!$parsed) {
//         return [
//             'success' => false,
//             'error' => 'JSON parse failed',
//             'raw_output' => $raw_output
//         ];
//     }

//     // Validate required fields
//     $required_fields = ['score', 'behavior_analysis', 'recommendations', 'overall_summary'];
//     foreach ($required_fields as $field) {
//         if (!isset($parsed[$field])) {
//             return [
//                 'success' => false,
//                 'error' => "Missing required field: $field",
//                 'parsed' => $parsed
//             ];
//         }
//     }

//     return [
//         'success' => true,
//         'data' => $parsed,
//         'raw' => $raw_output
//     ];
// }

// function analyzeWithRetry($apiKey, $models, $prompt, $maxRetriesPerModel, $retryDelay)
// {
//     $attemptLog = [];
//     $totalAttempts = 0;

//     foreach ($models as $modelName) {
//         error_log("[AI] 🔄 Trying model: $modelName");

//         $testResult = testModel($apiKey, $modelName);
//         if (!$testResult['available']) {
//             error_log("[AI] ❌ Model $modelName unavailable (HTTP {$testResult['httpCode']})");
//             $attemptLog[] = [
//                 'model' => $modelName,
//                 'status' => 'unavailable',
//                 'error' => $testResult['error'] ?: "HTTP {$testResult['httpCode']}",
//                 'timestamp' => date('Y-m-d H:i:s')
//             ];
//             continue;
//         }

//         error_log("[AI] ✓ Model $modelName is available");

//         for ($attempt = 1; $attempt <= $maxRetriesPerModel; $attempt++) {
//             $totalAttempts++;
//             $attemptInfo = [
//                 'model' => $modelName,
//                 'attempt' => $attempt,
//                 'total_attempt' => $totalAttempts,
//                 'timestamp' => date('Y-m-d H:i:s')
//             ];

//             error_log("[AI] 📝 Attempt $attempt/$maxRetriesPerModel with $modelName");

//             $result = callGeminiAPI($apiKey, $modelName, $prompt);

//             if (!$result['success']) {
//                 $attemptInfo['status'] = 'failed';
//                 $attemptInfo['error'] = $result['error'] ?: "HTTP {$result['httpCode']}";
//                 $attemptLog[] = $attemptInfo;

//                 error_log("[AI] ❌ API call failed: {$attemptInfo['error']}");

//                 if ($attempt < $maxRetriesPerModel) {
//                     error_log("[AI] ⏳ Waiting {$retryDelay}s before retry...");
//                     sleep($retryDelay);
//                 }
//                 continue;
//             }

//             $parsed = parseAIResponse($result['response']);

//             if (!$parsed['success']) {
//                 $attemptInfo['status'] = 'parse_failed';
//                 $attemptInfo['error'] = $parsed['error'];
//                 $attemptLog[] = $attemptInfo;

//                 error_log("[AI] ❌ Parse failed: {$parsed['error']}");

//                 if ($attempt < $maxRetriesPerModel) {
//                     error_log("[AI] ⏳ Waiting {$retryDelay}s before retry...");
//                     sleep($retryDelay);
//                 }
//                 continue;
//             }

//             // Success!
//             $attemptInfo['status'] = 'success';
//             $attemptLog[] = $attemptInfo;

//             error_log("[AI] ✅ SUCCESS with $modelName on attempt $attempt");

//             return [
//                 'success' => true,
//                 'data' => $parsed['data'],
//                 'raw' => $parsed['raw'],
//                 'model_used' => $modelName,
//                 'attempt_number' => $attempt,
//                 'total_attempts' => $totalAttempts,
//                 'attempt_log' => $attemptLog
//             ];
//         }

//         error_log("[AI] ⚠️ All retries failed for $modelName");
//     }

//     error_log("[AI] ❌ COMPLETE FAILURE");
//     return [
//         'success' => false,
//         'error' => 'All models and retries exhausted',
//         'total_attempts' => $totalAttempts,
//         'attempt_log' => $attemptLog
//     ];
// }

// // ✅ FIX: Better answer comparison
// function compareAnswers($studentAnswerIndex, $correctAnswer, $options)
// {
//     // Case 1: Student didn't answer
//     if ($studentAnswerIndex === null || !isset($options[$studentAnswerIndex])) {
//         return false;
//     }

//     $studentAnswerText = trim($options[$studentAnswerIndex]);
//     $correctAnswerText = trim($correctAnswer);

//     // Case 2: Direct text comparison (most common)
//     if (strcasecmp($studentAnswerText, $correctAnswerText) === 0) {
//         return true;
//     }

//     // Case 3: Correct answer is an index (numeric)
//     if (is_numeric($correctAnswer)) {
//         $correctIndex = intval($correctAnswer);
//         if ($studentAnswerIndex === $correctIndex) {
//             return true;
//         }
//     }

//     // Case 4: Find correct answer text in options and compare indices
//     $correctIndex = array_search($correctAnswerText, $options);
//     if ($correctIndex !== false && $studentAnswerIndex === $correctIndex) {
//         return true;
//     }

//     return false;
// }

// // ============================
// // MAIN EXECUTION
// // ============================

// $csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
// if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
//     echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);
//     exit;
// }

// $input = json_decode(file_get_contents('php://input'), true);
// $exam_id = intval($input['exam_id'] ?? 0);
// $user_id = $_SESSION['user']['id'] ?? null;
// $force_refresh = $input['force_refresh'] ?? false;

// if (!$exam_id || !$user_id) {
//     echo json_encode(['status' => 'error', 'message' => 'معلومات الامتحان ناقصة']);
//     exit;
// }

// try {
//     // Get student data
//     $stmt = $pdo->prepare("SELECT id, answers_json, actions_json, submitted, ai_analysis, created_at FROM exam_results WHERE exam_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1");
//     $stmt->execute([$exam_id, $user_id]);
//     $result = $stmt->fetch(PDO::FETCH_ASSOC);

//     if (!$result) {
//         echo json_encode(['status' => 'error', 'message' => 'نتيجة الامتحان غير موجودة']);
//         exit;
//     }

//     if (intval($result['submitted']) !== 1) {
//         echo json_encode(['status' => 'error', 'message' => 'لم يتم تسليم الامتحان بعد']);
//         exit;
//     }

//     // Return cached analysis if exists
//     if (!empty($result['ai_analysis']) && !$force_refresh) {
//         $cached_analysis = json_decode($result['ai_analysis'], true);
//         if ($cached_analysis) {
//             echo json_encode([
//                 'status' => 'success',
//                 'analysis' => $cached_analysis,
//                 'cached' => true,
//                 'analyzed_at' => $result['created_at']
//             ]);
//             exit;
//         }
//     }

//     // Get exam questions
//     $stmt2 = $pdo->prepare("SELECT title, questions_json, duration FROM exams WHERE id = ?");
//     $stmt2->execute([$exam_id]);
//     $exam_data = $stmt2->fetch(PDO::FETCH_ASSOC);

//     if (!$exam_data) {
//         echo json_encode(['status' => 'error', 'message' => 'الاختبار غير موجود']);
//         exit;
//     }

//     $exam_title = $exam_data['title'];
//     $exam_duration = intval($exam_data['duration']);
//     $questions = json_decode($exam_data['questions_json'], true);
//     $answers = json_decode($result['answers_json'], true) ?: [];
//     $actions = json_decode($result['actions_json'], true) ?: [];

//     if (!is_array($questions) || empty($questions)) {
//         echo json_encode(['status' => 'error', 'message' => 'بيانات الأسئلة غير صحيحة']);
//         exit;
//     }

//     // ✅ FIXED: Better answer analysis
//     $correct = 0;
//     $total = count($questions);
//     $wrong_questions = [];
//     $unanswered_questions = [];

//     foreach ($questions as $q) {
//         $qId = intval($q['id']);
//         $studentAnswerIndex = isset($answers[$qId]) ? intval($answers[$qId]) : null;
//         $correctAnswer = $q['answer'] ?? null;
//         $options = $q['options'] ?? [];

//         error_log("[Analyze] Q{$qId}: student_idx={$studentAnswerIndex}, correct='{$correctAnswer}'");

//         if (compareAnswers($studentAnswerIndex, $correctAnswer, $options)) {
//             $correct++;
//             error_log("[Analyze] ✅ CORRECT");
//         } elseif ($studentAnswerIndex !== null && isset($options[$studentAnswerIndex])) {
//             // Wrong answer
//             error_log("[Analyze] ❌ WRONG");
//             $wrong_questions[] = [
//                 'id' => $qId,
//                 'question' => $q['question'],
//                 'student_answer' => $options[$studentAnswerIndex],
//                 'correct_answer' => $correctAnswer,
//                 'all_options' => $options
//             ];
//         } else {
//             // Unanswered
//             error_log("[Analyze] ⚠️ UNANSWERED");
//             $unanswered_questions[] = [
//                 'id' => $qId,
//                 'question' => $q['question'],
//                 'correct_answer' => $correctAnswer,
//                 'all_options' => $options
//             ];
//         }
//     }

//     error_log("[Analyze] 📊 FINAL: {$correct}/{$total}");
//     $accuracy = $total > 0 ? round(($correct / $total) * 100, 2) : 0;

//     // Prepare AI prompt data
//     $wrong_questions_json = json_encode($wrong_questions, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
//     $unanswered_questions_json = json_encode($unanswered_questions, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
//     $actions_json = json_encode($actions, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
//     $wrong_count = count($wrong_questions);
//     $unanswered_count = count($unanswered_questions);

//     $prompt = <<<EOT
// أنت مساعد تعليمي ذكي متخصص في تحليل الامتحانات. هتكلم الطالب مباشرة باستخدام "أنت" و"إجابتك" بالعامية المصرية.

// **بيانات الامتحان:**
// - عنوان الامتحان: "$exam_title"
// - مدة الامتحان: $exam_duration دقيقة
// - إجمالي الأسئلة: $total
// - الإجابات الصحيحة: $correct
// - الإجابات الخاطئة: $wrong_count
// - الأسئلة بدون إجابة: $unanswered_count
// - درجتك النهائية: $accuracy%

// **الأسئلة اللي جاوبت عليها غلط:**
// $wrong_questions_json

// **الأسئلة اللي مجاوبتش عليها:**
// $unanswered_questions_json

// **بيانات سلوكك أثناء الامتحان:**
// $actions_json

// ---

// **المطلوب منك - قدم تحليل شامل ومفصل جداً والاخذ ب بيانات السلوك في الاعتبار:**

// **1. شرح الإجابات الخاطئة (WRONG ANSWERS EXPLANATION):**
// لكل سؤال جاوبت عليه غلط، لازم تعمل الآتي:
// - اشرح بالتفصيل ليه الإجابة الصحيحة هي الصح
// - وضح ليه إجابتك اللي اخترتها كانت غلط
// - اذكر المفاهيم الأساسية اللي الطالب محتاج يراجعها
// - قدم نصيحة أو طريقة لتذكر الإجابة الصحيحة (memory tip أو mnemonic)
// - اتكلم بشكل مباشر: "أنت اخترت X، لكن الإجابة الصحيحة هي Y لأن..."

// مثال للشرح المطلوب:
// "أنت اخترت 'القاهرة عاصمة السودان'، لكن دي إجابة غلط. الإجابة الصحيحة هي 'الخرطوم' لأن الخرطوم هي العاصمة الرسمية للسودان منذ سنة 1956. ممكن تكون اتلخبطت لأن القاهرة عاصمة مصر وهي دولة مجاورة. عشان تفتكر، فكر في كلمة 'خرطوم الفيل' - الخرطوم هي العاصمة السودانية. راجع جغرافيا الدول الأفريقية وعواصمها."

// **2. شرح الأسئلة اللي مجاوبتش عليها (UNANSWERED QUESTIONS):**
// لكل سؤال مجاوبتش عليه، قدم:
// - الإجابة الصحيحة مع شرح تفصيلي ليه هي صح
// - أهمية المفهوم ده وليه مهم تعرفه
// - إزاي تتعامل مع أسئلة زي دي في المستقبل
// - اتكلم مباشر: "أنت مجاوبتش على السؤال ده. الإجابة الصحيحة هي..."

// **3. تحليل السلوك أثناء الامتحان (DETAILED BEHAVIOR ANALYSIS):**

// **أ. تحليل السرعة (Speed Analysis):**
// - هل الطالب كان بيجاوب بسرعة زيادة عن اللزوم (rushing)?
// - ولا كان بياخد وقت كتير وبيفكر زيادة (overthinking)?
// - ولا كان عنده توازن كويس في السرعة؟
// - احسب متوسط الوقت لكل سؤال وقارنه بالوقت المثالي
// - قدم نصيحة محددة: "أنت قضيت متوسط X ثانية في السؤال. للأداء الأمثل، المفروض تقضي Y ثانية."

// **ب. مستوى الثقة (Confidence Level):**
// - هل الطالب كان واثق من إجاباته (قليل التغيير في الإجابات)?
// - ولا كان متردد وغير متأكد (كتير التغيير في الإجابات)?
// - هل كان بيشكك في نفسه كتير؟
// - نصيحة: "غيرت إجاباتك X مرة، ده معناه [واثق/متردد/متوسط]. وده معناه..."

// **ج. نمط التنقل (Navigation Pattern):**
// - هل جاوب على الأسئلة بالترتيب (1→2→3...)?
// - ولا كان بينط بين الأسئلة بشكل عشوائي؟
// - ولا كان بيتخطى الأسئلة الصعبة ويرجعلها تاني؟
// - ايه اللي النمط ده بيقوله عن استراتيجيته وإدارة وقته؟

// **د. إدارة الوقت (Time Management):**
// - متوسط الوقت اللي قضاه في كل سؤال
// - هل قضى وقت أطول في أسئلة معينة؟
// - هل اتسرع في الآخر بسبب ضغط الوقت؟
// - توصيات محددة لتوزيع الوقت بشكل أفضل

// **هـ. تغيير الإجابات (Answer Changes):**
// - كام مرة غير إجاباته؟
// - تحليل النمط: هل كان بيغير من صح لغلط، ولا من غلط لصح؟
// - ده بيقول ايه عن ثقته في نفسه واتخاذ القرار؟

// **4. كشف الشبهات (CHEATING SUSPICION DETECTION):**

// دور على العلامات دي:
// - أوقات استجابة سريعة جداً (الإجابة على أسئلة معقدة في 1-2 ثانية)
// - تغير مفاجئ في الأداء (بطيء ثم فجأة سريع مع كل الإجابات صح)
// - فترات طويلة من عدم النشاط تليها إجابات كتيرة مرة واحدة
// - أنماط تنقل غريبة (الإجابة على الأسئلة بشكل غير منطقي)
// - تبديل التابات أو تغيير نافذة المتصفح (لو ظاهر في الأفعال)

// **مستويات التقييم:**
// - **none**: مفيش سلوك مشبوه. أنماط امتحان طبيعية.
// - **low**: بعض الحاجات الغريبة البسيطة اللي ممكن تكون صدفة.
// - **medium**: مؤشرات متعددة بتشير لاحتمال مساعدة خارجية.
// - **high**: دليل قوي على سلوك غش.

// **كن عادل وموضوعي**: متتهمش من غير دليل واضح. اشرح الأنماط المحددة اللي أثارت القلق.

// **5. التوصيات الشخصية (PERSONALIZED RECOMMENDATIONS):**
// قدم 3-5 توصيات عملية وقابلة للتطبيق:
// - استراتيجيات مذاكرة للمواضيع اللي واجه فيها صعوبة
// - تقنيات إدارة الوقت
// - استراتيجيات حل الامتحانات (مثل: "اقرأ الأسئلة بعناية"، "احذف الإجابات الخاطئة الأول")
// - نصايح لبناء الثقة
// - مصادر متخصصة للمراجعة
// - اتكلم مباشر: "أنصحك تركز على..."، "عشان تتحسن، لازم..."

// **6. الملخص العام (OVERALL SUMMARY):**
// اكتب ملخص شامل ومشجع (3-4 جمل):
// - تقييم الأداء العام
// - نقاط القوة اللي أظهرها الطالب
// - المجالات الرئيسية للتحسين
// - بيان ختامي تحفيزي
// - اتكلم مباشر: "أنت أديت كويس في..."، "التحدي الرئيسي بتاعك كان..."، "مع التدريب المركز، هتقدر..."

// ---

// **متطلبات التنسيق:**
// - ارجع JSON صحيح فقط، من غير أي تنسيق markdown أو code blocks
// - استخدم لغة بسيطة وواضحة الطلاب يفهموها
// - كن مشجع وبناء، مش محبط
// - كل النصوص لازم تخاطب الطالب مباشرة باستخدام "أنت" و"إجابتك"
// - قدم رؤى محددة وقابلة للتطبيق، مش نصايح عامة
// - **اكتب كل النصوص بالعامية المصرية - المفاتيح تبقى بالإنجليزي، بس القيم بالعامية المصرية**

// **هيكل JSON المطلوب:**
// {
//   "score": {
//     "correct": $correct,
//     "total": $total,
//     "percentage": $accuracy
//   },
//   "wrong_answers_explanation": [
//     {
//       "question_id": 1,
//       "question": "نص السؤال",
//       "student_answer": "إجابتك",
//       "correct_answer": "الإجابة الصحيحة",
//       "explanation": "[ترجم للعامية المصرية: أنت اخترت [X] لأنك ممكن تكون فكرت [السبب]. لكن الإجابة الصحيحة هي [Y] لأن [شرح تفصيلي]. عشان تفتكر ده، جرب [نصيحة للتذكر]. لازم تراجع [موضوع محدد].]"
//     }
//   ],
//   "unanswered_explanation": [
//     {
//       "question_id": 1,
//       "question": "نص السؤال",
//       "correct_answer": "الإجابة الصحيحة",
//       "explanation": "[ترجم للعامية المصرية: أنت مجاوبتش على السؤال ده. الإجابة الصحيحة هي [X] لأن [شرح تفصيلي]. المفهوم ده مهم لأن [السبب]. المرة الجاية، تعامل مع الأسئلة زي دي بـ [استراتيجية].]"
//     }
//   ],
//   "behavior_analysis": {
//     "speed": "سريع / طبيعي / بطيئ",
//     "confidence": "واثق / متردد / معتدل",
//     "navigation_pattern": "تسلسلي / عشوائي / استراتيجي",
//     "answer_changes": 5,
//     "average_time_per_question": "X ثانية",
//     "details": "[ترجم للعامية المصرية: أنت خلصت الامتحان بسرعة [fast/balanced/slow]، وقضيت متوسط [X] ثانية في كل سؤال. غيرت إجاباتك [Y] مرة، وده معناه [مستوى الثقة]. نمط التنقل بتاعك كان [النمط]، وده بيشير لـ [التحليل]. [ملاحظات محددة]. [نصيحة محددة].]"
//   },
//   "cheating_suspicion": {
//     "level": "none / منخفض / متوسط / عالي",
//     "indicators": ["[عامية مصرية]", "[عامية مصرية]"],
//     "explanation": "[ترجم للعامية المصرية: بناءً على سلوكك في الامتحان، [التقييم]. [تفاصيل عن الأنماط].]"
//   },
//   "recommendations": [
//     "[ترجم للعامية المصرية: ركز على مراجعة [الموضوع]...]",
//     "[ترجم للعامية المصرية: حسن إدارة وقتك...]",
//     "[ترجم للعامية المصرية: اشتغل على ثقتك...]",
//     "[توصية بالعامية المصرية]",
//     "[توصية بالعامية المصرية]"
//   ],
//   "overall_summary": "[ترجم للعامية المصرية: أنت جبت $accuracy% في الامتحان ده... [الملخص الكامل]]"
// }
// EOT;

//     // Get API key
//     $apiKey = getenv('GEMINI_API_KEY');
//     if (!$apiKey) {
//         error_log("[Analyze] ❌ No API key");
//         echo json_encode([
//             'status' => 'error',
//             'message' => 'مفتاح API غير متوفر',
//             'error_code' => 'MISSING_API_KEY'
//         ]);
//         exit;
//     }

//     // Try analysis
//     error_log("[Analyze] 🚀 Starting AI analysis...");

//     $analysisResult = analyzeWithRetry(
//         $apiKey,
//         $AI_MODELS,
//         $prompt,
//         $MAX_RETRIES_PER_MODEL,
//         $RETRY_DELAY_SECONDS
//     );

//     if (!$analysisResult['success']) {
//         error_log("[Analyze] ❌ Failed after {$analysisResult['total_attempts']} attempts");

//         echo json_encode([
//             'status' => 'error',
//             'message' => 'فشل التحليل بعد ' . $analysisResult['total_attempts'] . ' محاولات',
//             'error_code' => 'AI_FAILED',
//             'retry_available' => true,
//             'debug' => $analysisResult['attempt_log']
//         ]);
//         exit;
//     }

//     // Save to database
//     $stmt3 = $pdo->prepare("UPDATE exam_results SET ai_analysis = ? WHERE id = ?");
//     $stmt3->execute([$analysisResult['raw'], $result['id']]);

//     error_log("[Analyze] ✅ Success!");

//     echo json_encode([
//         'status' => 'success',
//         'analysis' => $analysisResult['data'],
//         'cached' => false,
//         'metadata' => [
//             'model_used' => $analysisResult['model_used'],
//             'attempts' => $analysisResult['total_attempts']
//         ]
//     ]);

// } catch (Exception $e) {
//     error_log("[Analyze] ❌ ERROR: " . $e->getMessage());
//     echo json_encode([
//         'status' => 'error',
//         'message' => 'حدث خطأ غير متوقع',
//         'error_code' => 'UNEXPECTED_ERROR'
//     ]);
// }

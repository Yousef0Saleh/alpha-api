<?php
// routes/exam_generator/generate_pdf.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Start output buffering to prevent headers already sent error
ob_start();

require_once __DIR__ . '/../../config/cors.php';
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

error_reporting(0);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../../logs/pdf_errors.log');

require_once __DIR__ . '/../../vendor/autoload.php';

use Mpdf\Mpdf;

// CSRF validation
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
    http_response_code(403);
    exit;
}

// Authentication check
$user_id = $_SESSION['user']['id'] ?? null;
if (!$user_id) {
    http_response_code(401);
    exit;
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['exam'])) {
    http_response_code(400);
    exit;
}

$exam = $data['exam'];
$show_answers = $data['show_answers'] ?? false;

try {
    // Create mPDF instance with Arabic support
    $mpdf = new Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'orientation' => 'P',
        'margin_left' => 15,
        'margin_right' => 15,
        'margin_top' => 15,
        'margin_bottom' => 15,
        'default_font' => 'dejavusans',
        'directionality' => 'rtl',
        'autoScriptToLang' => true,
        'autoLangToFont' => true
    ]);

    // Set document properties
    $mpdf->SetTitle('امتحان تفاعلي');
    $mpdf->SetAuthor('منصة ألفا');
    $mpdf->SetCreator('Alpha Platform');

    // Prepare data
    $filename = htmlspecialchars($exam['original_filename'], ENT_QUOTES, 'UTF-8');
    $subject = htmlspecialchars($exam['exam_info']['subject_area'], ENT_QUOTES, 'UTF-8');
    $total_questions = intval($exam['exam_info']['total_questions']);

    // Type labels
    $type_labels = [
        'mcq' => 'اختيار من متعدد',
        'true_false' => 'صح/خطأ',
        'short_answer' => 'إجابة قصيرة',
        'essay' => 'مقالي'
    ];

    $types = array_map(function ($t) use ($type_labels) {
        return $type_labels[$t] ?? $t;
    }, $exam['exam_info']['question_types']);

    $question_types = implode(', ', $types);

    // Build HTML content
    $html = '<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: dejavusans, sans-serif;
            direction: rtl;
            text-align: right;
            color: #1a1a1a;
            line-height: 1.8;
        }
        
        .header {
            text-align: center;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            margin-bottom: 25px;
        }
        
        .header h1 {
            margin: 0 0 8px 0;
            font-size: 22px;
        }
        
        .header p {
            margin: 0;
            font-size: 12px;
        }
        
        .exam-info {
            background: #f5f5f5;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-right: 4px solid #667eea;
        }
        
        .info-row {
            margin-bottom: 6px;
            font-size: 13px;
        }
        
        .info-label {
            font-weight: bold;
            color: #667eea;
        }
        
        .question {
            margin-bottom: 20px;
            padding: 12px;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            page-break-inside: avoid;
        }
        
        .question-header {
            margin-bottom: 10px;
            padding-bottom: 8px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .question-number {
            background: #667eea;
            color: white;
            border-radius: 6px;
            padding: 4px 10px;
            font-weight: bold;
            font-size: 14px;
            display: inline-block;
            margin-left: 8px;
        }
        
        .badge {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            display: inline-block;
            margin-left: 5px;
        }
        
        .badge-type {
            background: #e8eaf6;
            color: #3f51b5;
        }
        
        .badge-easy {
            background: #e8f5e9;
            color: #4caf50;
        }
        
        .badge-medium {
            background: #fff3e0;
            color: #ff9800;
        }
        
        .badge-hard {
            background: #ffebee;
            color: #f44336;
        }
        
        .question-text {
            font-size: 14px;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 12px;
            line-height: 1.6;
        }
        
        .option {
            padding: 8px 12px;
            margin-bottom: 6px;
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-radius: 5px;
            font-size: 13px;
        }
        
        .option.correct {
            background: #e8f5e9;
            border-color: #4caf50;
            font-weight: 600;
        }
        
        .option-label {
            font-weight: bold;
            color: #667eea;
            margin-left: 6px;
        }
        
        .answer-box {
            margin-top: 12px;
            padding: 10px;
            background: #e8f5e9;
            border-right: 3px solid #4caf50;
            border-radius: 5px;
        }
        
        .answer-label {
            font-weight: bold;
            color: #4caf50;
            font-size: 12px;
            margin-bottom: 4px;
        }
        
        .answer-text {
            font-size: 13px;
            color: #2c3e50;
            line-height: 1.6;
        }
        
        .explanation-box {
            margin-top: 10px;
            padding: 10px;
            background: #e3f2fd;
            border-right: 3px solid #2196f3;
            border-radius: 5px;
        }
        
        .explanation-label {
            font-weight: bold;
            color: #2196f3;
            font-size: 12px;
            margin-bottom: 4px;
        }
        
        .explanation-text {
            font-size: 12px;
            color: #2c3e50;
            line-height: 1.6;
        }
        
        .footer {
            margin-top: 30px;
            text-align: center;
            padding: 12px;
            background: #f5f5f5;
            border-radius: 8px;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #e0e0e0;
        }
    </style>
</head>
<body>';

    // Header
    $html .= '
    <div class="header">
        <h1>امتحان تفاعلي</h1>
        <p>تم الإنشاء بواسطة منصة ألفا - مولد الامتحانات الذكي</p>
    </div>';

    // Exam Info
    $html .= '
    <div class="exam-info">
        <div class="info-row"><span class="info-label">الملف المصدر:</span> ' . $filename . '</div>
        <div class="info-row"><span class="info-label">المادة:</span> ' . $subject . '</div>
        <div class="info-row"><span class="info-label">عدد الأسئلة:</span> ' . $total_questions . ' سؤال</div>
        <div class="info-row"><span class="info-label">أنواع الأسئلة:</span> ' . $question_types . '</div>
        <div class="info-row"><span class="info-label">التاريخ:</span> ' . date('Y-m-d H:i') . '</div>
    </div>';

    // Add questions
    foreach ($exam['questions'] as $idx => $question) {
        $question_num = $idx + 1;
        $question_text = htmlspecialchars($question['question'], ENT_QUOTES, 'UTF-8');

        // Type badge
        $type = $question['type'];
        $type_label = $type_labels[$type] ?? $type;

        // Difficulty badge
        $difficulty_html = '';
        if (isset($question['difficulty'])) {
            $diff = $question['difficulty'];
            $diff_label = $diff === 'easy' ? 'سهل' : ($diff === 'medium' ? 'متوسط' : 'صعب');
            $diff_class = "badge-$diff";
            $difficulty_html = "<span class='badge $diff_class'>$diff_label</span>";
        }

        $html .= '
    <div class="question">
        <div class="question-header">
            <span class="question-number">' . $question_num . '</span>
            <span class="badge badge-type">' . $type_label . '</span>
            ' . $difficulty_html . '
        </div>
        
        <div class="question-text">' . $question_text . '</div>';

        // Add options for MCQ
        if ($type === 'mcq' && isset($question['options'])) {
            foreach ($question['options'] as $i => $option) {
                $option_text = htmlspecialchars($option, ENT_QUOTES, 'UTF-8');
                $option_label = chr(65 + $i);
                $is_correct = $show_answers && $option === $question['correct_answer'];
                $correct_class = $is_correct ? ' correct' : '';

                $html .= '
        <div class="option' . $correct_class . '">
            <span class="option-label">' . $option_label . ')</span>
            ' . $option_text . '
        </div>';
            }
        }

        // Add options for True/False
        if ($type === 'true_false') {
            $tf_options = ['صح', 'خطأ'];
            foreach ($tf_options as $i => $option) {
                $option_label = chr(65 + $i);
                $is_correct = $show_answers && $option === $question['correct_answer'];
                $correct_class = $is_correct ? ' correct' : '';

                $html .= '
        <div class="option' . $correct_class . '">
            <span class="option-label">' . $option_label . ')</span>
            ' . $option . '
        </div>';
            }
        }

        // Show answers if enabled
        if ($show_answers) {
            if ($type === 'short_answer' || $type === 'essay') {
                $answer_text = htmlspecialchars($question['correct_answer'], ENT_QUOTES, 'UTF-8');
                $answer_text = nl2br($answer_text);
                $html .= '
        <div class="answer-box">
            <div class="answer-label">✓ الإجابة النموذجية:</div>
            <div class="answer-text">' . $answer_text . '</div>
        </div>';
            }

            // Add explanation
            if (isset($question['explanation']) && !empty($question['explanation'])) {
                $explanation_text = htmlspecialchars($question['explanation'], ENT_QUOTES, 'UTF-8');
                $explanation_text = nl2br($explanation_text);
                $html .= '
        <div class="explanation-box">
            <div class="explanation-label">ℹ التوضيح:</div>
            <div class="explanation-text">' . $explanation_text . '</div>
        </div>';
            }
        }

        $html .= '
    </div>';
    }

    // Footer
    $html .= '
    <div class="footer">
        <p>تم إنشاء هذا الامتحان بواسطة منصة ألفا</p>
    </div>';

    $html .= '
</body>
</html>';

    // Write HTML to PDF
    $mpdf->WriteHTML($html);

    // Clear output buffer and send PDF
    ob_end_clean();

    // Generate safe filename
    $safe_pdf_name = preg_replace('/[^a-zA-Z0-9._-]/', '_', pathinfo($filename, PATHINFO_FILENAME));
    $pdf_filename = 'exam_' . $safe_pdf_name . '_' . date('Ymd') . '.pdf';

    // Output PDF
    $mpdf->Output($pdf_filename, 'D');

} catch (Exception $e) {
    error_log("[generate_pdf] Exception: " . $e->getMessage());
    error_log("[generate_pdf] Stack trace: " . $e->getTraceAsString());

    ob_end_clean();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'فشل توليد PDF']);
}
?>

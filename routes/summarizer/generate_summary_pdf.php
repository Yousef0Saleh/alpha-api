<?php
// routes/summarizer/generate_summary_pdf.php

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

if (!$data || !isset($data['summary'])) {
    http_response_code(400);
    exit;
}

$summary = $data['summary'];

// Validate summary structure
if (
    !isset($summary['summary']['main_points']) ||
    !isset($summary['summary']['detailed_summary']) ||
    !isset($summary['summary']['key_takeaways']) ||
    !isset($summary['summary']['topics_covered'])
) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'بيانات الملخص غير كاملة']);
    exit;
}

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
    $mpdf->SetTitle('ملخص ذكي');
    $mpdf->SetAuthor('منصة ألفا');
    $mpdf->SetCreator('Alpha Platform');

    // Prepare data
    $filename = htmlspecialchars($summary['original_filename'], ENT_QUOTES, 'UTF-8');
    $file_type = htmlspecialchars($summary['file_type'], ENT_QUOTES, 'UTF-8');
    $file_size = htmlspecialchars($summary['file_size'], ENT_QUOTES, 'UTF-8');
    $detailed_summary = htmlspecialchars($summary['summary']['detailed_summary'], ENT_QUOTES, 'UTF-8');
    $detailed_summary = nl2br($detailed_summary);

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
        
        .file-info {
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
        
        .section {
            margin-bottom: 25px;
        }
        
        .section-title {
            font-size: 16px;
            font-weight: bold;
            color: #667eea;
            margin-bottom: 12px;
            padding-bottom: 6px;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .point-box {
            background: #f9f9f9;
            border: 1px solid #e0e0e0;
            border-right: 3px solid #667eea;
            border-radius: 6px;
            padding: 10px;
            margin-bottom: 8px;
        }
        
        .point-number {
            background: #667eea;
            color: white;
            border-radius: 4px;
            padding: 2px 8px;
            font-weight: bold;
            font-size: 12px;
            margin-left: 8px;
            display: inline-block;
        }
        
        .point-text {
            font-size: 13px;
            color: #2c3e50;
            display: inline;
        }
        
        .detailed-box {
            background: #f9f9f9;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #e0e0e0;
            font-size: 13px;
            line-height: 1.7;
            color: #2c3e50;
            word-wrap: break-word;
            overflow-wrap: break-word;
        }
        
        .takeaway-box {
            background: #e8f5e9;
            border-right: 3px solid #4caf50;
            border-radius: 5px;
            padding: 8px;
            margin-bottom: 6px;
            font-size: 13px;
            color: #2c3e50;
        }
        
        .checkmark {
            color: #4caf50;
            font-weight: bold;
            margin-left: 6px;
        }
        
        .topic-tag {
            display: inline-block;
            background: #e3f2fd;
            color: #1976d2;
            border-radius: 15px;
            padding: 4px 12px;
            margin: 3px;
            font-size: 11px;
            font-weight: 600;
            border: 1px solid #bbdefb;
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
        <h1>ملخص</h1>
        <p>تم الإنشاء بواسطة منصة ألفا - أداة التلخيص الذكي</p>
    </div>';

    // File Info
    $html .= '
    <div class="file-info">
        <div class="info-row"><span class="info-label">اسم الملف:</span> ' . $filename . '</div>
        <div class="info-row"><span class="info-label">نوع الملف:</span> ' . $file_type . '</div>
        <div class="info-row"><span class="info-label">حجم الملف:</span> ' . $file_size . '</div>
        <div class="info-row"><span class="info-label">تاريخ التلخيص:</span> ' . date('Y-m-d H:i') . '</div>
    </div>';

    // Main Points
    if (!empty($summary['summary']['main_points'])) {
        $html .= '
    <div class="section">
        <h2 class="section-title">النقاط الرئيسية</h2>';

        foreach ($summary['summary']['main_points'] as $idx => $point) {
            $point_text = htmlspecialchars($point, ENT_QUOTES, 'UTF-8');
            $point_num = $idx + 1;
            $html .= '
        <div class="point-box">
            <span class="point-number">' . $point_num . '</span>
            <span class="point-text">' . $point_text . '</span>
        </div>';
        }

        $html .= '
    </div>';
    }

    // Detailed Summary
    $html .= '
    <div class="section">
        <h2 class="section-title">الملخص التفصيلي</h2>
        <div class="detailed-box">' . $detailed_summary . '</div>
    </div>';

    // Key Takeaways
    if (!empty($summary['summary']['key_takeaways'])) {
        $html .= '
    <div class="section">
        <h2 class="section-title">الاستنتاجات الرئيسية</h2>';

        foreach ($summary['summary']['key_takeaways'] as $takeaway) {
            $takeaway_text = htmlspecialchars($takeaway, ENT_QUOTES, 'UTF-8');
            $html .= '
        <div class="takeaway-box">
            <span class="checkmark">✓</span>
            ' . $takeaway_text . '
        </div>';
        }

        $html .= '
    </div>';
    }

    // Topics Covered
    if (!empty($summary['summary']['topics_covered'])) {
        $html .= '
    <div class="section">
        <h2 class="section-title">المواضيع المغطاة</h2>
        <div>';

        foreach ($summary['summary']['topics_covered'] as $topic) {
            $topic_text = htmlspecialchars($topic, ENT_QUOTES, 'UTF-8');
            $html .= '<span class="topic-tag">' . $topic_text . '</span> ';
        }

        $html .= '
        </div>
    </div>';
    }

    // Footer
    $html .= '
    <div class="footer">
        <p>تم إنشاء هذا الملخص بواسطة منصة ألفا</p>
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
    $pdf_filename = 'summary_' . $safe_pdf_name . '_' . date('Ymd') . '.pdf';

    // Output PDF
    $mpdf->Output($pdf_filename, 'D');

} catch (Exception $e) {
    error_log("[generate_summary_pdf] Exception: " . $e->getMessage());
    error_log("[generate_summary_pdf] Stack trace: " . $e->getTraceAsString());

    ob_end_clean();
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'فشل توليد PDF']);
}
?>

<?php
/**
 * Email Utility Functions
 * منصة ألفا التعليمية
 */

// use PHPMailer\PHPMailer\PHPMailer;
// use PHPMailer\PHPMailer\Exception;

// require_once __DIR__ . '/../vendor/autoload.php';
// require_once __DIR__ . '/../config/env.php';

// /**
//  * Send password reset email
//  * 
//  * @param string $toEmail Recipient email
//  * @param string $toName Recipient name
//  * @param string $resetToken Reset token
//  * @return bool Success status
//  */
// function sendPasswordResetEmail($toEmail, $toName, $resetToken)
// {
//     $mail = new PHPMailer(true);

//     try {
//         // Get SMTP settings from environment
//         $smtpHost = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
//         $smtpPort = getenv('SMTP_PORT') ?: 587;
//         $smtpUsername = getenv('SMTP_USERNAME') ?: '';
//         $smtpPassword = getenv('SMTP_PASSWORD') ?: '';
//         $smtpFromEmail = getenv('SMTP_FROM_EMAIL') ?: 'noreply@alpha.edu';
//         $smtpFromName = getenv('SMTP_FROM_NAME') ?: 'منصة ألفا';

//         // If SMTP not configured, log and return false
//         if (empty($smtpUsername) || empty($smtpPassword)) {
//             error_log('[EMAIL] SMTP credentials not configured in .env file');
//             return false;
//         }

//         // Server settings
//         $mail->isSMTP();
//         $mail->Host = $smtpHost;
//         $mail->SMTPAuth = true;
//         $mail->Username = $smtpUsername;
//         $mail->Password = $smtpPassword;
//         $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
//         $mail->Port = $smtpPort;
//         $mail->CharSet = 'UTF-8';

//         // Recipients
//         $mail->setFrom($smtpFromEmail, $smtpFromName);
//         $mail->addAddress($toEmail, $toName);

//         // Build reset URL
//         $resetUrl = "https://alpha-edu.up.railway.app/reset-password?token=" . urlencode($resetToken);

//         // Content
//         $mail->isHTML(true);
//         $mail->Subject = 'إعادة تعيين كلمة السر - منصة ألفا';

//         // HTML Body with beautiful template
//         $mail->Body = getPasswordResetEmailTemplate($toName, $resetUrl);

//         // Plain text alternative
//         $mail->AltBody = "مرحباً " . $toName . "،\n\n"
//             . "تلقينا طلبًا لإعادة تعيين كلمة السر الخاصة بحسابك في منصة ألفا.\n\n"
//             . "لإعادة تعيين كلمة السر، يرجى نسخ الرابط التالي في المتصفح:\n"
//             . "$resetUrl\n\n"
//             . "الرابط صالح لمدة ساعة واحدة فقط.\n\n"
//             . "إذا لم تطلب إعادة تعيين كلمة السر، يرجى تجاهل هذا البريد.\n\n"
//             . "مع تحيات،\nفريق منصة ألفا";

//         $mail->send();
//         error_log("[EMAIL] Password reset email sent to: $toEmail");
//         return true;

//     } catch (Exception $e) {
//         error_log("[EMAIL] Failed to send password reset email: {$mail->ErrorInfo}");
//         return false;
//     }
// }

// /**
//  * Send email verification email
//  * 
//  * @param string $toEmail Recipient email
//  * @param string $toName Recipient name
//  * @param string $verificationToken Verification token
//  * @return bool Success status
//  */
// function sendEmailVerification($toEmail, $toName, $verificationToken)
// {
//     $mail = new PHPMailer(true);

//     try {
//         // Get SMTP settings from environment
//         $smtpHost = getenv('SMTP_HOST') ?: 'smtp.gmail.com';
//         $smtpPort = getenv('SMTP_PORT') ?: 587;
//         $smtpUsername = getenv('SMTP_USERNAME') ?: '';
//         $smtpPassword = getenv('SMTP_PASSWORD') ?: '';
//         $smtpFromEmail = getenv('SMTP_FROM_EMAIL') ?: 'noreply@alpha.edu';
//         $smtpFromName = getenv('SMTP_FROM_NAME') ?: 'منصة ألفا';

//         // If SMTP not configured, log and return false
//         if (empty($smtpUsername) || empty($smtpPassword)) {
//             error_log('[EMAIL] SMTP credentials not configured in .env file');
//             return false;
//         }

//         // Server settings
//         $mail->isSMTP();
//         $mail->Host = $smtpHost;
//         $mail->SMTPAuth = true;
//         $mail->Username = $smtpUsername;
//         $mail->Password = $smtpPassword;
//         $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
//         $mail->Port = $smtpPort;
//         $mail->CharSet = 'UTF-8';

//         // Recipients
//         $mail->setFrom($smtpFromEmail, $smtpFromName);
//         $mail->addAddress($toEmail, $toName);

//         // Build verification URL
//         $verifyUrl = "https://alpha-edu.up.railway.app/verify-email?token=" . urlencode($verificationToken);

//         // Content
//         $mail->isHTML(true);
//         $mail->Subject = 'تأكيد البريد الإلكتروني - منصة ألفا';

//         // HTML Body with beautiful template
//         $mail->Body = getEmailVerificationTemplate($toName, $verifyUrl);

//         // Plain text alternative
//         $mail->AltBody = "مرحباً " . $toName . "،\n\n"
//             . "شكراً لتسجيلك في منصة ألفا!\n\n"
//             . "لتفعيل حسابك، يرجى نسخ الرابط التالي في المتصفح:\n"
//             . "$verifyUrl\n\n"
//             . "مع تحيات،\nفريق منصة ألفا";

//         $mail->send();
//         error_log("[EMAIL] Verification email sent to: $toEmail");
//         return true;

//     } catch (Exception $e) {
//         error_log("[EMAIL] Failed to send verification email: {$mail->ErrorInfo}");
//         return false;
//     }
// }


// /**
//  * Get HTML email template for password reset
//  * 
//  * @param string $toName User name
//  * @param string $resetUrl Reset URL
//  * @return string HTML template
//  */
// function getPasswordResetEmailTemplate($toName, $resetUrl)
// {
//     return <<<HTML
// <!DOCTYPE html>
// <html dir="rtl" lang="ar">
// <head>
//     <meta charset="UTF-8">
//     <meta name="viewport" content="width=device-width, initial-scale=1.0">
//     <title>إعادة تعيين كلمة السر</title>
// </head>
// <body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Arial, sans-serif; background-color: #f5f5f5; direction: rtl;">
//     <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f5f5f5; padding: 40px 20px;">
//         <tr>
//             <td align="center">
//                 <table role="presentation" style="width: 100%; max-width: 600px; border-collapse: collapse; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);">
                    
//                     <!-- Header -->
//                     <tr>
//                         <td style="padding: 40px 40px 30px; text-align: center; background-color: #4F46E5; color: #ffffff;">
//                             <h1 style="margin: 0; font-size: 28px; font-weight: 700; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">منصة ألفا</h1>
//                             <p style="margin: 8px 0 0; font-size: 14px; opacity: 0.9;">المنصة التعليمية الذكية</p>
//                         </td>
//                     </tr>
                    
//                     <!-- Content -->
//                     <tr>
//                         <td style="padding: 40px;">
//                             <h2 style="margin: 0 0 20px; color: #1F2937; font-size: 20px; font-weight: 600; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">مرحباً {$toName}</h2>
                            
//                             <p style="margin: 0 0 20px; color: #4B5563; font-size: 16px; line-height: 1.6; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
//                                 تلقينا طلباً لإعادة تعيين كلمة السر الخاصة بحسابك في منصة ألفا.
//                             </p>
                            
//                             <p style="margin: 0 0 30px; color: #4B5563; font-size: 16px; line-height: 1.6; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
//                                 لإعادة تعيين كلمة السر، اضغط على الزر أدناه:
//                             </p>
                            
//                             <!-- Button -->
//                             <table role="presentation" style="margin: 0 auto 30px; border-collapse: collapse;">
//                                 <tr>
//                                     <td style="border-radius: 8px; background-color: #4F46E5;">
//                                         <a href="{$resetUrl}" target="_blank" style="display: inline-block; padding: 14px 32px; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
//                                             إعادة تعيين كلمة السر
//                                         </a>
//                                     </td>
//                                 </tr>
//                             </table>
                            
//                             <p style="margin: 0 0 8px; color: #6B7280; font-size: 14px; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
//                                 أو انسخ الرابط التالي في المتصفح:
//                             </p>
//                             <p style="margin: 0 0 30px; padding: 12px; background-color: #F3F4F6; border-radius: 6px; color: #4F46E5; font-size: 13px; word-break: break-all; direction: ltr; text-align: left; font-family: monospace;">
//                                 {$resetUrl}
//                             </p>
                            
//                             <!-- Warning -->
//                             <div style="padding: 16px; background-color: #FEF3C7; border-right: 4px solid #F59E0B; border-radius: 6px; margin-bottom: 20px;">
//                                 <p style="margin: 0; color: #92400E; font-size: 14px; line-height: 1.5; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
//                                     ⚠️ <strong>تنبيه:</strong> الرابط صالح لمدة ساعة واحدة فقط.
//                                 </p>
//                             </div>
                            
//                             <p style="margin: 0; color: #6B7280; font-size: 14px; line-height: 1.6; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
//                                 إذا لم تطلب إعادة تعيين كلمة السر، يرجى تجاهل هذا البريد. حسابك آمن ولم يتم إجراء أي تغييرات.
//                             </p>
//                         </td>
//                     </tr>
                    
//                     <!-- Footer -->
//                     <tr>
//                         <td style="padding: 30px 40px; background-color: #F9FAFB; border-top: 1px solid #E5E7EB; text-align: center;">
//                             <p style="margin: 0 0 8px; color: #6B7280; font-size: 14px; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
//                                 مع تحيات،<br>
//                                 <strong style="color: #4B5563;">فريق منصة ألفا</strong>
//                             </p>
//                             <p style="margin: 0; color: #9CA3AF; font-size: 12px; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
//                                 © 2025 منصة ألفا. جميع الحقوق محفوظة.
//                             </p>
//                         </td>
//                     </tr>
//                 </table>
//             </td>
//         </tr>
//     </table>
// </body>
// </html>
// HTML;
// }

// /**
//  * Get HTML email template for email verification
//  * 
//  * @param string $toName User name
//  * @param string $verifyUrl Verification URL
//  * @return string HTML template
//  */
// function getEmailVerificationTemplate($toName, $verifyUrl)
// {
//     return <<<HTML
// <!DOCTYPE html>
// <html dir="rtl" lang="ar">
// <head>
//     <meta charset="UTF-8">
//     <meta name="viewport" content="width=device-width, initial-scale=1.0">
//     <title>تأكيد البريد الإلكتروني</title>
// </head>
// <body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Arial, sans-serif; background-color: #f5f5f5; direction: rtl;">
//     <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f5f5f5; padding: 40px 20px;">
//         <tr>
//             <td align="center">
//                 <table role="presentation" style="width: 100%; max-width: 600px; border-collapse: collapse; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);">
                    
//                     <!-- Header -->
//                     <tr>
//                         <td style="padding: 40px 40px 30px; text-align: center; background-color: #4F46E5; color: #ffffff;">
//                             <h1 style="margin: 0; font-size: 28px; font-weight: 700; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">منصة ألفا</h1>
//                             <p style="margin: 8px 0 0; font-size: 14px; opacity: 0.9;">مرحباً بك في منصتك التعليمية</p>
//                         </td>
//                     </tr>
                    
//                     <!-- Content -->
//                     <tr>
//                         <td style="padding: 40px;">
//                             <h2 style="margin: 0 0 20px; color: #1F2937; font-size: 20px; font-weight: 600; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">مرحباً {$toName}</h2>
                            
//                             <p style="margin: 0 0 20px; color: #4B5563; font-size: 16px; line-height: 1.6; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
//                                 شكراً لتسجيلك في منصة ألفا! نحن سعداء بانضمامك إلينا.
//                             </p>
                            
//                             <p style="margin: 0 0 30px; color: #4B5563; font-size: 16px; line-height: 1.6; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
//                                 لتفعيل حسابك والبدء في استخدام المنصة، اضغط على الزر أدناه:
//                             </p>
                            
//                             <!-- Button -->
//                             <table role="presentation" style="margin: 0 auto 30px; border-collapse: collapse;">
//                                 <tr>
//                                     <td style="border-radius: 8px; background-color: #4F46E5;">
//                                         <a href="{$verifyUrl}" target="_blank" style="display: inline-block; padding: 14px 32px; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
//                                             تأكيد البريد الإلكتروني
//                                         </a>
//                                     </td>
//                                 </tr>
//                             </table>
                            
//                             <p style="margin: 0 0 8px; color: #6B7280; font-size: 14px; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
//                                 أو انسخ الرابط التالي في المتصفح:
//                             </p>
//                             <p style="margin: 0 0 30px; padding: 12px; background-color: #F3F4F6; border-radius: 6px; color: #4F46E5; font-size: 13px; word-break: break-all; direction: ltr; text-align: left; font-family: monospace;">
//                                 {$verifyUrl}
//                             </p>
                            
//                             <p style="margin: 0; color: #6B7280; font-size: 14px; line-height: 1.6; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
//                                 إذا لم تقم بالتسجيل في منصة ألفا، يرجى تجاهل هذا البريد.
//                             </p>
//                         </td>
//                     </tr>
                    
//                     <!-- Footer -->
//                     <tr>
//                         <td style="padding: 30px 40px; background-color: #F9FAFB; border-top: 1px solid #E5E7EB; text-align: center;">
//                             <p style="margin: 0 0 8px; color: #6B7280; font-size: 14px; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
//                                 مع تحيات،<br>
//                                 <strong style="color: #4B5563;">فريق منصة ألفا</strong>
//                             </p>
//                             <p style="margin: 0; color: #9CA3AF; font-size: 12px; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
//                                 © 2025 منصة ألفا. جميع الحقوق محفوظة.
//                             </p>
//                         </td>
//                     </tr>
//                 </table>
//             </td>
//         </tr>
//     </table>
// </body>
// </html>
// HTML;
// }

/**
 * Send password reset email via Resend API
 * 
 * @param string $toEmail Recipient email
 * @param string $toName Recipient name
 * @param string $resetToken Reset token
 * @return bool Success status
 */
function sendPasswordResetEmail($toEmail, $toName, $resetToken)
{
    $apiKey = getenv('RESEND_API_KEY') ?: 're_KwC212SG_5njCBPijPoKWMNTwMME5XuqC';
    $resetUrl = "https://alpha-edu.up.railway.app/reset-password?token=" . urlencode($resetToken);
    $htmlBody = getPasswordResetEmailTemplate($toName, $resetUrl);

    $data = [
        "from" => "onboarding@resend.dev",
        "to" => $toEmail,
        "subject" => "إعادة تعيين كلمة السر - منصة ألفا",
        "html" => $htmlBody
    ];

    $ch = curl_init("https://api.resend.com/emails");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $apiKey",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        error_log("[EMAIL] Password reset email sent to: $toEmail");
        return true;
    } else {
        error_log("[EMAIL] Failed to send password reset email: $response");
        return false;
    }
}

/**
 * Send email verification email via Resend API
 * 
 * @param string $toEmail Recipient email
 * @param string $toName Recipient name
 * @param string $verificationToken Verification token
 * @return bool Success status
 */
function sendEmailVerification($toEmail, $toName, $verificationToken)
{
    $apiKey = getenv('RESEND_API_KEY') ?: 're_KwC212SG_5njCBPijPoKWMNTwMME5XuqC';
    $verifyUrl = "https://alpha-edu.up.railway.app/verify-email?token=" . urlencode($verificationToken);
    $htmlBody = getEmailVerificationTemplate($toName, $verifyUrl);

    $data = [
        "from" => "onboarding@resend.dev",
        "to" => $toEmail,
        "subject" => "تأكيد البريد الإلكتروني - منصة ألفا",
        "html" => $htmlBody
    ];

    $ch = curl_init("https://api.resend.com/emails");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $apiKey",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode >= 200 && $httpCode < 300) {
        error_log("[EMAIL] Verification email sent to: $toEmail");
        return true;
    } else {
        error_log("[EMAIL] Failed to send verification email: $response");
        return false;
    }
}

/**
 * Get HTML email template for password reset
 */
function getPasswordResetEmailTemplate($toName, $resetUrl)
{
    return <<<HTML
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8">
<title>إعادة تعيين كلمة السر</title>
</head>
<body style="font-family: Arial, sans-serif; background-color:#f5f5f5; direction: rtl;">
<table style="width:100%; padding:40px 20px;"><tr><td align="center">
<table style="max-width:600px; background:#fff; border-radius:12px; overflow:hidden;">
<tr><td style="padding:40px; text-align:center; background:#4F46E5; color:#fff;">
<h1>منصة ألفا</h1><p>المنصة التعليمية الذكية</p></td></tr>
<tr><td style="padding:40px;">
<h2>مرحباً {$toName}</h2>
<p>تلقينا طلباً لإعادة تعيين كلمة السر الخاصة بحسابك.</p>
<p>اضغط على الزر أدناه لإعادة التعيين:</p>
<p style="text-align:center;"><a href="{$resetUrl}" style="padding:14px 32px; background:#4F46E5; color:#fff; text-decoration:none; border-radius:8px;">إعادة تعيين كلمة السر</a></p>
<p>أو انسخ الرابط التالي في المتصفح:<br><code>{$resetUrl}</code></p>
<p>⚠️ الرابط صالح لمدة ساعة واحدة فقط.</p>
<p>إذا لم تطلب إعادة تعيين كلمة السر، تجاهل هذا البريد.</p>
</td></tr>
<tr><td style="padding:30px; text-align:center; background:#f9fafb; border-top:1px solid #e5e7eb;">
<p>مع تحيات،<br>فريق منصة ألفا</p>
<p>© 2025 منصة ألفا. جميع الحقوق محفوظة.</p>
</td></tr>
</table></td></tr></table>
</body>
</html>
HTML;
}

/**
 * Get HTML email template for email verification
 */
function getEmailVerificationTemplate($toName, $verifyUrl)
{
    return <<<HTML
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
<meta charset="UTF-8">
<title>تأكيد البريد الإلكتروني</title>
</head>
<body style="font-family: Arial, sans-serif; background-color:#f5f5f5; direction: rtl;">
<table style="width:100%; padding:40px 20px;"><tr><td align="center">
<table style="max-width:600px; background:#fff; border-radius:12px; overflow:hidden;">
<tr><td style="padding:40px; text-align:center; background:#4F46E5; color:#fff;">
<h1>منصة ألفا</h1><p>مرحباً بك في منصتك التعليمية</p></td></tr>
<tr><td style="padding:40px;">
<h2>مرحباً {$toName}</h2>
<p>شكراً لتسجيلك في منصة ألفا! لتفعيل حسابك اضغط على الزر أدناه:</p>
<p style="text-align:center;"><a href="{$verifyUrl}" style="padding:14px 32px; background:#4F46E5; color:#fff; text-decoration:none; border-radius:8px;">تأكيد البريد الإلكتروني</a></p>
<p>أو انسخ الرابط التالي في المتصفح:<br><code>{$verifyUrl}</code></p>
<p>إذا لم تقم بالتسجيل في منصة ألفا، تجاهل هذا البريد.</p>
</td></tr>
<tr><td style="padding:30px; text-align:center; background:#f9fafb; border-top:1px solid #e5e7eb;">
<p>مع تحيات،<br>فريق منصة ألفا</p>
<p>© 2025 منصة ألفا. جميع الحقوق محفوظة.</p>
</td></tr>
</table></td></tr></table>
</body>
</html>
HTML;
}

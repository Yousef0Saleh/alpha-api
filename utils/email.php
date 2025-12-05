<?php
/**
 * Email Utility Functions using SendGrid
 * منصة ألفا التعليمية
 */

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/env.php';

use SendGrid\Mail\Mail;

/**
 * Send password reset email
 * 
 * @param string $toEmail Recipient email
 * @param string $toName Recipient name
 * @param string $resetToken Reset token
 * @return bool Success status
 */
function sendPasswordResetEmail($toEmail, $toName, $resetToken)
{
    try {
        // Get SendGrid API key
        $sendgridApiKey = getenv('SENDGRID_API_KEY');
        $fromEmail = getenv('SMTP_FROM_EMAIL') ?: '1yousefsaleh@gmail.com';
        $fromName = getenv('SMTP_FROM_NAME') ?: 'منصة ألفا';

        if (empty($sendgridApiKey)) {
            error_log('[EMAIL] SendGrid API key not configured');
            return false;
        }

        // Build reset URL
        $frontendUrl = getenv('FRONTEND_URL') ?: 'http://192.168.1.5:3000';
        $resetUrl = $frontendUrl . "/reset-password?token=" . urlencode($resetToken);

        // Create email
        $email = new Mail();
        $email->setFrom($fromEmail, $fromName);
        $email->setSubject('إعادة تعيين كلمة السر - منصة ألفا');
        $email->addTo($toEmail, $toName);
        $email->addContent("text/html", getPasswordResetEmailTemplate($toName, $resetUrl));

        // Plain text alternative
        $plainText = "مرحباً " . $toName . "،\n\n"
            . "تلقينا طلبًا لإعادة تعيين كلمة السر الخاصة بحسابك في منصة ألفا.\n\n"
            . "لإعادة تعيين كلمة السر، يرجى نسخ الرابط التالي في المتصفح:\n"
            . "$resetUrl\n\n"
            . "الرابط صالح لمدة ساعة واحدة فقط.\n\n"
            . "إذا لم تطلب إعادة تعيين كلمة السر، يرجى تجاهل هذا البريد.\n\n"
            . "مع تحيات،\nفريق منصة ألفا";
        $email->addContent("text/plain", $plainText);

        // Send via SendGrid
        $sendgrid = new \SendGrid($sendgridApiKey);
        $response = $sendgrid->send($email);

        if ($response->statusCode() >= 200 && $response->statusCode() < 300) {
            error_log("[EMAIL] Password reset email sent to: $toEmail");
            return true;
        } else {
            error_log("[EMAIL] SendGrid error: " . $response->statusCode() . " - " . $response->body());
            return false;
        }

    } catch (Exception $e) {
        error_log("[EMAIL] Failed to send password reset email: " . $e->getMessage());
        return false;
    }
}

/**
 * Send email verification email
 * 
 * @param string $toEmail Recipient email
 * @param string $toName Recipient name
 * @param string $verificationToken Verification token
 * @return bool Success status
 */
function sendEmailVerification($toEmail, $toName, $verificationToken)
{
    try {
        // Get SendGrid API key
        $sendgridApiKey = getenv('SENDGRID_API_KEY');
        $fromEmail = getenv('SMTP_FROM_EMAIL') ?: '1yousefsaleh@gmail.com';
        $fromName = getenv('SMTP_FROM_NAME') ?: 'منصة ألفا';

        if (empty($sendgridApiKey)) {
            error_log('[EMAIL] SendGrid API key not configured');
            return false;
        }

        // Build verification URL
        $frontendUrl = getenv('FRONTEND_URL') ?: 'http://192.168.1.5:3000';
        $verifyUrl = $frontendUrl . "/verify-email?token=" . urlencode($verificationToken);

        // Create email
        $email = new Mail();
        $email->setFrom($fromEmail, $fromName);
        $email->setSubject('تأكيد البريد الإلكتروني - منصة ألفا');
        $email->addTo($toEmail, $toName);
        $email->addContent("text/html", getEmailVerificationTemplate($toName, $verifyUrl));

        // Plain text alternative
        $plainText = "مرحباً " . $toName . "،\n\n"
            . "شكراً لتسجيلك في منصة ألفا!\n\n"
            . "لتفعيل حسابك، يرجى نسخ الرابط التالي في المتصفح:\n"
            . "$verifyUrl\n\n"
            . "مع تحيات،\nفريق منصة ألفا";
        $email->addContent("text/plain", $plainText);

        // Send via SendGrid
        $sendgrid = new \SendGrid($sendgridApiKey);
        $response = $sendgrid->send($email);

        if ($response->statusCode() >= 200 && $response->statusCode() < 300) {
            error_log("[EMAIL] Verification email sent to: $toEmail");
            return true;
        } else {
            error_log("[EMAIL] SendGrid error: " . $response->statusCode() . " - " . $response->body());
            return false;
        }

    } catch (Exception $e) {
        error_log("[EMAIL] Failed to send verification email: " . $e->getMessage());
        return false;
    }
}


/**
 * Get HTML email template for password reset
 * 
 * @param string $toName User name
 * @param string $resetUrl Reset URL
 * @return string HTML template
 */
function getPasswordResetEmailTemplate($toName, $resetUrl)
{
    return <<<HTML
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إعادة تعيين كلمة السر</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Arial, sans-serif; background-color: #f5f5f5; direction: rtl;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f5f5f5; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" style="width: 100%; max-width: 600px; border-collapse: collapse; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);">
                    
                    <!-- Header -->
                    <tr>
                        <td style="padding: 40px 40px 30px; text-align: center; background-color: #4F46E5; color: #ffffff;">
                            <h1 style="margin: 0; font-size: 28px; font-weight: 700; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">منصة ألفا</h1>
                            <p style="margin: 8px 0 0; font-size: 14px; opacity: 0.9;">المنصة التعليمية الذكية</p>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <h2 style="margin: 0 0 20px; color: #1F2937; font-size: 20px; font-weight: 600; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">مرحباً {$toName}</h2>
                            
                            <p style="margin: 0 0 20px; color: #4B5563; font-size: 16px; line-height: 1.6; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
                                تلقينا طلباً لإعادة تعيين كلمة السر الخاصة بحسابك في منصة ألفا.
                            </p>
                            
                            <p style="margin: 0 0 30px; color: #4B5563; font-size: 16px; line-height: 1.6; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
                                لإعادة تعيين كلمة السر، اضغط على الزر أدناه:
                            </p>
                            
                            <!-- Button -->
                            <table role="presentation" style="margin: 0 auto 30px; border-collapse: collapse;">
                                <tr>
                                    <td style="border-radius: 8px; background-color: #4F46E5;">
                                        <a href="{$resetUrl}" target="_blank" style="display: inline-block; padding: 14px 32px; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
                                            إعادة تعيين كلمة السر
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="margin: 0 0 8px; color: #6B7280; font-size: 14px; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
                                أو انسخ الرابط التالي في المتصفح:
                            </p>
                            <p style="margin: 0 0 30px; padding: 12px; background-color: #F3F4F6; border-radius: 6px; color: #4F46E5; font-size: 13px; word-break: break-all; direction: ltr; text-align: left; font-family: monospace;">
                                {$resetUrl}
                            </p>
                            
                            <!-- Warning -->
                            <div style="padding: 16px; background-color: #FEF3C7; border-right: 4px solid #F59E0B; border-radius: 6px; margin-bottom: 20px;">
                                <p style="margin: 0; color: #92400E; font-size: 14px; line-height: 1.5; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
                                    ⚠️ <strong>تنبيه:</strong> الرابط صالح لمدة ساعة واحدة فقط.
                                </p>
                            </div>
                            
                            <p style="margin: 0; color: #6B7280; font-size: 14px; line-height: 1.6; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
                                إذا لم تطلب إعادة تعيين كلمة السر، يرجى تجاهل هذا البريد. حسابك آمن ولم يتم إجراء أي تغييرات.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px 40px; background-color: #F9FAFB; border-top: 1px solid #E5E7EB; text-align: center;">
                            <p style="margin: 0 0 8px; color: #6B7280; font-size: 14px; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
                                مع تحيات،<br>
                                <strong style="color: #4B5563;">فريق منصة ألفا</strong>
                            </p>
                            <p style="margin: 0; color: #9CA3AF; font-size: 12px; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
                                © 2025 منصة ألفا. جميع الحقوق محفوظة.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}

/**
 * Get HTML email template for email verification
 * 
 * @param string $toName User name
 * @param string $verifyUrl Verification URL
 * @return string HTML template
 */
function getEmailVerificationTemplate($toName, $verifyUrl)
{
    return <<<HTML
<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تأكيد البريد الإلكتروني</title>
</head>
<body style="margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Arial, sans-serif; background-color: #f5f5f5; direction: rtl;">
    <table role="presentation" style="width: 100%; border-collapse: collapse; background-color: #f5f5f5; padding: 40px 20px;">
        <tr>
            <td align="center">
                <table role="presentation" style="width: 100%; max-width: 600px; border-collapse: collapse; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);">
                    
                    <!-- Header -->
                    <tr>
                        <td style="padding: 40px 40px 30px; text-align: center; background-color: #4F46E5; color: #ffffff;">
                            <h1 style="margin: 0; font-size: 28px; font-weight: 700; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">منصة ألفا</h1>
                            <p style="margin: 8px 0 0; font-size: 14px; opacity: 0.9;">مرحباً بك في منصتك التعليمية</p>
                        </td>
                    </tr>
                    
                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px;">
                            <h2 style="margin: 0 0 20px; color: #1F2937; font-size: 20px; font-weight: 600; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">مرحباً {$toName}</h2>
                            
                            <p style="margin: 0 0 20px; color: #4B5563; font-size: 16px; line-height: 1.6; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
                                شكراً لتسجيلك في منصة ألفا! نحن سعداء بانضمامك إلينا.
                            </p>
                            
                            <p style="margin: 0 0 30px; color: #4B5563; font-size: 16px; line-height: 1.6; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
                                لتفعيل حسابك والبدء في استخدام المنصة، اضغط على الزر أدناه:
                            </p>
                            
                            <!-- Button -->
                            <table role="presentation" style="margin: 0 auto 30px; border-collapse: collapse;">
                                <tr>
                                    <td style="border-radius: 8px; background-color: #4F46E5;">
                                        <a href="{$verifyUrl}" target="_blank" style="display: inline-block; padding: 14px 32px; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
                                            تأكيد البريد الإلكتروني
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="margin: 0 0 8px; color: #6B7280; font-size: 14px; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
                                أو انسخ الرابط التالي في المتصفح:
                            </p>
                            <p style="margin: 0 0 30px; padding: 12px; background-color: #F3F4F6; border-radius: 6px; color: #4F46E5; font-size: 13px; word-break: break-all; direction: ltr; text-align: left; font-family: monospace;">
                                {$verifyUrl}
                            </p>
                            
                            <p style="margin: 0; color: #6B7280; font-size: 14px; line-height: 1.6; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
                                إذا لم تقم بالتسجيل في منصة ألفا، يرجى تجاهل هذا البريد.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px 40px; background-color: #F9FAFB; border-top: 1px solid #E5E7EB; text-align: center;">
                            <p style="margin: 0 0 8px; color: #6B7280; font-size: 14px; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
                                مع تحيات،<br>
                                <strong style="color: #4B5563;">فريق منصة ألفا</strong>
                            </p>
                            <p style="margin: 0; color: #9CA3AF; font-size: 12px; font-family: 'Segoe UI', Tahoma, Arial, sans-serif;">
                                © 2025 منصة ألفا. جميع الحقوق محفوظة.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
}
?>
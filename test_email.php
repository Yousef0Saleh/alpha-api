<?php
require_once __DIR__ . '/utils/email.php';

$toEmail = '1yousefsaleh@gmail.com';
$toName = 'Yousef';
$resetToken = bin2hex(random_bytes(16));

// جرب إرسال ايميل إعادة تعيين كلمة السر
if (sendPasswordResetEmail($toEmail, $toName, $resetToken)) {
    echo "Password reset email sent successfully!\n";
} else {
    echo "Failed to send password reset email.\n";
}

// جرب إرسال ايميل تأكيد البريد الإلكتروني
$verificationToken = bin2hex(random_bytes(16)); // رمز عشوائي لتجربة verification
if (sendEmailVerification($toEmail, $toName, $verificationToken)) {
    echo "Verification email sent successfully!\n";
} else {
    echo "Failed to send verification email.\n";
}

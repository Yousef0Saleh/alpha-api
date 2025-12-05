<?php
/**
 * Test Email Sending
 * Quick test to verify SMTP configuration works
 */

require_once __DIR__ . '/config/env.php';
require_once __DIR__ . '/utils/email.php';

echo "Testing Email Configuration...\n\n";

// Check if SMTP credentials are loaded
$smtpHost = getenv('SMTP_HOST');
$smtpUsername = getenv('SMTP_USERNAME');
$smtpPassword = getenv('SMTP_PASSWORD');

echo "SMTP Host: " . ($smtpHost ?: 'NOT SET') . "\n";
echo "SMTP Username: " . ($smtpUsername ?: 'NOT SET') . "\n";
echo "SMTP Password: " . (empty($smtpPassword) ? 'NOT SET' : '***SET***') . "\n\n";

if (empty($smtpUsername) || empty($smtpPassword)) {
  die("❌ SMTP credentials not configured!\n");
}

echo "✓ SMTP credentials found\n\n";

// Test sending email
echo "Attempting to send test email...\n";

try {
  $testEmail = '1yousefsaleh@gmail.com'; // sending to yourself for testing
  $testName = 'Test User';
  $testToken = 'test_token_' . bin2hex(random_bytes(16));

  $result = sendEmailVerification($testEmail, $testName, $testToken);

  if ($result) {
    echo "✅ SUCCESS! Email sent successfully!\n";
    echo "Check the inbox: $testEmail\n";
  } else {
    echo "❌ FAILED! Email was not sent.\n";
    echo "Check error logs for details.\n";
  }
} catch (Exception $e) {
  echo "❌ ERROR: " . $e->getMessage() . "\n";
}

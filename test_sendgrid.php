<?php
/**
 * Test SendGrid Email
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/env.php';

use SendGrid\Mail\Mail;

echo "=== SendGrid Test ===\n\n";

// Get settings
$sendgridApiKey = getenv('SENDGRID_API_KEY');
$fromEmail = getenv('SMTP_FROM_EMAIL') ?: '1yousefsaleh@gmail.com';
$toEmail = 'yousef.programmer100@gmail.com';

echo "SENDGRID_API_KEY: " . (empty($sendgridApiKey) ? 'NOT SET' : 'SET (length: ' . strlen($sendgridApiKey) . ')') . "\n";
echo "FROM_EMAIL: $fromEmail\n";
echo "TO_EMAIL: $toEmail\n\n";

if (empty($sendgridApiKey)) {
  die("❌ SendGrid API key not configured!\n");
}

echo "Sending test email...\n";

try {
  $email = new Mail();
  $email->setFrom($fromEmail, 'منصة ألفا - Test');
  $email->setSubject('Test Email from Alpha Platform');
  $email->addTo($toEmail, 'Test User');
  $email->addContent("text/html", "<h1>Test Email</h1><p>This is a test email from SendGrid!</p>");
  $email->addContent("text/plain", "Test Email\n\nThis is a test email from SendGrid!");

  $sendgrid = new \SendGrid($sendgridApiKey);
  $response = $sendgrid->send($email);

  echo "\n=== Response ===\n";
  echo "Status Code: " . $response->statusCode() . "\n";
  echo "Body: " . $response->body() . "\n";
  echo "Headers: " . print_r($response->headers(), true) . "\n";

  if ($response->statusCode() >= 200 && $response->statusCode() < 300) {
    echo "\n✅ Email sent successfully!\n";
    echo "Check $toEmail inbox (or spam folder)\n";
  } else {
    echo "\n❌ Failed to send email!\n";
    echo "Status: " . $response->statusCode() . "\n";
  }

} catch (Exception $e) {
  echo "\n❌ Error: " . $e->getMessage() . "\n";
  echo "Full error: " . print_r($e, true) . "\n";
}
?>
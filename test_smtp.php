<?php
/**
 * Test SMTP Connection on Railway
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/config/env.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

echo "=== SMTP Test ===\n\n";

// Get SMTP settings
$smtpHost = getenv('SMTP_HOST');
$smtpPort = getenv('SMTP_PORT');
$smtpUsername = getenv('SMTP_USERNAME');
$smtpPassword = getenv('SMTP_PASSWORD');
$smtpFromEmail = getenv('SMTP_FROM_EMAIL');

echo "SMTP_HOST: " . ($smtpHost ?: 'NOT SET') . "\n";
echo "SMTP_PORT: " . ($smtpPort ?: 'NOT SET') . "\n";
echo "SMTP_USERNAME: " . ($smtpUsername ?: 'NOT SET') . "\n";
echo "SMTP_PASSWORD: " . (empty($smtpPassword) ? 'NOT SET' : '***SET***') . "\n";
echo "SMTP_FROM_EMAIL: " . ($smtpFromEmail ?: 'NOT SET') . "\n\n";

if (empty($smtpUsername) || empty($smtpPassword)) {
  die("❌ SMTP credentials not configured!\n");
}

echo "Testing SMTP connection...\n";

$mail = new PHPMailer(true);

try {
  $mail->isSMTP();
  $mail->Host = $smtpHost;
  $mail->SMTPAuth = true;
  $mail->Username = $smtpUsername;
  $mail->Password = $smtpPassword;
  $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
  $mail->Port = $smtpPort;
  $mail->SMTPDebug = 2; // Enable verbose debug output
  $mail->CharSet = 'UTF-8';

  // Try to connect
  $mail->smtpConnect();

  echo "\n✅ SMTP connection successful!\n";

  // Close connection
  $mail->smtpClose();

} catch (Exception $e) {
  echo "\n❌ SMTP connection failed!\n";
  echo "Error: " . $e->getMessage() . "\n";
  echo "\nTroubleshooting:\n";
  echo "1. Check if SMTP_PASSWORD is correct\n";
  echo "2. Gmail may block Railway IPs - try using App Password\n";
  echo "3. Enable 'Less secure app access' in Gmail settings\n";
}
?>
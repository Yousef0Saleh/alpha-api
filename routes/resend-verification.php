<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../config/cors.php';
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/email.php';

// Rate limiting (file-based by IP)
$maxAttempts = 3;
$blockMinutes = 10;
$ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
$cacheDir = sys_get_temp_dir() . '/alpha_resend_attempts';
@mkdir($cacheDir, 0700, true);
$ipFile = $cacheDir . '/resend_' . md5($ip);

function getAttempts($file)
{
  if (!file_exists($file))
    return ['count' => 0, 'time' => 0];
  $json = @file_get_contents($file);
  $data = @json_decode($json, true);
  if (!is_array($data))
    return ['count' => 0, 'time' => 0];
  return $data;
}

function setAttempts($file, $count, $time)
{
  $data = ['count' => $count, 'time' => $time];
  @file_put_contents($file, json_encode($data), LOCK_EX);
}

// Check block
$attemptData = getAttempts($ipFile);
$blocked = false;
if ($attemptData['count'] >= $maxAttempts) {
  $elapsed = time() - ($attemptData['time'] ?? 0);
  if ($elapsed < ($blockMinutes * 60)) {
    $blocked = true;
  } else {
    setAttempts($ipFile, 0, 0);
    $attemptData = ['count' => 0, 'time' => 0];
  }
}

if ($blocked) {
  http_response_code(429);
  echo json_encode(["status" => "error", "message" => "عدد كبير من المحاولات. حاول مرة أخرى لاحقًا."]);
  exit;
}

// Read input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

function respond($code, $payload)
{
  http_response_code($code);
  echo json_encode($payload);
  exit;
}

if (!is_array($data))
  respond(400, ["status" => "error", "message" => "Invalid JSON"]);

$email = isset($data['email']) ? trim($data['email']) : '';

if ($email === '') {
  respond(400, ["status" => "error", "message" => "البريد الإلكتروني مطلوب"]);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  respond(400, ["status" => "error", "message" => "البريد الإلكتروني غير صالح"]);
}

try {
  // Check if user exists
  $stmt = $pdo->prepare("SELECT id, name, email, email_verified FROM users WHERE email = :email LIMIT 1");
  $stmt->execute([':email' => $email]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  // Security: Always return same message whether user exists or not
  // This prevents email enumeration attacks

  if ($user) {
    // Check if already verified
    if ($user['email_verified']) {
      // Don't reveal that email is already verified
    } else {
      // Generate new verification token
      $token = bin2hex(random_bytes(32)); // 64 character hex string

      // Update token
      $updateStmt = $pdo->prepare(
        "UPDATE users 
                 SET email_verification_token = :token 
                 WHERE id = :user_id"
      );
      $updateStmt->execute([
        ':user_id' => $user['id'],
        ':token' => $token
      ]);

      // Send email
      $emailSent = sendEmailVerification($user['email'], $user['name'], $token);

      // For testing - log the token
      error_log("VERIFICATION TOKEN: $token");

      if (!$emailSent) {
        error_log("[RESEND] Failed to send email to: {$user['email']}");
        // Don't reveal email sending failure to user
      }
    }
  }

  // Increment attempt (even on success)
  $count = ($attemptData['count'] ?? 0) + 1;
  $time = time();
  setAttempts($ipFile, $count, $time);

  // Always return success message (security)
  respond(200, [
    "status" => "success",
    "message" => "إذا كان البريد الإلكتروني مسجلاً وغير مفعل، ستصلك رسالة تفعيل جديدة."
  ]);

} catch (PDOException $e) {
  error_log("Resend verification error: " . $e->getMessage());
  error_log("Error trace: " . $e->getTraceAsString());

  respond(500, [
    "status" => "error",
    "message" => "حدث خطأ. حاول مرة أخرى."
  ]);
}

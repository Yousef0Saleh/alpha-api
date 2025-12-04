<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../config/cors.php';
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

require_once __DIR__ . '/../config/db.php';

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

$token = isset($data['token']) ? trim($data['token']) : '';

if ($token === '') {
  respond(400, ["status" => "error", "message" => "التوكن مطلوب"]);
}

try {
  // Find user with this verification token
  $stmt = $pdo->prepare("SELECT id, name, email, email_verified FROM users WHERE email_verification_token = :token LIMIT 1");
  $stmt->execute([':token' => $token]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$user) {
    respond(400, ["status" => "error", "message" => "رابط التحقق غير صحيح أو منتهي الصلاحية"]);
  }

  // Check if already verified
  if ($user['email_verified']) {
    respond(200, [
      "status" => "success",
      "message" => "تم تأكيد بريدك الإلكتروني مسبقاً",
      "already_verified" => true
    ]);
  }

  // Verify the email
  $updateStmt = $pdo->prepare(
    "UPDATE users 
         SET email_verified = TRUE, 
             email_verified_at = NOW(), 
             email_verification_token = NULL 
         WHERE id = :user_id"
  );
  $updateStmt->execute([':user_id' => $user['id']]);

  error_log("[VERIFY] Email verified for user: {$user['email']}");

  respond(200, [
    "status" => "success",
    "message" => "تم تأكيد بريدك الإلكتروني بنجاح! يمكنك الآن تسجيل الدخول",
    "email" => $user['email']
  ]);

} catch (PDOException $e) {
  error_log("Email verification error: " . $e->getMessage());
  error_log("Error trace: " . $e->getTraceAsString());

  respond(500, [
    "status" => "error",
    "message" => "حدث خطأ. حاول مرة أخرى."
  ]);
}

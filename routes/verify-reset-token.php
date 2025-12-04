<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../config/cors.php';
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

require_once __DIR__ . '/../config/db.php';

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
  respond(400, ["status" => "error", "message" => "Token مطلوب"]);
}

try {
  // Hash the token to compare with database
  $tokenHash = hash('sha256', $token);

  // Find token in database
  $stmt = $pdo->prepare(
    "SELECT t.id, t.user_id, t.expires_at, t.used_at, u.email, u.is_banned
         FROM password_reset_tokens t
         JOIN users u ON t.user_id = u.id
         WHERE t.token = :token
         LIMIT 1"
  );
  $stmt->execute([':token' => $tokenHash]);
  $tokenData = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$tokenData) {
    respond(400, ["status" => "error", "message" => "الرابط غير صالح"]);
  }

  // Check if token has been used
  if ($tokenData['used_at'] !== null) {
    respond(400, ["status" => "error", "message" => "تم استخدام هذا الرابط بالفعل"]);
  }

  // Check if token has expired
  $now = time();
  $expiresAt = strtotime($tokenData['expires_at']);
  if ($now > $expiresAt) {
    respond(400, ["status" => "error", "message" => "انتهت صلاحية الرابط"]);
  }

  // Check if user is banned
  if (!empty($tokenData['is_banned']) && $tokenData['is_banned'] == 1) {
    respond(403, ["status" => "error", "message" => "حسابك محظور"]);
  }

  // Token is valid
  respond(200, [
    "status" => "success",
    "message" => "Token صالح",
    "email" => $tokenData['email']
  ]);

} catch (PDOException $e) {
  error_log("Token verification error: " . $e->getMessage());
  respond(500, ["status" => "error", "message" => "حدث خطأ. حاول مرة أخرى."]);
}

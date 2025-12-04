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
$password = isset($data['password']) ? $data['password'] : '';

if ($token === '' || $password === '') {
  respond(400, ["status" => "error", "message" => "Token وكلمة السر مطلوبين"]);
}

// Validate password
if (strlen($password) < 8) {
  respond(400, ["status" => "error", "message" => "كلمة السر يجب أن تكون 8 حروف على الأقل"]);
}

if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
  respond(400, ["status" => "error", "message" => "كلمة السر يجب أن تحتوي على حروف وأرقام"]);
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

  // Hash new password
  $algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
  $hashedPassword = password_hash($password, $algo);

  // Begin transaction
  $pdo->beginTransaction();

  try {
    // Update user password
    $updateStmt = $pdo->prepare("UPDATE users SET password = :password WHERE id = :user_id");
    $updateStmt->execute([
      ':password' => $hashedPassword,
      ':user_id' => $tokenData['user_id']
    ]);

    // Mark token as used
    $markUsedStmt = $pdo->prepare("UPDATE password_reset_tokens SET used_at = NOW() WHERE id = :token_id");
    $markUsedStmt->execute([':token_id' => $tokenData['id']]);

    // Delete old unused tokens for this user (cleanup)
    $cleanupStmt = $pdo->prepare(
      "DELETE FROM password_reset_tokens 
             WHERE user_id = :user_id 
             AND id != :current_token_id"
    );
    $cleanupStmt->execute([
      ':user_id' => $tokenData['user_id'],
      ':current_token_id' => $tokenData['id']
    ]);

    // Log activity (if activity_logs table exists)
    try {
      $logStmt = $pdo->prepare(
        "INSERT INTO activity_logs (user_id, activity_type, description, ip_address, user_agent) 
                 VALUES (:user_id, :activity_type, :description, :ip_address, :user_agent)"
      );
      $logStmt->execute([
        ':user_id' => $tokenData['user_id'],
        ':activity_type' => 'password_reset',
        ':description' => 'تم إعادة تعيين كلمة السر',
        ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
      ]);
    } catch (PDOException $e) {
      // Activity logs might not exist, ignore
      error_log("Activity log failed (optional): " . $e->getMessage());
    }

    $pdo->commit();

    respond(200, [
      "status" => "success",
      "message" => "تم إعادة تعيين كلمة السر بنجاح"
    ]);

  } catch (PDOException $e) {
    $pdo->rollBack();
    throw $e;
  }

} catch (PDOException $e) {
  error_log("Password reset error: " . $e->getMessage());
  respond(500, ["status" => "error", "message" => "حدث خطأ. حاول مرة أخرى."]);
}

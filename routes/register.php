<?php
header("Content-Type: application/json; charset=UTF-8");
require_once __DIR__ . '/../config/cors.php'; // مهم يكون دومين محدد

// limit request size (e.g. 1MB)
$maxLen = 1024 * 1024; // 1MB
$length = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
if ($length > $maxLen) {
  http_response_code(413); // Payload Too Large
  echo json_encode(["status" => "error", "message" => "البيانات المرسلة كبيرة جدًا"]);
  exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../utils/email.php';

// read body & decode
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// simple helper to return
function respond($code, $payload)
{
  http_response_code($code);
  echo json_encode($payload);
  exit;
}

// basic server-side validation
if (!is_array($data))
  respond(400, ["status" => "error", "message" => "Invalid JSON"]);

$name = isset($data['name']) ? trim($data['name']) : '';
$email = isset($data['email']) ? trim($data['email']) : '';
$password = isset($data['password']) ? $data['password'] : '';
$grade = isset($data['grade']) ? trim($data['grade']) : null;

// validate required fields
if ($name === '' || $email === '' || $password === '') {
  respond(400, ["status" => "error", "message" => "الاسم والبريد وكلمة السر مطلوبين"]);
}

// validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  respond(400, ["status" => "error", "message" => "البريد الالكتروني غير صالح"]);
}

// validate password length & complexity (min 8, at least letter + number)
if (strlen($password) < 8) {
  respond(400, ["status" => "error", "message" => "الباسوورد لازم تكون 8 حروف على الاقل"]);
}
if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
  respond(400, ["status" => "error", "message" => "الباسوورد لازم تبقا حروف كابيتال وسمال وارقام"]);
}

// validate grade if provided (allow only expected values)
$allowedGrades = ['first', 'second', 'third', null];
if (!in_array($grade, $allowedGrades, true)) {
  respond(400, ["status" => "error", "message" => "اختار صفك الدراسي"]);
}

// Hash password: prefer ARGON2ID if available, otherwise PASSWORD_DEFAULT (bcrypt)
$algo = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
$hashed = password_hash($password, $algo);

// insert user
try {
  // Generate verification token
  $verificationToken = bin2hex(random_bytes(32)); // 64 character hex string

  $stmt = $pdo->prepare("INSERT INTO users (name, email, password, grade, email_verified, email_verification_token) VALUES (:name, :email, :password, :grade, FALSE, :token)");
  $stmt->execute([
    ':name' => $name,
    ':email' => $email,
    ':password' => $hashed,
    ':grade' => $grade,
    ':token' => $verificationToken
  ]);

  // Send verification email
  $emailSent = sendEmailVerification($email, $name, $verificationToken);

  // For testing - log the token
  error_log("VERIFICATION TOKEN: $verificationToken");

  if (!$emailSent) {
    error_log("[REGISTER] Failed to send verification email to: $email");
  }

  // created
  respond(201, [
    "status" => "success",
    "message" => "تم إنشاء الحساب بنجاح! تحقق من بريدك الإلكتروني لتفعيل حسابك",
    "email_sent" => $emailSent
  ]);
} catch (PDOException $e) {
  // Duplicate entry code for MySQL is 23000
  if ($e->getCode() === '23000') {
    respond(409, ["status" => "error", "message" => "الايميل دا مسجل فعلا"]);
  }
  // log internal error (don't expose details to client)
  error_log("Register error: " . $e->getMessage());
  respond(500, ["status" => "error", "message" => "Server error"]);
}

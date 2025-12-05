<?php
/**
 * Make user admin on Railway database
 */

// Railway Database Configuration
$DB_HOST = 'ballast.proxy.rlwy.net';
$DB_PORT = '45417';
$DB_NAME = 'railway';
$DB_USER = 'root';
$DB_PASS = 'HkeyPzhMvuEPkFTwhXgFsVmxQTtUQVTR';

echo "Connecting to Railway database...\n";

$conn = new PDO(
  "mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4",
  $DB_USER,
  $DB_PASS,
  [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

echo "✓ Connected!\n\n";

// ============================================
// CHANGE THESE VALUES:
// ============================================
$email = '1yousefsaleh@gmail.com';  // البريد بتاعك
$newPassword = '123@Yousef';         // كلمة السر الجديدة
$changePassword = false;             // true = هيغير الباسوورد، false = هيسيب الباسوورد زي ما هو

// Hash password if needed
$hashedPassword = $changePassword ? password_hash($newPassword, PASSWORD_DEFAULT) : null;

try {
  // First, check if user exists
  $stmt = $conn->prepare("SELECT id, name, email, role FROM users WHERE email = ?");
  $stmt->execute([$email]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$user) {
    echo "⚠️ User not found. Creating new admin user...\n\n";

    // Create new admin user
    $name = 'Yousef Saleh';  // Change this if needed
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, email_verified, is_banned, created_at) VALUES (?, ?, ?, 'admin', 1, 0, NOW())");
    $stmt->execute([$name, $email, $hashedPassword]);

    echo "✅ New admin user created!\n";
    echo "   Name: $name\n";
    echo "   Email: $email\n";
    echo "   Password: $newPassword\n";
    echo "   Role: admin\n";
    echo "\n🎉 You can now login as admin!\n";
    exit;
  }

  echo "📧 Found existing user:\n";
  echo "   ID: {$user['id']}\n";
  echo "   Name: {$user['name']}\n";
  echo "   Email: {$user['email']}\n";
  echo "   Current Role: {$user['role']}\n\n";

  // Update existing user
  if ($changePassword) {
    $stmt = $conn->prepare("UPDATE users SET role = 'admin', password = ?, email_verified = 1, is_banned = 0 WHERE email = ?");
    $stmt->execute([$hashedPassword, $email]);
  } else {
    $stmt = $conn->prepare("UPDATE users SET role = 'admin', email_verified = 1, is_banned = 0 WHERE email = ?");
    $stmt->execute([$email]);
  }

  echo "✅ User updated successfully!\n";
  echo "   Email: $email\n";
  echo "   Role: admin\n";
  echo "   Email Verified: Yes\n";
  echo "   Is Banned: No\n";

  if ($changePassword) {
    echo "   New Password: $newPassword\n";
  } else {
    echo "   Password: (unchanged)\n";
  }

  echo "\n🎉 You can now login as admin!\n";

} catch (PDOException $e) {
  echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
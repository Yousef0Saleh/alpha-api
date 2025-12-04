<?php
require_once __DIR__ . '/../config/cors.php';
header("Content-Type: application/json; charset=UTF-8");

session_start();

echo json_encode([
  'status' => 'success',
  'user' => $_SESSION['user'] ?? null
]);

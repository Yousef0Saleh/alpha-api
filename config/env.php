<?php
/**
 * Environment Variable Loader
 * Loads variables from .env file into PHP environment
 */

$envPath = __DIR__ . '/../.env';

if (!file_exists($envPath)) {
  error_log("[ENV] .env file not found at: $envPath");
  return;
}

$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

foreach ($lines as $line) {
  // Skip comments
  if (strpos(trim($line), '#') === 0) {
    continue;
  }

  // Parse key=value pairs
  if (strpos($line, '=') !== false) {
    list($key, $value) = explode('=', $line, 2);
    $key = trim($key);
    $value = trim($value);

    // Remove quotes if present
    $value = trim($value, '"\'');

    // Set environment variable
    putenv("$key=$value");
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
  }
}

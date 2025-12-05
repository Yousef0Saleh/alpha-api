<?php
/**
 * Environment Variable Loader
 * Loads variables from Railway environment or .env file
 */

// Railway/production environment variables are already in $_ENV and getenv()
// Only load from .env file if we're in local development

$envPath = __DIR__ . '/../.env';

// If .env file exists (local development), load it
if (file_exists($envPath)) {
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

      // Only set if not already set by Railway
      if (!getenv($key)) {
        putenv("$key=$value");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
      }
    }
  }
} else {
  // On Railway: environment variables are already available
  // No need to do anything, they're in $_ENV and getenv()
  error_log("[ENV] Using Railway environment variables (no .env file needed)");
}

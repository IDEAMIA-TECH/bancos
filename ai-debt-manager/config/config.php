<?php
// Application configuration
define('APP_NAME', 'AI Debt Manager');
define('APP_URL', 'https://ideamia-dev.com/deudas');
define('APP_VERSION', '1.0.0');

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 1);

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error.log');

// Time zone
date_default_timezone_set('America/Mexico_City');

// Security
define('HASH_COST', 12); // For password hashing
define('TOKEN_EXPIRY', 3600); // 1 hour in seconds

// File upload configuration
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_FILE_TYPES', ['jpg', 'jpeg', 'png', 'pdf']);
define('UPLOAD_DIR', __DIR__ . '/../uploads/');

// Cache configuration
define('CACHE_ENABLED', true);
define('CACHE_DIR', __DIR__ . '/../cache/');
define('CACHE_EXPIRY', 3600); // 1 hour in seconds

// API rate limiting
define('RATE_LIMIT', 100); // requests per hour
define('RATE_LIMIT_WINDOW', 3600); // 1 hour in seconds

// Notification settings
define('EMAIL_FROM', 'noreply@ideamia-dev.com');
define('EMAIL_FROM_NAME', 'AI Debt Manager');
define('SMTP_HOST', 'smtp.ideamia-dev.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your_smtp_username');
define('SMTP_PASS', 'your_smtp_password');

// Feature flags
define('ENABLE_AI_FEATURES', true);
define('ENABLE_BANK_CONNECTION', true);
define('ENABLE_DEBT_CONSOLIDATION', true);
define('ENABLE_PAYMENT_SCHEDULING', true);

// Debug mode
define('DEBUG_MODE', true); 
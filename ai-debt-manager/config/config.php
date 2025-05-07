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
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/../logs/error_log');

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
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com');
define('SMTP_PASS', 'your-email-password');
define('SMTP_FROM', 'your-email@gmail.com');
define('SMTP_FROM_NAME', APP_NAME);

// Feature flags
define('ENABLE_AI_FEATURES', true);
define('ENABLE_BANK_CONNECTION', true);
define('ENABLE_DEBT_CONSOLIDATION', true);
define('ENABLE_PAYMENT_SCHEDULING', true);

// Debug mode
define('DEBUG_MODE', true);

// Configuración de seguridad
define('SESSION_LIFETIME', 3600); // 1 hora
define('CSRF_TOKEN_LIFETIME', 3600); // 1 hora

// Crear directorio de logs si no existe
if (!file_exists(__DIR__ . '/../logs')) {
    mkdir(__DIR__ . '/../logs', 0755, true);
}

// Configuración de sesión
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
ini_set('session.cookie_samesite', 'Strict');
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
ini_set('session.use_strict_mode', 1);

// Include database configuration
require_once __DIR__ . '/database.php';

// Función para establecer la conexión a la base de datos
function getDatabaseConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            
            // Verificar la conexión
            $pdo->query("SELECT 1");
            
            return $pdo;
        } catch (PDOException $e) {
            error_log("Error de conexión a la base de datos: " . $e->getMessage());
            throw new Exception("Error de conexión a la base de datos");
        }
    }
    
    return $pdo;
}

// Establecer la conexión global
try {
    $pdo = getDatabaseConnection();
} catch (Exception $e) {
    error_log("Error al establecer la conexión global: " . $e->getMessage());
    // No lanzamos la excepción aquí para permitir que la aplicación maneje el error
}

// Función para verificar la conexión a la base de datos
function checkDatabaseConnection() {
    global $pdo;
    try {
        if (!isset($pdo) || !($pdo instanceof PDO)) {
            $pdo = getDatabaseConnection();
        }
        $pdo->query("SELECT 1");
        return true;
    } catch (Exception $e) {
        error_log("Error al verificar la conexión: " . $e->getMessage());
        return false;
    }
}

// Verificar la conexión al inicio
if (!checkDatabaseConnection()) {
    error_log("No se pudo establecer la conexión inicial a la base de datos");
} 
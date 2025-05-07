<?php
// General Configuration
define('APP_NAME', 'AI Debt Manager');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'https://ideamia.com.mx/deudas'); // URL de producción
define('APP_ROOT', dirname(__DIR__));

// Session Configuration
if (session_status() === PHP_SESSION_NONE) {
    // Solo configurar la sesión si aún no está activa
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 1); // Habilitado para HTTPS
}

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Deshabilitado en producción

// Time Zone
date_default_timezone_set('America/Mexico_City');

// Load Required Files
require_once 'database.php';
require_once 'belvo.php'; 
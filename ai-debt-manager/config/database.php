<?php
// Database Configuration for cPanel
// Replace these values with your cPanel database credentials
define('DB_HOST', 'localhost'); // Usually your MySQL hostname from cPanel
define('DB_NAME', 'ideamiadev_deudas'); // Your database name from cPanel
define('DB_USER', 'ideamia_admin'); // Your database username from cPanel
define('DB_PASS', 'e$PkKPqDJ7N#m?jN'); // Your database password from cPanel
define('DB_CHARSET', 'utf8mb4');

// Create PDO connection
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Log error and show generic message
    error_log("Database connection error: " . $e->getMessage());
    die("Error de conexión a la base de datos. Por favor, intente más tarde.");
} 
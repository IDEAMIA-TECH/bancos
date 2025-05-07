<?php
// Prevent any output before headers
ob_start();

require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/banks.php';
require_once __DIR__ . '/../../includes/auth_functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set JSON response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-CSRF-Token');
header('Access-Control-Allow-Credentials: true');

try {
    // Check if user is logged in
    if (!isLoggedIn()) {
        throw new Exception('Sesión expirada. Por favor, inicie sesión nuevamente.');
    }

    // Verify CSRF token
    $csrf_token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($csrf_token) || !isset($_SESSION['csrf_token']) || $csrf_token !== $_SESSION['csrf_token']) {
        throw new Exception('Token de seguridad inválido');
    }

    // Obtener el ID del banco
    $bankId = $_GET['bank_id'] ?? '';

    // Verificar que el banco existe
    if (!isset($SUPPORTED_BANKS[$bankId])) {
        throw new Exception('Banco no soportado');
    }

    // Obtener los campos del banco
    $bank = $SUPPORTED_BANKS[$bankId];

    // Devolver los campos
    echo json_encode([
        'success' => true,
        'fields' => $bank['fields']
    ]);

} catch (Exception $e) {
    error_log('Error en fields.php: ' . $e->getMessage());
    http_response_code($e->getMessage() === 'Sesión expirada. Por favor, inicie sesión nuevamente.' ? 401 : 400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Ensure all output is sent
ob_end_flush(); 
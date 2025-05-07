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
header('Access-Control-Allow-Methods: POST');
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

    // Verify request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    // Get bank ID and verify it exists
    $bank_id = $_POST['bank_id'] ?? '';
    if (empty($bank_id) || !isset($SUPPORTED_BANKS[$bank_id])) {
        throw new Exception('Banco no soportado');
    }

    // Get bank configuration
    $bank = $SUPPORTED_BANKS[$bank_id];

    // Verify required fields
    foreach ($bank['fields'] as $field) {
        if ($field['required'] && empty($_POST[$field['name']])) {
            throw new Exception("El campo {$field['label']} es requerido");
        }
    }

    // Prepare credentials
    $credentials = [];
    foreach ($bank['fields'] as $field) {
        $credentials[$field['name']] = $_POST[$field['name']] ?? '';
    }

    // Encrypt credentials
    $encrypted_credentials = encryptBankCredentials($credentials);

    // Store bank connection
    $stmt = $pdo->prepare("
        INSERT INTO bank_connections (
            user_id, 
            institution_id, 
            credentials, 
            status, 
            created_at
        ) VALUES (?, ?, ?, 'active', NOW())
    ");

    $stmt->execute([
        $_SESSION['user_id'],
        $bank_id,
        $encrypted_credentials
    ]);

    $connection_id = $pdo->lastInsertId();

    // Log successful connection
    error_log("Nueva conexión bancaria creada: ID={$connection_id}, Usuario={$_SESSION['user_id']}, Banco={$bank_id}");

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Conexión bancaria establecida correctamente',
        'connection_id' => $connection_id
    ]);

} catch (Exception $e) {
    error_log('Error en connect.php: ' . $e->getMessage());
    http_response_code($e->getMessage() === 'Sesión expirada. Por favor, inicie sesión nuevamente.' ? 401 : 400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

// Ensure all output is sent
ob_end_flush(); 
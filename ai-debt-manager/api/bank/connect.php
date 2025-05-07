<?php
// Prevent any output before headers
ob_start();

// Disable error display
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Set error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

// Set JSON response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With, X-CSRF-Token');
header('Access-Control-Allow-Credentials: true');

try {
    require_once __DIR__ . '/../../config/config.php';
    require_once __DIR__ . '/../../config/banks.php';
    require_once __DIR__ . '/../../includes/auth_functions.php';

    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

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

    // Verify database connection
    if (!checkDatabaseConnection()) {
        throw new Exception('Error de conexión a la base de datos');
    }

    // Encrypt credentials
    if (!function_exists('encryptBankCredentials')) {
        throw new Exception('Función de encriptación no disponible');
    }
    $encrypted_credentials = encryptBankCredentials($credentials);

    // Generate a unique belvo_link_id
    $belvo_link_id = uniqid('link_', true);

    // Store bank connection
    $stmt = $pdo->prepare("
        INSERT INTO bank_connections (
            user_id, 
            institution_id, 
            belvo_link_id,
            bank_credentials, 
            status
        ) VALUES (?, ?, ?, ?, 'active')
    ");

    if (!$stmt) {
        throw new Exception('Error al preparar la consulta SQL');
    }

    $result = $stmt->execute([
        $_SESSION['user_id'],
        $bank_id,
        $belvo_link_id,
        $encrypted_credentials
    ]);

    if (!$result) {
        throw new Exception('Error al guardar la conexión bancaria');
    }

    $connection_id = $pdo->lastInsertId();

    // Log successful connection
    error_log("Nueva conexión bancaria creada: ID={$connection_id}, Usuario={$_SESSION['user_id']}, Banco={$bank_id}");

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Conexión bancaria establecida correctamente',
        'connection_id' => $connection_id,
        'belvo_link_id' => $belvo_link_id
    ]);

} catch (ErrorException $e) {
    error_log('Error PHP en connect.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
} catch (PDOException $e) {
    error_log('Error de base de datos en connect.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al conectar con la base de datos'
    ]);
} catch (Exception $e) {
    error_log('Error en connect.php: ' . $e->getMessage());
    http_response_code($e->getMessage() === 'Sesión expirada. Por favor, inicie sesión nuevamente.' ? 401 : 400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
} catch (Throwable $e) {
    error_log('Error inesperado en connect.php: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error inesperado del servidor'
    ]);
}

// Ensure all output is sent
ob_end_flush(); 
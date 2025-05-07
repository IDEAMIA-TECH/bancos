<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/banks.php';
require_once __DIR__ . '/../../includes/auth_functions.php';

// Verificar autenticación
requireLogin();

// Verificar que se recibieron los datos necesarios
if (!isset($_POST['bank_id']) || !isset($SUPPORTED_BANKS[$_POST['bank_id']])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Banco no válido'
    ]);
    exit;
}

$bankId = $_POST['bank_id'];
$bank = $SUPPORTED_BANKS[$bankId];

// Verificar que se recibieron todos los campos requeridos
$missingFields = [];
foreach ($bank['fields'] as $field) {
    if (!isset($_POST[$field['name']]) || empty($_POST[$field['name']])) {
        $missingFields[] = $field['label'];
    }
}

if (!empty($missingFields)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => 'Faltan campos requeridos: ' . implode(', ', $missingFields)
    ]);
    exit;
}

try {
    // Preparar las credenciales para almacenamiento
    $credentials = [];
    foreach ($bank['fields'] as $field) {
        $credentials[$field['name']] = $_POST[$field['name']];
    }

    // Encriptar las credenciales
    $encryptedCredentials = encryptBankCredentials($credentials);

    // Guardar la conexión en la base de datos
    $stmt = $pdo->prepare("
        INSERT INTO bank_connections (
            user_id, 
            bank_id, 
            bank_name, 
            credentials, 
            created_at, 
            last_sync
        ) VALUES (?, ?, ?, ?, NOW(), NULL)
    ");

    $stmt->execute([
        $_SESSION['user_id'],
        $bankId,
        $bank['name'],
        $encryptedCredentials
    ]);

    $connectionId = $pdo->lastInsertId();

    // Iniciar el proceso de scraping en segundo plano
    $scraperScript = __DIR__ . '/../../scripts/scrapers/' . $bankId . '.php';
    if (file_exists($scraperScript)) {
        // Ejecutar el scraper en segundo plano
        $command = sprintf(
            'php %s %d > /dev/null 2>&1 &',
            escapeshellarg($scraperScript),
            $connectionId
        );
        exec($command);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Conexión iniciada correctamente'
    ]);

} catch (Exception $e) {
    error_log('Error al conectar cuenta bancaria: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al conectar la cuenta bancaria'
    ]);
} 
<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/banks.php';
require_once __DIR__ . '/../../includes/auth_functions.php';

// Set JSON response headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: ' . APP_URL);
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// Verificar autenticación
requireLogin();

try {
    // Obtener el ID del banco
    $bankId = $_GET['bank_id'] ?? '';

    // Verificar que el banco existe
    if (!isset($SUPPORTED_BANKS[$bankId])) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Banco no soportado'
        ]);
        exit;
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
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor'
    ]);
} 
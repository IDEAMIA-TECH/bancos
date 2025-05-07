<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/banks.php';
require_once __DIR__ . '/../../includes/auth_functions.php';

// Verificar autenticación
requireLogin();

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
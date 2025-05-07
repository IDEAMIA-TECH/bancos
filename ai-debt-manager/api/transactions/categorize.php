<?php
require_once __DIR__ . '/../../includes/auth_functions.php';
requireLogin();

// Set response type to JSON
header('Content-Type: application/json');

// Verify request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

// Validate input
if (!isset($data['transaction_id']) || !isset($data['category_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

try {
    // Verify transaction belongs to user
    $stmt = $pdo->prepare("
        SELECT t.id 
        FROM transactions t
        JOIN accounts a ON t.account_id = a.id
        JOIN bank_connections bc ON a.bank_connection_id = bc.id
        WHERE t.id = ? AND bc.user_id = ?
    ");
    $stmt->execute([$data['transaction_id'], $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Transacción no encontrada']);
        exit;
    }

    // Update transaction category
    $stmt = $pdo->prepare("
        UPDATE transactions 
        SET category_id = ? 
        WHERE id = ?
    ");
    $stmt->execute([$data['category_id'], $data['transaction_id']]);

    // Return success response
    echo json_encode([
        'success' => true,
        'message' => 'Categoría actualizada correctamente'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al actualizar la categoría: ' . $e->getMessage()
    ]);
} 
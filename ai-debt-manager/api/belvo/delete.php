<?php
require_once __DIR__ . '/../../../includes/auth_functions.php';
requireLogin();

header('Content-Type: application/json');

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Obtener y validar datos
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['connection_id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID de conexión no proporcionado']);
    exit;
}

try {
    // Verificar que la conexión pertenece al usuario
    $stmt = $pdo->prepare("
        SELECT belvo_link_id 
        FROM bank_connections 
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$data['connection_id'], $_SESSION['user_id']]);
    $connection = $stmt->fetch();

    if (!$connection) {
        throw new Exception('Conexión no encontrada o no autorizada');
    }

    // Iniciar transacción
    $pdo->beginTransaction();

    // Eliminar la conexión en Belvo
    $ch = curl_init(BELVO_LINKS_ENDPOINT . $connection['belvo_link_id']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, BELVO_SECRET_ID . ":" . BELVO_SECRET_PASSWORD);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 204) {
        // Eliminar la conexión de la base de datos
        // Las cuentas se eliminarán por CASCADE
        $stmt = $pdo->prepare("DELETE FROM bank_connections WHERE id = ?");
        $stmt->execute([$data['connection_id']]);

        // Confirmar transacción
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Conexión eliminada correctamente'
        ]);
    } else {
        throw new Exception('Error al eliminar la conexión en Belvo');
    }
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $pdo->rollBack();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al eliminar la conexión: ' . $e->getMessage()
    ]);
} 
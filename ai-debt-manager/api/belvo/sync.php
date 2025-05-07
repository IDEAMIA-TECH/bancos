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

    // Obtener cuentas actualizadas de Belvo
    $ch = curl_init(BELVO_ACCOUNTS_ENDPOINT);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, BELVO_SECRET_ID . ":" . BELVO_SECRET_PASSWORD);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['link' => $connection['belvo_link_id']]));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $accounts = json_decode($response, true);
        
        // Actualizar las cuentas existentes
        $stmt = $pdo->prepare("
            UPDATE accounts 
            SET balance = ?, last_updated = CURRENT_TIMESTAMP
            WHERE bank_connection_id = ? AND account_number = ?
        ");

        foreach ($accounts as $account) {
            $stmt->execute([
                $account['balance'],
                $data['connection_id'],
                $account['number']
            ]);
        }

        // Actualizar timestamp de última sincronización
        $stmt = $pdo->prepare("
            UPDATE bank_connections 
            SET last_sync = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->execute([$data['connection_id']]);

        // Confirmar transacción
        $pdo->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Cuentas sincronizadas correctamente'
        ]);
    } else {
        throw new Exception('Error al obtener datos de Belvo');
    }
} catch (Exception $e) {
    // Revertir transacción en caso de error
    $pdo->rollBack();
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al sincronizar: ' . $e->getMessage()
    ]);
} 
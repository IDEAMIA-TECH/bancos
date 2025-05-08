<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Verificar que se haya subido un archivo
if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Error al subir el archivo']);
    exit;
}

// Verificar campos requeridos
$required_fields = ['bank', 'account', 'date'];
foreach ($required_fields as $field) {
    if (empty($_POST[$field])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Faltan campos requeridos']);
        exit;
    }
}

// Validar archivo
$file = $_FILES['file'];
if ($file['type'] !== 'application/pdf') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Solo se permiten archivos PDF']);
    exit;
}

// Crear directorio de uploads si no existe
$upload_dir = __DIR__ . '/../../uploads/statements/' . $_SESSION['user_id'];
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Generar nombre único para el archivo
$file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$file_name = uniqid() . '_' . time() . '.' . $file_extension;
$file_path = $upload_dir . '/' . $file_name;

// Mover archivo
if (!move_uploaded_file($file['tmp_name'], $file_path)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al guardar el archivo']);
    exit;
}

try {
    // Guardar registro en la base de datos
    $stmt = $pdo->prepare("
        INSERT INTO bank_statements (
            user_id, 
            bank_name, 
            account_number, 
            statement_date, 
            file_path, 
            status
        ) VALUES (?, ?, ?, ?, ?, 'pending')
    ");

    $stmt->execute([
        $_SESSION['user_id'],
        $_POST['bank'],
        $_POST['account'],
        $_POST['date'],
        'uploads/statements/' . $_SESSION['user_id'] . '/' . $file_name
    ]);

    // Responder con éxito
    echo json_encode([
        'success' => true,
        'message' => 'Estado de cuenta subido correctamente',
        'statement_id' => $pdo->lastInsertId()
    ]);

} catch (Exception $e) {
    // Eliminar archivo si hay error en la base de datos
    unlink($file_path);
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al procesar el estado de cuenta'
    ]);
    
    // Log del error
    error_log("Error al procesar estado de cuenta: " . $e->getMessage());
} 
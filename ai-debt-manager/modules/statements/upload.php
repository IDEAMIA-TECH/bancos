<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth_functions.php';

// Verificar si el usuario está logueado
if (!isLoggedIn()) {
    header('Location: ' . APP_URL . '/login');
    exit;
}

// Procesar el formulario de subida
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validar archivo
        if (!isset($_FILES['statement']) || $_FILES['statement']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Error al subir el archivo');
        }

        // Validar tipo de archivo
        $file_type = mime_content_type($_FILES['statement']['tmp_name']);
        if ($file_type !== 'application/pdf') {
            throw new Exception('Solo se permiten archivos PDF');
        }

        // Crear directorio si no existe
        $upload_dir = __DIR__ . '/../../uploads/statements/' . $_SESSION['user_id'];
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        // Generar nombre único para el archivo
        $file_name = uniqid() . '_' . basename($_FILES['statement']['name']);
        $file_path = $upload_dir . '/' . $file_name;

        // Mover el archivo
        if (!move_uploaded_file($_FILES['statement']['tmp_name'], $file_path)) {
            throw new Exception('Error al guardar el archivo');
        }

        // Guardar en la base de datos
        $stmt = $pdo->prepare("
            INSERT INTO bank_statements (
                user_id, 
                bank_name, 
                account_number, 
                statement_date, 
                file_path
            ) VALUES (?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $_SESSION['user_id'],
            $_POST['bank_name'],
            $_POST['account_number'],
            $_POST['statement_date'],
            $file_path
        ]);

        $success = 'Estado de cuenta subido correctamente. Se procesará en breve.';

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subir Estado de Cuenta - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h2 class="h4 mb-0">Subir Estado de Cuenta</h2>
                    </div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <?php if (isset($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="bank_name" class="form-label">Banco</label>
                                <select class="form-select" id="bank_name" name="bank_name" required>
                                    <option value="">Seleccione un banco</option>
                                    <option value="Banamex">Banamex</option>
                                    <option value="Banorte">Banorte</option>
                                    <option value="Santander">Santander</option>
                                    <option value="BBVA">BBVA</option>
                                    <option value="HSBC">HSBC</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="account_number" class="form-label">Número de Cuenta</label>
                                <input type="text" class="form-control" id="account_number" name="account_number" required>
                            </div>

                            <div class="mb-3">
                                <label for="statement_date" class="form-label">Fecha del Estado de Cuenta</label>
                                <input type="date" class="form-control" id="statement_date" name="statement_date" required>
                            </div>

                            <div class="mb-3">
                                <label for="statement" class="form-label">Archivo PDF</label>
                                <input type="file" class="form-control" id="statement" name="statement" accept=".pdf" required>
                                <div class="form-text">Solo se permiten archivos PDF</div>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload me-2"></i>Subir Estado de Cuenta
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Lista de estados de cuenta subidos -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h3 class="h5 mb-0">Estados de Cuenta Subidos</h3>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Banco</th>
                                        <th>Cuenta</th>
                                        <th>Fecha</th>
                                        <th>Estado</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $stmt = $pdo->prepare("
                                        SELECT * FROM bank_statements 
                                        WHERE user_id = ? 
                                        ORDER BY created_at DESC
                                    ");
                                    $stmt->execute([$_SESSION['user_id']]);
                                    while ($statement = $stmt->fetch()): 
                                    ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($statement['bank_name']); ?></td>
                                        <td><?php echo htmlspecialchars($statement['account_number']); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($statement['statement_date'])); ?></td>
                                        <td>
                                            <?php
                                            $status_class = [
                                                'pending' => 'warning',
                                                'processed' => 'success',
                                                'error' => 'danger'
                                            ][$statement['status']];
                                            ?>
                                            <span class="badge bg-<?php echo $status_class; ?>">
                                                <?php echo ucfirst($statement['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="<?php echo APP_URL; ?>/statements/view/<?php echo $statement['id']; ?>" 
                                               class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 
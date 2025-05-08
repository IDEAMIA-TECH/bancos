<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../includes/auth.php';

// Verificar autenticación
if (!isLoggedIn()) {
    header('Location: /login');
    exit;
}

$user_id = $_SESSION['user_id'];

// Obtener estados de cuenta del usuario
$stmt = $pdo->prepare("
    SELECT * FROM bank_statements 
    WHERE user_id = ? 
    ORDER BY statement_date DESC
");
$stmt->execute([$user_id]);
$statements = $stmt->fetchAll();

// Obtener bancos disponibles
$banks = [
    'BBVA' => 'BBVA',
    'Santander' => 'Santander',
    'Banamex' => 'Banamex',
    'Banorte' => 'Banorte',
    'HSBC' => 'HSBC',
    'Banco Azteca' => 'Banco Azteca',
    'Banco del Bajío' => 'Banco del Bajío',
    'Banregio' => 'Banregio',
    'Inbursa' => 'Inbursa',
    'Otro' => 'Otro'
];
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subir Estado de Cuenta - AI Debt Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        .upload-area {
            border: 2px dashed #ccc;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .upload-area:hover {
            border-color: #0d6efd;
            background-color: #f8f9fa;
        }
        .upload-area.dragover {
            border-color: #0d6efd;
            background-color: #e9ecef;
        }
        .statement-card {
            transition: all 0.3s ease;
        }
        .statement-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <div class="container py-5">
        <div class="row mb-4">
            <div class="col">
                <h1 class="h3 mb-0">Estados de Cuenta</h1>
                <p class="text-muted">Sube tus estados de cuenta en PDF para analizar tus transacciones</p>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Subir Nuevo Estado de Cuenta</h5>
                        <form id="uploadForm" class="mt-3">
                            <div class="mb-3">
                                <label for="bank" class="form-label">Banco</label>
                                <select class="form-select" id="bank" name="bank" required>
                                    <option value="">Selecciona un banco</option>
                                    <?php foreach ($banks as $code => $name): ?>
                                        <option value="<?php echo htmlspecialchars($code); ?>">
                                            <?php echo htmlspecialchars($name); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label for="account" class="form-label">Número de Cuenta</label>
                                <input type="text" class="form-control" id="account" name="account" required>
                            </div>

                            <div class="mb-3">
                                <label for="date" class="form-label">Fecha del Estado de Cuenta</label>
                                <input type="date" class="form-control" id="date" name="date" required>
                            </div>

                            <div class="upload-area" id="dropZone">
                                <i class="bi bi-cloud-upload fs-1"></i>
                                <p class="mt-2">Arrastra tu archivo PDF aquí o haz clic para seleccionar</p>
                                <input type="file" id="file" name="file" accept=".pdf" class="d-none" required>
                                <div id="fileInfo" class="mt-2"></div>
                            </div>

                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary" id="uploadButton" disabled>
                                    <i class="bi bi-upload me-2"></i>Subir Estado de Cuenta
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Estados de Cuenta Subidos</h5>
                        <?php if (empty($statements)): ?>
                            <p class="text-muted">No hay estados de cuenta subidos</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($statements as $statement): ?>
                                    <div class="list-group-item statement-card">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <h6 class="mb-1"><?php echo htmlspecialchars($statement['bank_name']); ?></h6>
                                                <p class="mb-1 text-muted small">
                                                    Cuenta: <?php echo htmlspecialchars($statement['account_number']); ?><br>
                                                    Fecha: <?php echo date('d/m/Y', strtotime($statement['statement_date'])); ?>
                                                </p>
                                            </div>
                                            <div>
                                                <?php if ($statement['status'] === 'pending'): ?>
                                                    <span class="badge bg-warning">Pendiente</span>
                                                <?php elseif ($statement['status'] === 'processed'): ?>
                                                    <span class="badge bg-success">Procesado</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Error</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const dropZone = document.getElementById('dropZone');
            const fileInput = document.getElementById('file');
            const fileInfo = document.getElementById('fileInfo');
            const uploadButton = document.getElementById('uploadButton');
            const uploadForm = document.getElementById('uploadForm');

            // Manejar clic en la zona de drop
            dropZone.addEventListener('click', () => fileInput.click());

            // Manejar drag & drop
            dropZone.addEventListener('dragover', (e) => {
                e.preventDefault();
                dropZone.classList.add('dragover');
            });

            dropZone.addEventListener('dragleave', () => {
                dropZone.classList.remove('dragover');
            });

            dropZone.addEventListener('drop', (e) => {
                e.preventDefault();
                dropZone.classList.remove('dragover');
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    handleFile(files[0]);
                }
            });

            // Manejar selección de archivo
            fileInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    handleFile(e.target.files[0]);
                }
            });

            function handleFile(file) {
                if (file.type !== 'application/pdf') {
                    alert('Por favor, selecciona un archivo PDF');
                    return;
                }

                fileInfo.innerHTML = `
                    <div class="alert alert-info">
                        <i class="bi bi-file-pdf me-2"></i>
                        ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)
                    </div>
                `;
                uploadButton.disabled = false;
            }

            // Manejar envío del formulario
            uploadForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                
                const formData = new FormData(uploadForm);
                uploadButton.disabled = true;
                uploadButton.innerHTML = '<i class="bi bi-arrow-repeat spin me-2"></i>Subiendo...';

                try {
                    const response = await fetch('/api/bank/upload.php', {
                        method: 'POST',
                        body: formData
                    });

                    const data = await response.json();

                    if (data.success) {
                        alert('Estado de cuenta subido correctamente');
                        window.location.reload();
                    } else {
                        throw new Error(data.message || 'Error al subir el archivo');
                    }
                } catch (error) {
                    alert(error.message);
                    uploadButton.disabled = false;
                    uploadButton.innerHTML = '<i class="bi bi-upload me-2"></i>Subir Estado de Cuenta';
                }
            });
        });
    </script>
</body>
</html> 
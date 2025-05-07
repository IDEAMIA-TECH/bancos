<?php
require_once __DIR__ . '/../../includes/auth_functions.php';
require_once __DIR__ . '/../../config/banks.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verificar autenticación
if (!isLoggedIn()) {
    header('Location: ' . APP_URL . '/login');
    exit;
}

// Habilitar visualización de errores
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Función para mostrar errores en la página
function displayError($message, $type = 'danger') {
    return "<div class='alert alert-{$type} mb-3'>
                <i class='fas fa-exclamation-circle'></i> 
                {$message}
            </div>";
}

// Verificar conexiones existentes
try {
    $stmt = $pdo->prepare("
        SELECT bc.*, COUNT(a.id) as account_count 
        FROM bank_connections bc 
        LEFT JOIN accounts a ON bc.id = a.bank_connection_id 
        WHERE bc.user_id = ? 
        GROUP BY bc.id
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $existingConnections = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log('Error al obtener conexiones: ' . $e->getMessage());
    $existingConnections = [];
    $errors[] = 'Error al obtener las conexiones bancarias: ' . $e->getMessage();
}

// Add CSRF token for security
$csrf_token = bin2hex(random_bytes(32));
$_SESSION['csrf_token'] = $csrf_token;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conexión Bancaria - <?php echo APP_NAME; ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" 
          integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" 
          crossorigin="anonymous" 
          referrerpolicy="no-referrer" />
    <style>
        .bank-card {
            transition: transform 0.2s;
        }
        .bank-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .card {
            border: 1px solid rgba(0,0,0,0.1);
        }
        .card-body {
            padding: 1.5rem;
        }
        .btn {
            padding: 0.5rem 1rem;
        }
        .btn-sm {
            padding: 0.25rem 0.5rem;
        }
    </style>
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <main class="flex-grow-1">
        <div class="container py-4">
            <h1 class="mb-4">Conexión Bancaria</h1>
            
            <?php if (!empty($errors)): ?>
                <div class="mb-4">
                    <?php foreach ($errors as $error): ?>
                        <?php echo displayError($error); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <div class="alert alert-info mb-4">
                <h4 class="alert-heading"><i class="fas fa-info-circle"></i> Importante</h4>
                <p>Para conectar tu cuenta bancaria, necesitaremos tus credenciales de acceso. Estas credenciales:</p>
                <ul>
                    <li>Se almacenan de forma segura y encriptada</li>
                    <li>Solo se utilizan para leer tus movimientos y saldos</li>
                    <li>No permiten realizar transferencias ni modificar tu cuenta</li>
                    <li>Se eliminan automáticamente después de <?php echo BANK_DATA_RETENTION_DAYS; ?> días de inactividad</li>
                </ul>
            </div>
            
            <?php if (empty($existingConnections)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Conecta tus cuentas bancarias para comenzar a gestionar tus deudas de manera inteligente.
                </div>
            <?php else: ?>
                <div class="mb-4">
                    <h2>Conexiones Activas</h2>
                    <div class="row">
                        <?php foreach ($existingConnections as $connection): ?>
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title">
                                            <i class="fas fa-university"></i> 
                                            <?php echo htmlspecialchars($connection['bank_name']); ?>
                                        </h5>
                                        <p class="card-text">
                                            <i class="fas fa-credit-card"></i> 
                                            Cuentas conectadas: <?php echo $connection['account_count']; ?>
                                        </p>
                                        <p class="card-text">
                                            <i class="fas fa-sync"></i> 
                                            Última sincronización: 
                                            <?php echo $connection['last_sync'] ? date('d/m/Y H:i', strtotime($connection['last_sync'])) : 'Nunca'; ?>
                                        </p>
                                        <div class="d-flex gap-2">
                                            <button onclick="syncConnection('<?php echo $connection['id']; ?>')" 
                                                    class="btn btn-primary btn-sm">
                                                <i class="fas fa-sync"></i> Sincronizar
                                            </button>
                                            <button onclick="deleteConnection('<?php echo $connection['id']; ?>')" 
                                                    class="btn btn-danger btn-sm">
                                                <i class="fas fa-trash"></i> Eliminar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="mt-4">
                <h2>Conectar Nueva Cuenta</h2>
                <div class="row">
                    <?php foreach ($SUPPORTED_BANKS as $bankId => $bank): ?>
                        <div class="col-md-4 col-lg-3 mb-3">
                            <div class="card h-100 bank-card">
                                <div class="card-body text-center">
                                    <h5 class="card-title"><?php echo htmlspecialchars($bank['name']); ?></h5>
                                    <button type="button" 
                                            class="btn btn-primary mt-3"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#connectModal"
                                            data-bank-id="<?php echo $bankId; ?>"
                                            data-bank-name="<?php echo htmlspecialchars($bank['name']); ?>">
                                        <i class="fas fa-link"></i> Conectar
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Modal de Conexión -->
    <div class="modal fade" id="connectModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Conectar Cuenta Bancaria</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="connectForm">
                        <input type="hidden" id="bankId" name="bank_id">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <div id="formFields"></div>
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" id="termsCheck" required>
                            <label class="form-check-label" for="termsCheck">
                                Acepto que mis credenciales se almacenarán de forma segura y solo se utilizarán para leer mis movimientos y saldos.
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="connectBank()">Conectar</button>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Cargar campos del formulario cuando se abre el modal
        document.getElementById('connectModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const bankId = button.getAttribute('data-bank-id');
            const bankName = button.getAttribute('data-bank-name');
            
            document.getElementById('bankId').value = bankId;
            document.querySelector('.modal-title').textContent = `Conectar ${bankName}`;
            
            // Cargar campos del formulario
            fetch(`<?php echo APP_URL; ?>/api/bank/fields.php?bank_id=${bankId}`, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': '<?php echo $csrf_token; ?>'
                },
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    if (response.status === 401) {
                        window.location.href = '<?php echo APP_URL; ?>/login';
                        throw new Error('Sesión expirada');
                    }
                    return response.json().then(data => {
                        throw new Error(data.message || 'Error al cargar los campos');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (!data.success) {
                    throw new Error(data.message || 'Error al cargar los campos');
                }
                
                if (!Array.isArray(data.fields)) {
                    throw new Error('Formato de campos inválido');
                }

                const formFields = document.getElementById('formFields');
                formFields.innerHTML = '';
                
                data.fields.forEach(field => {
                    if (!field.name || !field.label || !field.type) {
                        console.warn('Campo inválido:', field);
                        return;
                    }

                    const div = document.createElement('div');
                    div.className = 'mb-3';
                    div.innerHTML = `
                        <label class="form-label">${field.label}</label>
                        <input type="${field.type}" 
                               class="form-control" 
                               name="${field.name}"
                               placeholder="${field.placeholder || ''}"
                               ${field.required ? 'required' : ''}>
                    `;
                    formFields.appendChild(div);
                });
            })
            .catch(error => {
                console.error('Error:', error);
                const formFields = document.getElementById('formFields');
                formFields.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        ${error.message}
                    </div>
                `;
            });
        });

        function connectBank() {
            if (!document.getElementById('termsCheck').checked) {
                alert('Debes aceptar los términos para continuar');
                return;
            }

            const form = document.getElementById('connectForm');
            const formData = new FormData(form);
            
            fetch('<?php echo APP_URL; ?>/api/bank/connect.php', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
                headers: {
                    'X-CSRF-Token': '<?php echo $csrf_token; ?>'
                }
            })
            .then(response => {
                if (!response.ok) {
                    if (response.status === 401) {
                        window.location.href = '<?php echo APP_URL; ?>/login';
                        throw new Error('Sesión expirada');
                    }
                    return response.json().then(data => {
                        throw new Error(data.message || 'Error al conectar la cuenta');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    throw new Error(data.message || 'Error al conectar la cuenta');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al conectar la cuenta: ' + error.message);
            });
        }

        function syncConnection(connectionId) {
            fetch('<?php echo APP_URL; ?>/api/bank/sync.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-Token': '<?php echo $csrf_token; ?>'
                },
                body: JSON.stringify({
                    connection_id: connectionId
                }),
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok) {
                    if (response.status === 401) {
                        window.location.href = '<?php echo APP_URL; ?>/login';
                        throw new Error('Sesión expirada');
                    }
                    return response.json().then(data => {
                        throw new Error(data.message || 'Error al sincronizar');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    throw new Error(data.message || 'Error al sincronizar');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al sincronizar: ' + error.message);
            });
        }

        function deleteConnection(connectionId) {
            if (confirm('¿Estás seguro de que deseas eliminar esta conexión?')) {
                fetch('<?php echo APP_URL; ?>/api/bank/delete.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-Token': '<?php echo $csrf_token; ?>'
                    },
                    body: JSON.stringify({
                        connection_id: connectionId
                    }),
                    credentials: 'same-origin'
                })
                .then(response => {
                    if (!response.ok) {
                        if (response.status === 401) {
                            window.location.href = '<?php echo APP_URL; ?>/login';
                            throw new Error('Sesión expirada');
                        }
                        return response.json().then(data => {
                            throw new Error(data.message || 'Error al eliminar la conexión');
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        throw new Error(data.message || 'Error al eliminar la conexión');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al eliminar la conexión: ' + error.message);
                });
            }
        }
    </script>
</body>
</html> 
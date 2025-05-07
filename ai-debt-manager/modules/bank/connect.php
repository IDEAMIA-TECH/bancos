<?php
require_once __DIR__ . '/../../includes/auth_functions.php';
require_once __DIR__ . '/../../config/belvo.php';
require_once __DIR__ . '/../../config/config.php';
requireLogin();

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

// Obtener instituciones disponibles de Belvo
function getBelvoInstitutions() {
    global $pdo;
    $errors = [];
    
    try {
        // Verificar si la función belvoApiRequest existe
        if (!function_exists('belvoApiRequest')) {
            throw new Exception('La función belvoApiRequest no está definida. Verifique que el archivo belvo.php está incluido correctamente.');
        }

        // Verificar credenciales de Belvo
        if (!defined('BELVO_API_KEY') || !defined('BELVO_API_SECRET')) {
            throw new Exception('Las credenciales de Belvo no están configuradas. Verifique el archivo config/belvo.php');
        }

        error_log('Iniciando solicitud a Belvo API...');
        error_log('Credenciales Belvo - Key: ' . substr(BELVO_API_KEY, 0, 5) . '...');
        
        $response = belvoApiRequest('/api/institutions/');
        error_log('Respuesta de Belvo API: ' . print_r($response, true));
        
        if (!is_array($response)) {
            throw new Exception('La respuesta de Belvo no es un array. Tipo recibido: ' . gettype($response));
        }
        
        if (empty($response['results'])) {
            throw new Exception('La respuesta de Belvo está vacía o no contiene instituciones');
        }
        
        return $response['results'];
    } catch (Exception $e) {
        error_log('Error al obtener instituciones de Belvo: ' . $e->getMessage());
        error_log('Stack trace: ' . $e->getTraceAsString());
        $errors[] = $e->getMessage();
        return [];
    }
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

// Obtener instituciones disponibles
$institutions = getBelvoInstitutions();
error_log('Instituciones obtenidas: ' . print_r($institutions, true));

// Activar modo debug para esta sesión
$_SESSION['debug'] = true;
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
    <!-- Belvo Widget -->
    <script src="https://cdn.belvo.io/belvo-widget-1-stable.js"></script>
    <style>
        .institution-card {
            transition: transform 0.2s;
        }
        .institution-card:hover {
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
        .debug-info {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            padding: 1rem;
            margin-bottom: 1rem;
            font-family: monospace;
            font-size: 0.875rem;
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

            <?php if (isset($_SESSION['debug']) && $_SESSION['debug']): ?>
                <div class="debug-info mb-4">
                    <h5>Información de Depuración:</h5>
                    <ul>
                        <li>Último error PHP: <?php echo error_get_last()['message'] ?? 'Ninguno'; ?></li>
                        <li>Función belvoApiRequest existe: <?php echo function_exists('belvoApiRequest') ? 'Sí' : 'No'; ?></li>
                        <li>Credenciales Belvo configuradas: <?php echo (defined('BELVO_API_KEY') && defined('BELVO_API_SECRET')) ? 'Sí' : 'No'; ?></li>
                        <li>Número de instituciones obtenidas: <?php echo count($institutions); ?></li>
                        <li>Contenido de la respuesta de Belvo:
                            <pre><?php echo htmlspecialchars(print_r($institutions, true)); ?></pre>
                        </li>
                        <li>Archivos incluidos:
                            <ul>
                                <?php foreach (get_included_files() as $file): ?>
                                    <li><?php echo $file; ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </li>
                    </ul>
                </div>
            <?php endif; ?>
            
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
                                            <?php echo htmlspecialchars($connection['institution_id']); ?>
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
                <?php if (empty($institutions)): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> 
                        No se pudieron cargar las instituciones bancarias. Por favor, intente más tarde.
                        <?php if (isset($_SESSION['debug']) && $_SESSION['debug']): ?>
                            <br>
                            <small class="text-muted">
                                Error: <?php echo error_get_last()['message'] ?? 'No hay error específico'; ?>
                            </small>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($institutions as $institution): ?>
                            <?php if (isset($institution['id']) && isset($institution['display_name']) && isset($institution['logo'])): ?>
                                <div class="col-md-4 col-lg-3 mb-3">
                                    <div class="card h-100 institution-card" 
                                         onclick="connectBank('<?php echo htmlspecialchars($institution['id']); ?>')"
                                         style="cursor: pointer;">
                                        <div class="card-body text-center">
                                            <img src="<?php echo htmlspecialchars($institution['logo']); ?>" 
                                                 alt="<?php echo htmlspecialchars($institution['display_name']); ?>"
                                                 class="img-fluid mb-3"
                                                 style="max-height: 50px;">
                                            <h5 class="card-title"><?php echo htmlspecialchars($institution['display_name']); ?></h5>
                                            <?php if (isset($institution['type'])): ?>
                                                <span class="badge bg-info"><?php echo htmlspecialchars($institution['type']); ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function connectBank(institutionId) {
            belvoSDK.createWidget({
                institution: institutionId,
                callback: function(link) {
                    // Guardar la conexión en la base de datos
                    fetch('<?php echo APP_URL; ?>/api/belvo/connect.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            institution_id: institutionId,
                            belvo_link_id: link
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            window.location.reload();
                        } else {
                            alert('Error al conectar la cuenta: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error al conectar la cuenta. Por favor, intente más tarde.');
                    });
                }
            });
        }

        function syncConnection(connectionId) {
            fetch('<?php echo APP_URL; ?>/api/belvo/sync.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    connection_id: connectionId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Error al sincronizar: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al sincronizar. Por favor, intente más tarde.');
            });
        }

        function deleteConnection(connectionId) {
            if (confirm('¿Estás seguro de que deseas eliminar esta conexión?')) {
                fetch('<?php echo APP_URL; ?>/api/belvo/delete.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        connection_id: connectionId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        window.location.reload();
                    } else {
                        alert('Error al eliminar la conexión: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error al eliminar la conexión. Por favor, intente más tarde.');
                });
            }
        }
    </script>
</body>
</html> 
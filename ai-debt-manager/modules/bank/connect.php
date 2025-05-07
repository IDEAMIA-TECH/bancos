<?php
require_once __DIR__ . '/../../includes/auth_functions.php';
requireLogin();

// Obtener instituciones disponibles de Belvo
function getBelvoInstitutions() {
    try {
        $response = belvoApiRequest('/api/institutions/');
        if (!is_array($response)) {
            error_log('Error en respuesta de Belvo: ' . print_r($response, true));
            return [];
        }
        return $response;
    } catch (Exception $e) {
        error_log('Error al obtener instituciones de Belvo: ' . $e->getMessage());
        return [];
    }
}

// Verificar conexiones existentes
$stmt = $pdo->prepare("
    SELECT bc.*, COUNT(a.id) as account_count 
    FROM bank_connections bc 
    LEFT JOIN accounts a ON bc.id = a.bank_connection_id 
    WHERE bc.user_id = ? 
    GROUP BY bc.id
");
$stmt->execute([$_SESSION['user_id']]);
$existingConnections = $stmt->fetchAll();

// Obtener instituciones disponibles
$institutions = getBelvoInstitutions();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conexión Bancaria - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" 
          integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" 
          crossorigin="anonymous" 
          referrerpolicy="no-referrer" />
    <script src="https://cdn.belvo.io/belvo-widget-1-stable.js"></script>
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <main class="flex-grow-1">
        <div class="container py-4">
            <h1 class="mb-4">Conexión Bancaria</h1>
            
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
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($institutions as $institution): ?>
                            <?php if (isset($institution['id']) && isset($institution['name']) && isset($institution['logo_url'])): ?>
                                <div class="col-md-4 col-lg-3 mb-3">
                                    <div class="card h-100 institution-card" 
                                         onclick="connectBank('<?php echo htmlspecialchars($institution['id']); ?>')"
                                         style="cursor: pointer;">
                                        <div class="card-body text-center">
                                            <img src="<?php echo htmlspecialchars($institution['logo_url']); ?>" 
                                                 alt="<?php echo htmlspecialchars($institution['name']); ?>"
                                                 class="img-fluid mb-3"
                                                 style="max-height: 50px;">
                                            <h5 class="card-title"><?php echo htmlspecialchars($institution['name']); ?></h5>
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
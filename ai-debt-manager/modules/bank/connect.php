<?php
require_once __DIR__ . '/../../includes/auth_functions.php';
requireLogin();

// Obtener instituciones disponibles de Belvo
function getBelvoInstitutions() {
    $ch = curl_init(BELVO_API_URL . 'institutions/');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, BELVO_SECRET_ID . ":" . BELVO_SECRET_PASSWORD);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return json_decode($response, true);
    }
    return [];
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
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/style.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/dashboard.css">
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>
    
    <div class="container">
        <h1>Conexión Bancaria</h1>
        
        <?php if (empty($existingConnections)): ?>
            <div class="info-box">
                <p>Conecta tus cuentas bancarias para comenzar a gestionar tus deudas de manera inteligente.</p>
            </div>
        <?php else: ?>
            <div class="connections-list">
                <h2>Conexiones Activas</h2>
                <?php foreach ($existingConnections as $connection): ?>
                    <div class="connection-card">
                        <div class="connection-info">
                            <h3><?php echo htmlspecialchars($connection['institution_id']); ?></h3>
                            <p>Cuentas conectadas: <?php echo $connection['account_count']; ?></p>
                            <p>Última sincronización: <?php echo $connection['last_sync'] ? date('d/m/Y H:i', strtotime($connection['last_sync'])) : 'Nunca'; ?></p>
                        </div>
                        <div class="connection-actions">
                            <button onclick="syncConnection('<?php echo $connection['id']; ?>')" class="btn-secondary">Sincronizar</button>
                            <button onclick="deleteConnection('<?php echo $connection['id']; ?>')" class="btn-danger">Eliminar</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <div class="connect-new">
            <h2>Conectar Nueva Cuenta</h2>
            <div class="institutions-grid">
                <?php foreach ($institutions as $institution): ?>
                    <div class="institution-card" onclick="connectBank('<?php echo $institution['id']; ?>')">
                        <img src="<?php echo $institution['logo_url']; ?>" alt="<?php echo $institution['name']; ?>">
                        <h3><?php echo $institution['name']; ?></h3>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script src="<?php echo APP_URL; ?>/assets/js/belvo.js"></script>
    <script>
        function connectBank(institutionId) {
            // Inicializar el widget de Belvo
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
                });
            }
        }
    </script>
</body>
</html> 
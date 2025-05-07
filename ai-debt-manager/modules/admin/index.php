<?php
require_once __DIR__ . '/../../includes/auth_functions.php';
requireLogin();
requireAdmin(); // Nueva función para verificar si el usuario es administrador

// Get system statistics
$stmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM users WHERE status = 'active') as active_users,
        (SELECT COUNT(*) FROM bank_connections WHERE status = 'active') as active_connections,
        (SELECT COUNT(*) FROM debts WHERE status = 'active') as active_debts
");
$stmt->execute();
$stats = $stmt->fetch();

// Get recent users
$stmt = $pdo->prepare("
    SELECT u.*, 
           (SELECT COUNT(*) FROM bank_connections bc WHERE bc.user_id = u.id) as connection_count,
           (SELECT COUNT(*) FROM debts d WHERE d.user_id = u.id) as debt_count
    FROM users u
    ORDER BY u.created_at DESC
    LIMIT 10
");
$stmt->execute();
$recent_users = $stmt->fetchAll();

// Get recent bank connections
$stmt = $pdo->prepare("
    SELECT bc.*, u.email, u.full_name
    FROM bank_connections bc
    JOIN users u ON bc.user_id = u.id
    ORDER BY bc.created_at DESC
    LIMIT 10
");
$stmt->execute();
$recent_connections = $stmt->fetchAll();

// Get system alerts
$stmt = $pdo->prepare("
    SELECT n.*, u.email
    FROM notifications n
    JOIN users u ON n.user_id = u.id
    WHERE n.type = 'system'
    ORDER BY n.created_at DESC
    LIMIT 10
");
$stmt->execute();
$system_alerts = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Administración - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <div class="container">
        <div class="admin-header">
            <h1>Panel de Administración</h1>
            <p>Gestiona usuarios, monitorea conexiones y configura el sistema</p>
        </div>

        <div class="admin-summary">
            <div class="summary-card">
                <h3>Usuarios Totales</h3>
                <p class="amount"><?php echo number_format($stats['total_users']); ?></p>
                <p class="subtitle"><?php echo number_format($stats['active_users']); ?> activos</p>
            </div>
            <div class="summary-card">
                <h3>Conexiones Activas</h3>
                <p class="amount"><?php echo number_format($stats['active_connections']); ?></p>
                <p class="subtitle">Conexiones bancarias</p>
            </div>
            <div class="summary-card">
                <h3>Deudas Activas</h3>
                <p class="amount"><?php echo number_format($stats['active_debts']); ?></p>
                <p class="subtitle">En seguimiento</p>
            </div>
        </div>

        <div class="admin-grid">
            <div class="admin-card">
                <div class="card-header">
                    <h3>Usuarios Recientes</h3>
                    <a href="users.php" class="btn-link">Ver todos</a>
                </div>
                <div class="users-list">
                    <?php foreach ($recent_users as $user): ?>
                        <div class="user-item">
                            <div class="user-info">
                                <h4><?php echo htmlspecialchars($user['full_name']); ?></h4>
                                <p class="email"><?php echo htmlspecialchars($user['email']); ?></p>
                            </div>
                            <div class="user-stats">
                                <span class="stat">
                                    <i class="fas fa-link"></i>
                                    <?php echo $user['connection_count']; ?> conexiones
                                </span>
                                <span class="stat">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <?php echo $user['debt_count']; ?> deudas
                                </span>
                            </div>
                            <div class="user-status <?php echo $user['status']; ?>">
                                <?php echo ucfirst($user['status']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="admin-card">
                <div class="card-header">
                    <h3>Conexiones Recientes</h3>
                    <a href="connections.php" class="btn-link">Ver todas</a>
                </div>
                <div class="connections-list">
                    <?php foreach ($recent_connections as $connection): ?>
                        <div class="connection-item">
                            <div class="connection-info">
                                <h4><?php echo htmlspecialchars($connection['institution_id']); ?></h4>
                                <p class="user"><?php echo htmlspecialchars($connection['full_name']); ?></p>
                            </div>
                            <div class="connection-status <?php echo $connection['status']; ?>">
                                <?php echo ucfirst($connection['status']); ?>
                            </div>
                            <div class="connection-date">
                                <?php echo date('d/m/Y', strtotime($connection['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="admin-card">
                <div class="card-header">
                    <h3>Alertas del Sistema</h3>
                    <a href="alerts.php" class="btn-link">Ver todas</a>
                </div>
                <div class="alerts-list">
                    <?php foreach ($system_alerts as $alert): ?>
                        <div class="alert-item">
                            <div class="alert-info">
                                <h4><?php echo htmlspecialchars($alert['message']); ?></h4>
                                <p class="user"><?php echo htmlspecialchars($alert['email']); ?></p>
                            </div>
                            <div class="alert-date">
                                <?php echo date('d/m/Y H:i', strtotime($alert['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="admin-card">
                <div class="card-header">
                    <h3>Configuración del Sistema</h3>
                </div>
                <div class="settings-list">
                    <a href="settings.php?section=general" class="settings-item">
                        <i class="fas fa-cog"></i>
                        Configuración General
                    </a>
                    <a href="settings.php?section=security" class="settings-item">
                        <i class="fas fa-shield-alt"></i>
                        Seguridad
                    </a>
                    <a href="settings.php?section=notifications" class="settings-item">
                        <i class="fas fa-bell"></i>
                        Notificaciones
                    </a>
                    <a href="settings.php?section=ai" class="settings-item">
                        <i class="fas fa-robot"></i>
                        Configuración IA
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Aquí se pueden agregar gráficos o funcionalidades adicionales
    </script>
</body>
</html> 
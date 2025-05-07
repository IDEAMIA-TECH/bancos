<?php
require_once __DIR__ . '/../../includes/auth_functions.php';
requireLogin();
requireAdmin();

// Check if alert ID is provided
if (!isset($_GET['id'])) {
    header('Location: alerts.php');
    exit;
}

$alert_id = $_GET['id'];

// Get alert details
$stmt = $pdo->prepare("
    SELECT n.*, u.email, u.full_name
    FROM notifications n
    JOIN users u ON n.user_id = u.id
    WHERE n.id = ? AND n.type = 'system'
");
$stmt->execute([$alert_id]);
$alert = $stmt->fetch();

if (!$alert) {
    header('Location: alerts.php');
    exit;
}

// Get related transactions if any
$stmt = $pdo->prepare("
    SELECT t.*, c.name as category_name
    FROM transactions t
    LEFT JOIN categories c ON t.category_id = c.id
    WHERE t.id IN (
        SELECT transaction_id 
        FROM notification_transactions 
        WHERE notification_id = ?
    )
    ORDER BY t.date DESC
");
$stmt->execute([$alert_id]);
$related_transactions = $stmt->fetchAll();

// Get audit logs for this alert
$stmt = $pdo->prepare("
    SELECT al.*, u.email as admin_email, u.full_name as admin_name
    FROM audit_logs al
    JOIN users u ON al.user_id = u.id
    WHERE al.action = 'alert_resolve' 
    AND JSON_EXTRACT(al.details, '$.alert_id') = ?
    ORDER BY al.created_at DESC
");
$stmt->execute([$alert_id]);
$audit_logs = $stmt->fetchAll();

// Handle alert status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'resolve' && $alert['status'] === 'pending') {
        $stmt = $pdo->prepare("UPDATE notifications SET status = 'resolved' WHERE id = ?");
        $stmt->execute([$alert_id]);
        
        // Log the action
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (user_id, action, details, ip_address)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $_SESSION['user_id'],
            'alert_resolve',
            json_encode(['alert_id' => $alert_id]),
            $_SERVER['REMOTE_ADDR']
        ]);
        
        header("Location: alert_details.php?id=$alert_id&success=1");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles de Alerta - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin.css">
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <div class="container">
        <div class="admin-header">
            <h1>Detalles de Alerta</h1>
            <p>Información detallada de la alerta del sistema</p>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                La operación se realizó correctamente.
            </div>
        <?php endif; ?>

        <div class="admin-grid">
            <!-- Alert Information -->
            <div class="admin-card">
                <div class="card-header">
                    <h3>Información de la Alerta</h3>
                    <?php if ($alert['status'] === 'pending'): ?>
                        <form method="POST" class="action-form">
                            <input type="hidden" name="action" value="resolve">
                            <button type="submit" class="btn btn-success">Resolver Alerta</button>
                        </form>
                    <?php endif; ?>
                </div>
                <div class="alert-details">
                    <div class="detail-row">
                        <span class="label">Mensaje:</span>
                        <span class="value"><?php echo htmlspecialchars($alert['message']); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Detalles:</span>
                        <span class="value"><?php echo htmlspecialchars($alert['details'] ?? 'No hay detalles adicionales'); ?></span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Usuario:</span>
                        <span class="value">
                            <?php echo htmlspecialchars($alert['full_name']); ?>
                            (<?php echo htmlspecialchars($alert['email']); ?>)
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Tipo:</span>
                        <span class="value">
                            <span class="alert-type <?php echo $alert['severity']; ?>">
                                <?php echo ucfirst($alert['severity']); ?>
                            </span>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Estado:</span>
                        <span class="value">
                            <span class="alert-status <?php echo $alert['status']; ?>">
                                <?php echo ucfirst($alert['status']); ?>
                            </span>
                        </span>
                    </div>
                    <div class="detail-row">
                        <span class="label">Fecha de Creación:</span>
                        <span class="value"><?php echo date('d/m/Y H:i', strtotime($alert['created_at'])); ?></span>
                    </div>
                </div>
            </div>

            <?php if (!empty($related_transactions)): ?>
                <!-- Related Transactions -->
                <div class="admin-card">
                    <div class="card-header">
                        <h3>Transacciones Relacionadas</h3>
                    </div>
                    <div class="transactions-list">
                        <?php foreach ($related_transactions as $transaction): ?>
                            <div class="transaction-item">
                                <div class="transaction-info">
                                    <h4><?php echo htmlspecialchars($transaction['description']); ?></h4>
                                    <p class="category"><?php echo htmlspecialchars($transaction['category_name']); ?></p>
                                </div>
                                <div class="transaction-amount <?php echo $transaction['amount'] >= 0 ? 'positive' : 'negative'; ?>">
                                    <?php echo number_format($transaction['amount'], 2); ?> €
                                </div>
                                <div class="transaction-date">
                                    <?php echo date('d/m/Y', strtotime($transaction['date'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Audit Logs -->
            <div class="admin-card">
                <div class="card-header">
                    <h3>Registro de Actividad</h3>
                </div>
                <div class="audit-logs">
                    <?php if (empty($audit_logs)): ?>
                        <p class="no-data">No hay registros de actividad para esta alerta.</p>
                    <?php else: ?>
                        <?php foreach ($audit_logs as $log): ?>
                            <div class="log-item">
                                <div class="log-info">
                                    <p class="action">
                                        <?php echo htmlspecialchars($log['admin_name']); ?> 
                                        (<?php echo htmlspecialchars($log['admin_email']); ?>)
                                    </p>
                                    <p class="details"><?php echo htmlspecialchars($log['action']); ?></p>
                                </div>
                                <div class="log-date">
                                    <?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 
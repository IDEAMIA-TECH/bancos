<?php
require_once __DIR__ . '/../../includes/auth_functions.php';
requireLogin();
requireAdmin();

if (!isset($_GET['id'])) {
    header('Location: users.php');
    exit;
}

$user_id = $_GET['id'];

// Get user details
$stmt = $pdo->prepare("
    SELECT u.*, 
           (SELECT COUNT(*) FROM bank_connections bc WHERE bc.user_id = u.id) as connection_count,
           (SELECT COUNT(*) FROM debts d WHERE d.user_id = u.id) as debt_count,
           (SELECT COUNT(*) FROM transactions t 
            JOIN accounts a ON t.account_id = a.id 
            JOIN bank_connections bc ON a.bank_connection_id = bc.id 
            WHERE bc.user_id = u.id) as transaction_count
    FROM users u
    WHERE u.id = ?
");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: users.php');
    exit;
}

// Get user's bank connections
$stmt = $pdo->prepare("
    SELECT bc.*, 
           (SELECT COUNT(*) FROM accounts a WHERE a.bank_connection_id = bc.id) as account_count
    FROM bank_connections bc
    WHERE bc.user_id = ?
    ORDER BY bc.created_at DESC
");
$stmt->execute([$user_id]);
$connections = $stmt->fetchAll();

// Get user's debts
$stmt = $pdo->prepare("
    SELECT d.*, bc.institution_id
    FROM debts d
    JOIN accounts a ON d.account_id = a.id
    JOIN bank_connections bc ON a.bank_connection_id = bc.id
    WHERE bc.user_id = ?
    ORDER BY d.created_at DESC
");
$stmt->execute([$user_id]);
$debts = $stmt->fetchAll();

// Get user's recent transactions
$stmt = $pdo->prepare("
    SELECT t.*, a.account_number, bc.institution_id, c.name as category_name
    FROM transactions t
    JOIN accounts a ON t.account_id = a.id
    JOIN bank_connections bc ON a.bank_connection_id = bc.id
    LEFT JOIN categories c ON t.category_id = c.id
    WHERE bc.user_id = ?
    ORDER BY t.transaction_date DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$transactions = $stmt->fetchAll();

// Get user's audit logs
$stmt = $pdo->prepare("
    SELECT al.*
    FROM audit_logs al
    WHERE al.user_id = ?
    ORDER BY al.created_at DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$audit_logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles de Usuario - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin.css">
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <div class="container">
        <div class="admin-header">
            <h1>Detalles de Usuario</h1>
            <p>Información detallada del usuario</p>
        </div>

        <div class="user-details-grid">
            <div class="admin-card">
                <div class="card-header">
                    <h3>Información Personal</h3>
                    <div class="header-actions">
                        <span class="user-status <?php echo $user['status']; ?>">
                            <?php echo ucfirst($user['status']); ?>
                        </span>
                    </div>
                </div>
                <div class="user-info-details">
                    <div class="info-group">
                        <label>Nombre Completo</label>
                        <p><?php echo htmlspecialchars($user['full_name']); ?></p>
                    </div>
                    <div class="info-group">
                        <label>Email</label>
                        <p><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                    <div class="info-group">
                        <label>Fecha de Registro</label>
                        <p><?php echo date('d/m/Y H:i', strtotime($user['created_at'])); ?></p>
                    </div>
                    <div class="info-group">
                        <label>Último Acceso</label>
                        <p><?php echo $user['last_login'] ? date('d/m/Y H:i', strtotime($user['last_login'])) : 'Nunca'; ?></p>
                    </div>
                </div>
            </div>

            <div class="admin-card">
                <div class="card-header">
                    <h3>Resumen de Actividad</h3>
                </div>
                <div class="activity-summary">
                    <div class="summary-item">
                        <span class="label">Conexiones Bancarias</span>
                        <span class="value"><?php echo $user['connection_count']; ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="label">Deudas Activas</span>
                        <span class="value"><?php echo $user['debt_count']; ?></span>
                    </div>
                    <div class="summary-item">
                        <span class="label">Transacciones</span>
                        <span class="value"><?php echo $user['transaction_count']; ?></span>
                    </div>
                </div>
            </div>

            <div class="admin-card">
                <div class="card-header">
                    <h3>Conexiones Bancarias</h3>
                </div>
                <div class="connections-list">
                    <?php foreach ($connections as $connection): ?>
                        <div class="connection-item">
                            <div class="connection-info">
                                <h4><?php echo htmlspecialchars($connection['institution_id']); ?></h4>
                                <p class="accounts"><?php echo $connection['account_count']; ?> cuentas</p>
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
                    <h3>Deudas Activas</h3>
                </div>
                <div class="debts-list">
                    <?php foreach ($debts as $debt): ?>
                        <div class="debt-item">
                            <div class="debt-info">
                                <h4><?php echo htmlspecialchars($debt['institution_id']); ?></h4>
                                <p class="amount">$<?php echo number_format($debt['current_amount'], 2); ?></p>
                            </div>
                            <div class="debt-details">
                                <span class="interest"><?php echo $debt['interest_rate']; ?>% Tasa</span>
                                <span class="payment">$<?php echo number_format($debt['monthly_payment'], 2); ?> Mensual</span>
                            </div>
                            <div class="debt-status <?php echo $debt['status']; ?>">
                                <?php echo ucfirst($debt['status']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="admin-card">
                <div class="card-header">
                    <h3>Transacciones Recientes</h3>
                </div>
                <div class="transactions-list">
                    <?php foreach ($transactions as $transaction): ?>
                        <div class="transaction-item">
                            <div class="transaction-info">
                                <h4><?php echo htmlspecialchars($transaction['description']); ?></h4>
                                <p class="account"><?php echo htmlspecialchars($transaction['institution_id']); ?> - <?php echo $transaction['account_number']; ?></p>
                            </div>
                            <div class="transaction-details">
                                <span class="amount <?php echo $transaction['amount'] >= 0 ? 'positive' : 'negative'; ?>">
                                    $<?php echo number_format(abs($transaction['amount']), 2); ?>
                                </span>
                                <span class="category"><?php echo htmlspecialchars($transaction['category_name'] ?? 'Sin categoría'); ?></span>
                            </div>
                            <div class="transaction-date">
                                <?php echo date('d/m/Y', strtotime($transaction['transaction_date'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="admin-card">
                <div class="card-header">
                    <h3>Registro de Actividad</h3>
                </div>
                <div class="audit-list">
                    <?php foreach ($audit_logs as $log): ?>
                        <div class="audit-item">
                            <div class="audit-info">
                                <h4><?php echo htmlspecialchars($log['action']); ?></h4>
                                <p class="details"><?php echo htmlspecialchars($log['details']); ?></p>
                            </div>
                            <div class="audit-date">
                                <?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</body>
</html> 
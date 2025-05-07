<?php
require_once __DIR__ . '/../../includes/auth_functions.php';
requireLogin();
requireAdmin();

if (!isset($_GET['id'])) {
    header('Location: connections.php');
    exit;
}

$connection_id = $_GET['id'];

// Get connection details
$stmt = $pdo->prepare("
    SELECT bc.*, u.email, u.full_name
    FROM bank_connections bc
    JOIN users u ON bc.user_id = u.id
    WHERE bc.id = ?
");
$stmt->execute([$connection_id]);
$connection = $stmt->fetch();

if (!$connection) {
    header('Location: connections.php');
    exit;
}

// Get connection's accounts
$stmt = $pdo->prepare("
    SELECT a.*, 
           (SELECT COUNT(*) FROM transactions t WHERE t.account_id = a.id) as transaction_count,
           (SELECT COUNT(*) FROM debts d WHERE d.account_id = a.id) as debt_count
    FROM accounts a
    WHERE a.bank_connection_id = ?
    ORDER BY a.created_at DESC
");
$stmt->execute([$connection_id]);
$accounts = $stmt->fetchAll();

// Get connection's recent transactions
$stmt = $pdo->prepare("
    SELECT t.*, a.account_number, c.name as category_name
    FROM transactions t
    JOIN accounts a ON t.account_id = a.id
    LEFT JOIN categories c ON t.category_id = c.id
    WHERE a.bank_connection_id = ?
    ORDER BY t.transaction_date DESC
    LIMIT 10
");
$stmt->execute([$connection_id]);
$transactions = $stmt->fetchAll();

// Get connection's audit logs
$stmt = $pdo->prepare("
    SELECT al.*
    FROM audit_logs al
    WHERE al.details LIKE ?
    ORDER BY al.created_at DESC
    LIMIT 10
");
$stmt->execute(['%connection_id":"' . $connection_id . '"%']);
$audit_logs = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalles de Conexión - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/admin.css">
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <div class="container">
        <div class="admin-header">
            <h1>Detalles de Conexión</h1>
            <p>Información detallada de la conexión bancaria</p>
        </div>

        <div class="connection-details-grid">
            <div class="admin-card">
                <div class="card-header">
                    <h3>Información de la Conexión</h3>
                    <div class="header-actions">
                        <span class="connection-status <?php echo $connection['status']; ?>">
                            <?php echo ucfirst($connection['status']); ?>
                        </span>
                    </div>
                </div>
                <div class="connection-info-details">
                    <div class="info-group">
                        <label>Institución</label>
                        <p><?php echo htmlspecialchars($connection['institution_id']); ?></p>
                    </div>
                    <div class="info-group">
                        <label>Usuario</label>
                        <p><?php echo htmlspecialchars($connection['full_name']); ?> (<?php echo htmlspecialchars($connection['email']); ?>)</p>
                    </div>
                    <div class="info-group">
                        <label>Fecha de Creación</label>
                        <p><?php echo date('d/m/Y H:i', strtotime($connection['created_at'])); ?></p>
                    </div>
                    <div class="info-group">
                        <label>Última Sincronización</label>
                        <p><?php echo date('d/m/Y H:i', strtotime($connection['last_sync'])); ?></p>
                    </div>
                </div>
            </div>

            <div class="admin-card">
                <div class="card-header">
                    <h3>Cuentas Vinculadas</h3>
                </div>
                <div class="accounts-list">
                    <?php foreach ($accounts as $account): ?>
                        <div class="account-item">
                            <div class="account-info">
                                <h4><?php echo htmlspecialchars($account['account_number']); ?></h4>
                                <p class="type"><?php echo htmlspecialchars($account['account_type']); ?></p>
                            </div>
                            <div class="account-stats">
                                <span class="stat">
                                    <i class="fas fa-exchange-alt"></i>
                                    <?php echo $account['transaction_count']; ?> transacciones
                                </span>
                                <span class="stat">
                                    <i class="fas fa-money-bill-wave"></i>
                                    <?php echo $account['debt_count']; ?> deudas
                                </span>
                            </div>
                            <div class="account-balance">
                                $<?php echo number_format($account['current_balance'], 2); ?>
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
                                <p class="account"><?php echo $transaction['account_number']; ?></p>
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
<?php
require_once __DIR__ . '/../../includes/auth_functions.php';
requireLogin();

// Get user's debts with account information
$stmt = $pdo->prepare("
    SELECT d.*, a.account_number, a.account_type, bc.institution_id,
           (SELECT SUM(amount) FROM transactions 
            WHERE account_id = a.id 
            AND transaction_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
            AND amount < 0) as monthly_payments
    FROM debts d
    JOIN accounts a ON d.account_id = a.id
    JOIN bank_connections bc ON a.bank_connection_id = bc.id
    WHERE d.user_id = ? AND d.status = 'active'
    ORDER BY d.current_amount DESC
");
$stmt->execute([$_SESSION['user_id']]);
$debts = $stmt->fetchAll();

// Calculate total debt and monthly payments
$total_debt = 0;
$total_monthly_payments = 0;
foreach ($debts as $debt) {
    $total_debt += $debt['current_amount'];
    $total_monthly_payments += abs($debt['monthly_payments'] ?? 0);
}

// Get user's monthly income
$stmt = $pdo->prepare("
    SELECT SUM(amount) as total_income
    FROM transactions t
    JOIN accounts a ON t.account_id = a.id
    JOIN bank_connections bc ON a.bank_connection_id = bc.id
    WHERE bc.user_id = ?
    AND t.transaction_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
    AND t.amount > 0
");
$stmt->execute([$_SESSION['user_id']]);
$income = $stmt->fetch()['total_income'] ?? 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Deudas - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/debts.css">
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <div class="container">
        <div class="debt-summary">
            <div class="summary-card total-debt">
                <h3>Deuda Total</h3>
                <p class="amount">$<?php echo number_format($total_debt, 2); ?></p>
            </div>
            <div class="summary-card monthly-payments">
                <h3>Pagos Mensuales</h3>
                <p class="amount">$<?php echo number_format($total_monthly_payments, 2); ?></p>
            </div>
            <div class="summary-card monthly-income">
                <h3>Ingreso Mensual</h3>
                <p class="amount">$<?php echo number_format($income, 2); ?></p>
            </div>
        </div>

        <div class="debt-analysis">
            <h2>Análisis de Deudas</h2>
            <div class="analysis-grid">
                <div class="analysis-card">
                    <h4>Ratio de Deuda</h4>
                    <p class="ratio">
                        <?php 
                        $debt_ratio = $income > 0 ? ($total_monthly_payments / $income) * 100 : 0;
                        echo number_format($debt_ratio, 1) . '%';
                        ?>
                    </p>
                    <p class="status <?php echo $debt_ratio > 50 ? 'warning' : 'good'; ?>">
                        <?php echo $debt_ratio > 50 ? 'Alto' : 'Saludable'; ?>
                    </p>
                </div>
                <div class="analysis-card">
                    <h4>Deuda Promedio</h4>
                    <p class="amount">
                        $<?php echo number_format(count($debts) > 0 ? $total_debt / count($debts) : 0, 2); ?>
                    </p>
                    <p class="subtitle">por cuenta</p>
                </div>
                <div class="analysis-card">
                    <h4>Tasa Promedio</h4>
                    <p class="rate">
                        <?php 
                        $avg_rate = 0;
                        foreach ($debts as $debt) {
                            $avg_rate += $debt['interest_rate'];
                        }
                        echo number_format(count($debts) > 0 ? $avg_rate / count($debts) : 0, 1) . '%';
                        ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="debts-list">
            <h2>Mis Deudas</h2>
            <?php if (empty($debts)): ?>
                <p class="no-debts">No tienes deudas activas registradas.</p>
            <?php else: ?>
                <?php foreach ($debts as $debt): ?>
                    <div class="debt-card">
                        <div class="debt-info">
                            <h3><?php echo htmlspecialchars($debt['institution_id']); ?></h3>
                            <p class="account-number">Cuenta: <?php echo htmlspecialchars($debt['account_number']); ?></p>
                            <p class="debt-amount">$<?php echo number_format($debt['current_amount'], 2); ?></p>
                        </div>
                        <div class="debt-details">
                            <div class="detail">
                                <span class="label">Tasa de Interés:</span>
                                <span class="value"><?php echo $debt['interest_rate']; ?>%</span>
                            </div>
                            <div class="detail">
                                <span class="label">Pago Mensual:</span>
                                <span class="value">$<?php echo number_format(abs($debt['monthly_payments'] ?? 0), 2); ?></span>
                            </div>
                            <div class="detail">
                                <span class="label">Fecha de Vencimiento:</span>
                                <span class="value"><?php echo date('d/m/Y', strtotime($debt['due_date'])); ?></span>
                            </div>
                        </div>
                        <div class="debt-actions">
                            <a href="plan.php?debt_id=<?php echo $debt['id']; ?>" class="btn-primary">Ver Plan</a>
                            <button onclick="updateDebt(<?php echo $debt['id']; ?>)" class="btn-secondary">Actualizar</button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function updateDebt(debtId) {
            // Implementar actualización de deuda
            alert('Función en desarrollo');
        }
    </script>
</body>
</html> 
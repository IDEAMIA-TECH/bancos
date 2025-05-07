<?php
require_once __DIR__ . '/../../includes/auth_functions.php';
requireLogin();

// Get user's active debts with details
$stmt = $pdo->prepare("
    SELECT d.*, a.account_number, bc.institution_id,
           (SELECT SUM(amount) FROM transactions t 
            WHERE t.account_id = d.account_id 
            AND t.transaction_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
            AND t.amount < 0) as monthly_payment
    FROM debts d
    JOIN accounts a ON d.account_id = a.id
    JOIN bank_connections bc ON a.bank_connection_id = bc.id
    WHERE d.user_id = ? AND d.status = 'active'
    ORDER BY d.current_amount DESC
");
$stmt->execute([$_SESSION['user_id']]);
$debts = $stmt->fetchAll();

// Calculate consolidation metrics
$total_debt = 0;
$total_monthly_payments = 0;
$debt_by_institution = [];
$debt_by_type = [];

foreach ($debts as $debt) {
    $total_debt += $debt['current_amount'];
    $total_monthly_payments += abs($debt['monthly_payment'] ?? 0);
    
    // Group by institution
    if (!isset($debt_by_institution[$debt['institution_id']])) {
        $debt_by_institution[$debt['institution_id']] = 0;
    }
    $debt_by_institution[$debt['institution_id']] += $debt['current_amount'];
    
    // Group by account type
    $account_type = $debt['account_type'];
    if (!isset($debt_by_type[$account_type])) {
        $debt_by_type[$account_type] = 0;
    }
    $debt_by_type[$account_type] += $debt['current_amount'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consolidación de Deudas - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/debts.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <div class="container">
        <div class="consolidation-header">
            <h1>Consolidación de Deudas</h1>
            <p>Analiza y agrupa tus deudas para una mejor gestión</p>
        </div>

        <div class="consolidation-summary">
            <div class="summary-card">
                <h3>Deuda Total</h3>
                <p class="amount">$<?php echo number_format($total_debt, 2); ?></p>
            </div>
            <div class="summary-card">
                <h3>Pagos Mensuales</h3>
                <p class="amount">$<?php echo number_format($total_monthly_payments, 2); ?></p>
            </div>
            <div class="summary-card">
                <h3>Deudas Activas</h3>
                <p class="amount"><?php echo count($debts); ?></p>
            </div>
        </div>

        <div class="consolidation-grid">
            <div class="consolidation-card">
                <h3>Distribución por Institución</h3>
                <div class="chart-container">
                    <canvas id="institutionChart"></canvas>
                </div>
            </div>
            <div class="consolidation-card">
                <h3>Distribución por Tipo</h3>
                <div class="chart-container">
                    <canvas id="typeChart"></canvas>
                </div>
            </div>
            <div class="consolidation-card full-width">
                <h3>Lista de Deudas</h3>
                <div class="debts-list">
                    <?php foreach ($debts as $debt): ?>
                        <div class="debt-card">
                            <div class="debt-info">
                                <h4><?php echo htmlspecialchars($debt['institution_id']); ?></h4>
                                <p class="account"><?php echo htmlspecialchars($debt['account_number']); ?></p>
                            </div>
                            <div class="debt-details">
                                <div class="detail-item">
                                    <span class="label">Monto Actual</span>
                                    <span class="value">$<?php echo number_format($debt['current_amount'], 2); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="label">Tasa de Interés</span>
                                    <span class="value"><?php echo $debt['interest_rate']; ?>%</span>
                                </div>
                                <div class="detail-item">
                                    <span class="label">Pago Mensual</span>
                                    <span class="value">$<?php echo number_format(abs($debt['monthly_payment'] ?? 0), 2); ?></span>
                                </div>
                            </div>
                            <div class="debt-actions">
                                <a href="plan.php?id=<?php echo $debt['id']; ?>" class="btn-primary">Ver Plan</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Institution Distribution Chart
        const institutionCtx = document.getElementById('institutionChart').getContext('2d');
        new Chart(institutionCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_keys($debt_by_institution)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($debt_by_institution)); ?>,
                    backgroundColor: [
                        '#2196f3',
                        '#4caf50',
                        '#ff9800',
                        '#f44336',
                        '#9c27b0'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Type Distribution Chart
        const typeCtx = document.getElementById('typeChart').getContext('2d');
        new Chart(typeCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_keys($debt_by_type)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($debt_by_type)); ?>,
                    backgroundColor: [
                        '#2196f3',
                        '#4caf50',
                        '#ff9800',
                        '#f44336'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
</body>
</html> 
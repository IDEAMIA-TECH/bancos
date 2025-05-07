<?php
require_once __DIR__ . '/../../includes/auth_functions.php';
requireLogin();

// Get user's active debts
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

// Get user's monthly income
$stmt = $pdo->prepare("
    SELECT SUM(amount) as monthly_income
    FROM transactions t
    JOIN accounts a ON t.account_id = a.id
    JOIN bank_connections bc ON a.bank_connection_id = bc.id
    WHERE bc.user_id = ? 
    AND t.transaction_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
    AND t.amount > 0
");
$stmt->execute([$_SESSION['user_id']]);
$income = $stmt->fetch();

// Calculate total debt and monthly payments
$total_debt = 0;
$total_monthly_payments = 0;
foreach ($debts as $debt) {
    $total_debt += $debt['current_amount'];
    $total_monthly_payments += abs($debt['monthly_payment'] ?? 0);
}

// Calculate 50/30/20 allocation
$needs = $income['monthly_income'] * 0.5;
$wants = $income['monthly_income'] * 0.3;
$savings_debt = $income['monthly_income'] * 0.2;

// Sort debts for snowball method
$snowball_debts = $debts;
usort($snowball_debts, function($a, $b) {
    return $a['current_amount'] - $b['current_amount'];
});
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estrategia de Pago - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/debts.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <div class="container">
        <div class="strategy-header">
            <h1>Estrategia de Pago</h1>
            <p>Selecciona el método que mejor se adapte a tus necesidades</p>
        </div>

        <div class="strategy-grid">
            <div class="strategy-card">
                <h3>Método 50/30/20</h3>
                <div class="strategy-description">
                    <p>Distribuye tus ingresos en:</p>
                    <ul>
                        <li>50% para necesidades básicas</li>
                        <li>30% para gastos personales</li>
                        <li>20% para ahorro y pago de deudas</li>
                    </ul>
                </div>
                <div class="allocation-chart">
                    <canvas id="allocationChart"></canvas>
                </div>
                <div class="allocation-details">
                    <div class="detail-item">
                        <span class="label">Ingresos Mensuales</span>
                        <span class="value">$<?php echo number_format($income['monthly_income'], 2); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Necesidades (50%)</span>
                        <span class="value">$<?php echo number_format($needs, 2); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Deseos (30%)</span>
                        <span class="value">$<?php echo number_format($wants, 2); ?></span>
                    </div>
                    <div class="detail-item">
                        <span class="label">Ahorro/Deuda (20%)</span>
                        <span class="value">$<?php echo number_format($savings_debt, 2); ?></span>
                    </div>
                </div>
                <div class="strategy-actions">
                    <a href="plan.php?method=50_30_20" class="btn-primary">Aplicar Método</a>
                </div>
            </div>

            <div class="strategy-card">
                <h3>Método Snowball</h3>
                <div class="strategy-description">
                    <p>Paga primero las deudas más pequeñas:</p>
                    <ul>
                        <li>Enfócate en la deuda más pequeña</li>
                        <li>Paga el mínimo en las demás</li>
                        <li>Usa el dinero extra para la siguiente deuda</li>
                    </ul>
                </div>
                <div class="snowball-list">
                    <?php foreach ($snowball_debts as $index => $debt): ?>
                        <div class="snowball-item">
                            <div class="snowball-number"><?php echo $index + 1; ?></div>
                            <div class="snowball-details">
                                <h4><?php echo htmlspecialchars($debt['institution_id']); ?></h4>
                                <p class="amount">$<?php echo number_format($debt['current_amount'], 2); ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="strategy-actions">
                    <a href="plan.php?method=snowball" class="btn-primary">Aplicar Método</a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Allocation Chart
        const allocationCtx = document.getElementById('allocationChart').getContext('2d');
        new Chart(allocationCtx, {
            type: 'doughnut',
            data: {
                labels: ['Necesidades (50%)', 'Deseos (30%)', 'Ahorro/Deuda (20%)'],
                datasets: [{
                    data: [50, 30, 20],
                    backgroundColor: [
                        '#2196f3',
                        '#4caf50',
                        '#ff9800'
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
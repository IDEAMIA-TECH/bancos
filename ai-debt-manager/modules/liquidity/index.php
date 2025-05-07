<?php
require_once __DIR__ . '/../../includes/auth_functions.php';
requireLogin();

// Get user's transactions for the last 3 months
$stmt = $pdo->prepare("
    SELECT 
        t.*,
        c.name as category_name,
        c.type as category_type,
        a.account_number,
        bc.institution_id
    FROM transactions t
    JOIN accounts a ON t.account_id = a.id
    JOIN bank_connections bc ON a.bank_connection_id = bc.id
    LEFT JOIN categories c ON t.category_id = c.id
    WHERE bc.user_id = ?
    AND t.transaction_date >= DATE_SUB(CURRENT_DATE, INTERVAL 3 MONTH)
    ORDER BY t.transaction_date ASC
");
$stmt->execute([$_SESSION['user_id']]);
$transactions = $stmt->fetchAll();

// Get user's active debts
$stmt = $pdo->prepare("
    SELECT d.*
    FROM debts d
    WHERE d.user_id = ? AND d.status = 'active'
");
$stmt->execute([$_SESSION['user_id']]);
$debts = $stmt->fetchAll();

// Calculate daily balances for the last 3 months
$daily_balances = [];
$current_balance = 0;

foreach ($transactions as $transaction) {
    $date = date('Y-m-d', strtotime($transaction['transaction_date']));
    $current_balance += $transaction['amount'];
    $daily_balances[$date] = $current_balance;
}

// Calculate average daily income and expenses
$daily_income = [];
$daily_expenses = [];

foreach ($transactions as $transaction) {
    $date = date('Y-m-d', strtotime($transaction['transaction_date']));
    if ($transaction['amount'] > 0) {
        if (!isset($daily_income[$date])) {
            $daily_income[$date] = 0;
        }
        $daily_income[$date] += $transaction['amount'];
    } else {
        if (!isset($daily_expenses[$date])) {
            $daily_expenses[$date] = 0;
        }
        $daily_expenses[$date] += abs($transaction['amount']);
    }
}

$avg_daily_income = array_sum($daily_income) / count($daily_income);
$avg_daily_expenses = array_sum($daily_expenses) / count($daily_expenses);

// Calculate monthly debt payments
$monthly_debt_payments = 0;
foreach ($debts as $debt) {
    // Calculate monthly payment based on debt amount and interest rate
    $principal = $debt['amount'];
    $interest_rate = $debt['interest_rate'] / 100 / 12; // Monthly interest rate
    $months = 12; // Assuming 12 months payment period
    
    if ($interest_rate > 0) {
        $monthly_payment = ($principal * $interest_rate * pow(1 + $interest_rate, $months)) / 
                          (pow(1 + $interest_rate, $months) - 1);
    } else {
        $monthly_payment = $principal / $months;
    }
    
    $monthly_debt_payments += $monthly_payment;
}

// Predict liquidity for the next 30 days
$predictions = [];
$current_date = date('Y-m-d');
$current_balance = end($daily_balances);

for ($i = 1; $i <= 30; $i++) {
    $date = date('Y-m-d', strtotime("+$i days"));
    $day_of_week = date('N', strtotime($date));
    
    // Adjust daily income/expenses based on day of week
    $daily_factor = 1;
    if ($day_of_week >= 6) { // Weekend
        $daily_factor = 0.5; // Less income, more expenses on weekends
    }
    
    // Calculate predicted balance
    $predicted_income = $avg_daily_income * $daily_factor;
    $predicted_expenses = $avg_daily_expenses * (2 - $daily_factor);
    
    // Add monthly debt payments on the 1st of each month
    if (date('d', strtotime($date)) == '01') {
        $predicted_expenses += $monthly_debt_payments;
    }
    
    $current_balance += $predicted_income - $predicted_expenses;
    $predictions[$date] = $current_balance;
}

// Calculate risk level
$min_balance = min($predictions);
$risk_level = 'low';
if ($min_balance < 0) {
    $risk_level = 'high';
} elseif ($min_balance < 1000) {
    $risk_level = 'medium';
}

// Get dates of potential liquidity issues
$risk_dates = [];
foreach ($predictions as $date => $balance) {
    if ($balance < 0) {
        $risk_dates[] = $date;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Predicción de Liquidez - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/liquidity.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <div class="container">
        <div class="liquidity-header">
            <h1>Predicción de Liquidez</h1>
            <p>Visualiza tu flujo de efectivo proyectado para los próximos 30 días</p>
        </div>

        <div class="liquidity-summary">
            <div class="summary-card">
                <h3>Nivel de Riesgo</h3>
                <p class="risk-level <?php echo $risk_level; ?>">
                    <?php
                    switch ($risk_level) {
                        case 'high':
                            echo 'Alto';
                            break;
                        case 'medium':
                            echo 'Medio';
                            break;
                        case 'low':
                            echo 'Bajo';
                            break;
                    }
                    ?>
                </p>
                <p class="subtitle">Basado en proyecciones</p>
            </div>
            <div class="summary-card">
                <h3>Ingreso Diario Promedio</h3>
                <p class="amount positive">$<?php echo number_format($avg_daily_income, 2); ?></p>
                <p class="subtitle">Últimos 3 meses</p>
            </div>
            <div class="summary-card">
                <h3>Gasto Diario Promedio</h3>
                <p class="amount negative">$<?php echo number_format($avg_daily_expenses, 2); ?></p>
                <p class="subtitle">Últimos 3 meses</p>
            </div>
        </div>

        <div class="liquidity-grid">
            <div class="liquidity-card full-width">
                <h3>Proyección de Saldo</h3>
                <div class="chart-container">
                    <canvas id="projectionChart"></canvas>
                </div>
            </div>

            <?php if (!empty($risk_dates)): ?>
            <div class="liquidity-card">
                <h3>Alertas de Liquidez</h3>
                <div class="alerts-list">
                    <?php foreach ($risk_dates as $date): ?>
                        <div class="alert-item">
                            <div class="alert-date">
                                <?php echo date('d/m/Y', strtotime($date)); ?>
                            </div>
                            <div class="alert-message">
                                Saldo proyectado: $<?php echo number_format($predictions[$date], 2); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <div class="liquidity-card">
                <h3>Recomendaciones</h3>
                <div class="recommendations-list">
                    <?php if ($risk_level == 'high'): ?>
                        <div class="recommendation-item">
                            <h4>Reducir Gastos</h4>
                            <p>Considera reducir gastos no esenciales para evitar problemas de liquidez.</p>
                        </div>
                        <div class="recommendation-item">
                            <h4>Fondo de Emergencia</h4>
                            <p>Establece un fondo de emergencia para cubrir gastos inesperados.</p>
                        </div>
                    <?php elseif ($risk_level == 'medium'): ?>
                        <div class="recommendation-item">
                            <h4>Monitoreo</h4>
                            <p>Mantén un seguimiento cercano de tus gastos y saldos.</p>
                        </div>
                        <div class="recommendation-item">
                            <h4>Planificación</h4>
                            <p>Planifica tus gastos con anticipación para evitar sorpresas.</p>
                        </div>
                    <?php else: ?>
                        <div class="recommendation-item">
                            <h4>Mantener</h4>
                            <p>Continúa con tus buenos hábitos financieros.</p>
                        </div>
                        <div class="recommendation-item">
                            <h4>Inversión</h4>
                            <p>Considera invertir tus excedentes para generar ingresos adicionales.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Projection Chart
        const projectionCtx = document.getElementById('projectionChart').getContext('2d');
        new Chart(projectionCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_keys($predictions)); ?>,
                datasets: [{
                    label: 'Saldo Proyectado',
                    data: <?php echo json_encode(array_values($predictions)); ?>,
                    borderColor: '#2196f3',
                    backgroundColor: 'rgba(33, 150, 243, 0.1)',
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    </script>
</body>
</html> 
<?php
require_once __DIR__ . '/../../includes/auth_functions.php';
requireLogin();

// Get user's financial summary
$stmt = $pdo->prepare("
    SELECT 
        (SELECT SUM(current_amount) FROM debts WHERE user_id = ? AND status = 'active') as total_debt,
        (SELECT SUM(balance) FROM accounts a 
         JOIN bank_connections bc ON a.bank_connection_id = bc.id 
         WHERE bc.user_id = ? AND a.account_type IN ('checking', 'savings')) as total_balance,
        (SELECT SUM(amount) FROM transactions t
         JOIN accounts a ON t.account_id = a.id
         JOIN bank_connections bc ON a.bank_connection_id = bc.id
         WHERE bc.user_id = ? AND t.transaction_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
         AND t.amount > 0) as monthly_income,
        (SELECT SUM(amount) FROM transactions t
         JOIN accounts a ON t.account_id = a.id
         JOIN bank_connections bc ON a.bank_connection_id = bc.id
         WHERE bc.user_id = ? AND t.transaction_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
         AND t.amount < 0) as monthly_expenses
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$summary = $stmt->fetch();

// Get recent transactions
$stmt = $pdo->prepare("
    SELECT t.*, a.account_number, bc.institution_id
    FROM transactions t
    JOIN accounts a ON t.account_id = a.id
    JOIN bank_connections bc ON a.bank_connection_id = bc.id
    WHERE bc.user_id = ?
    ORDER BY t.transaction_date DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$recent_transactions = $stmt->fetchAll();

// Get debt distribution
$stmt = $pdo->prepare("
    SELECT bc.institution_id, SUM(d.current_amount) as total
    FROM debts d
    JOIN accounts a ON d.account_id = a.id
    JOIN bank_connections bc ON a.bank_connection_id = bc.id
    WHERE d.user_id = ? AND d.status = 'active'
    GROUP BY bc.institution_id
");
$stmt->execute([$_SESSION['user_id']]);
$debt_distribution = $stmt->fetchAll();

// Get expense categories
$stmt = $pdo->prepare("
    SELECT c.name, SUM(ABS(t.amount)) as total
    FROM transactions t
    JOIN categories c ON t.category_id = c.id
    JOIN accounts a ON t.account_id = a.id
    JOIN bank_connections bc ON a.bank_connection_id = bc.id
    WHERE bc.user_id = ? 
    AND t.transaction_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)
    AND t.amount < 0
    GROUP BY c.name
    ORDER BY total DESC
    LIMIT 5
");
$stmt->execute([$_SESSION['user_id']]);
$expense_categories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/dashboard.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <div class="container">
        <div class="dashboard-header">
            <h1>Dashboard Financiero</h1>
            <p>Bienvenido, <?php echo htmlspecialchars($_SESSION['user_name']); ?></p>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card">
                <h3>Deuda Total</h3>
                <p class="amount">$<?php echo number_format($summary['total_debt'], 2); ?></p>
                <div class="trend <?php echo $summary['total_debt'] < 0 ? 'positive' : 'negative'; ?>">
                    <span class="icon"><?php echo $summary['total_debt'] < 0 ? '↓' : '↑'; ?></span>
                    <span class="text">Tendencia</span>
                </div>
            </div>
            <div class="kpi-card">
                <h3>Balance Total</h3>
                <p class="amount">$<?php echo number_format($summary['total_balance'], 2); ?></p>
                <div class="trend <?php echo $summary['total_balance'] > 0 ? 'positive' : 'negative'; ?>">
                    <span class="icon"><?php echo $summary['total_balance'] > 0 ? '↑' : '↓'; ?></span>
                    <span class="text">Tendencia</span>
                </div>
            </div>
            <div class="kpi-card">
                <h3>Ingresos Mensuales</h3>
                <p class="amount">$<?php echo number_format($summary['monthly_income'], 2); ?></p>
                <div class="trend positive">
                    <span class="icon">↑</span>
                    <span class="text">Últimos 30 días</span>
                </div>
            </div>
            <div class="kpi-card">
                <h3>Gastos Mensuales</h3>
                <p class="amount">$<?php echo number_format(abs($summary['monthly_expenses']), 2); ?></p>
                <div class="trend negative">
                    <span class="icon">↓</span>
                    <span class="text">Últimos 30 días</span>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="dashboard-card">
                <h3>Distribución de Deudas</h3>
                <div class="chart-container">
                    <canvas id="debtDistributionChart"></canvas>
                </div>
            </div>
            <div class="dashboard-card">
                <h3>Categorías de Gastos</h3>
                <div class="chart-container">
                    <canvas id="expenseCategoriesChart"></canvas>
                </div>
            </div>
            <div class="dashboard-card full-width">
                <h3>Transacciones Recientes</h3>
                <div class="transactions-list">
                    <?php foreach ($recent_transactions as $transaction): ?>
                        <div class="transaction-item">
                            <div class="transaction-info">
                                <span class="institution"><?php echo htmlspecialchars($transaction['institution_id']); ?></span>
                                <span class="account"><?php echo htmlspecialchars($transaction['account_number']); ?></span>
                            </div>
                            <div class="transaction-details">
                                <span class="description"><?php echo htmlspecialchars($transaction['description']); ?></span>
                                <span class="date"><?php echo date('d/m/Y', strtotime($transaction['transaction_date'])); ?></span>
                            </div>
                            <div class="transaction-amount <?php echo $transaction['amount'] > 0 ? 'positive' : 'negative'; ?>">
                                $<?php echo number_format(abs($transaction['amount']), 2); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Debt Distribution Chart
        const debtCtx = document.getElementById('debtDistributionChart').getContext('2d');
        new Chart(debtCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($debt_distribution, 'institution_id')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($debt_distribution, 'total')); ?>,
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

        // Expense Categories Chart
        const expenseCtx = document.getElementById('expenseCategoriesChart').getContext('2d');
        new Chart(expenseCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_column($expense_categories, 'name')); ?>,
                datasets: [{
                    label: 'Gastos por Categoría',
                    data: <?php echo json_encode(array_column($expense_categories, 'total')); ?>,
                    backgroundColor: '#2196f3'
                }]
            },
            options: {
                responsive: true,
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
<?php
require_once __DIR__ . '/../../includes/auth_functions.php';
requireLogin();

// Get user's transactions for the last 6 months
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
    AND t.transaction_date >= DATE_SUB(CURRENT_DATE, INTERVAL 6 MONTH)
    ORDER BY t.transaction_date DESC
");
$stmt->execute([$_SESSION['user_id']]);
$transactions = $stmt->fetchAll();

// Calculate monthly totals
$monthly_totals = [];
$category_totals = [];
$income_totals = [];
$expense_totals = [];

foreach ($transactions as $transaction) {
    $month = date('Y-m', strtotime($transaction['transaction_date']));
    
    // Initialize month if not exists
    if (!isset($monthly_totals[$month])) {
        $monthly_totals[$month] = 0;
        $income_totals[$month] = 0;
        $expense_totals[$month] = 0;
    }
    
    // Add to monthly total
    $monthly_totals[$month] += $transaction['amount'];
    
    // Add to income/expense totals
    if ($transaction['amount'] > 0) {
        $income_totals[$month] += $transaction['amount'];
    } else {
        $expense_totals[$month] += abs($transaction['amount']);
    }
    
    // Add to category totals if categorized
    if ($transaction['category_id']) {
        $category = $transaction['category_name'];
        if (!isset($category_totals[$category])) {
            $category_totals[$category] = 0;
        }
        $category_totals[$category] += abs($transaction['amount']);
    }
}

// Sort months
ksort($monthly_totals);
ksort($income_totals);
ksort($expense_totals);

// Calculate averages
$total_months = count($monthly_totals);
$avg_income = array_sum($income_totals) / $total_months;
$avg_expenses = array_sum($expense_totals) / $total_months;
$avg_savings = $avg_income - $avg_expenses;

// Get top categories
arsort($category_totals);
$top_categories = array_slice($category_totals, 0, 5, true);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Análisis de Ingresos y Gastos - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/analysis.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <div class="container">
        <div class="analysis-header">
            <h1>Análisis de Ingresos y Gastos</h1>
            <p>Visualiza tus patrones de gasto y tendencias financieras</p>
        </div>

        <div class="analysis-summary">
            <div class="summary-card">
                <h3>Ingresos Promedio</h3>
                <p class="amount positive">$<?php echo number_format($avg_income, 2); ?></p>
                <p class="subtitle">Últimos 6 meses</p>
            </div>
            <div class="summary-card">
                <h3>Gastos Promedio</h3>
                <p class="amount negative">$<?php echo number_format($avg_expenses, 2); ?></p>
                <p class="subtitle">Últimos 6 meses</p>
            </div>
            <div class="summary-card">
                <h3>Ahorro Promedio</h3>
                <p class="amount <?php echo $avg_savings >= 0 ? 'positive' : 'negative'; ?>">
                    $<?php echo number_format($avg_savings, 2); ?>
                </p>
                <p class="subtitle">Últimos 6 meses</p>
            </div>
        </div>

        <div class="analysis-grid">
            <div class="analysis-card">
                <h3>Tendencia Mensual</h3>
                <div class="chart-container">
                    <canvas id="trendChart"></canvas>
                </div>
            </div>
            <div class="analysis-card">
                <h3>Distribución por Categoría</h3>
                <div class="chart-container">
                    <canvas id="categoryChart"></canvas>
                </div>
            </div>
            <div class="analysis-card full-width">
                <h3>Top 5 Categorías de Gasto</h3>
                <div class="category-list">
                    <?php foreach ($top_categories as $category => $amount): ?>
                        <div class="category-item">
                            <div class="category-info">
                                <h4><?php echo htmlspecialchars($category); ?></h4>
                                <p class="amount">$<?php echo number_format($amount, 2); ?></p>
                            </div>
                            <div class="category-bar">
                                <div class="bar-fill" style="width: <?php echo ($amount / max($top_categories)) * 100; ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Trend Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_keys($monthly_totals)); ?>,
                datasets: [{
                    label: 'Ingresos',
                    data: <?php echo json_encode(array_values($income_totals)); ?>,
                    borderColor: '#4caf50',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    fill: true
                }, {
                    label: 'Gastos',
                    data: <?php echo json_encode(array_values($expense_totals)); ?>,
                    borderColor: '#f44336',
                    backgroundColor: 'rgba(244, 67, 54, 0.1)',
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

        // Category Chart
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_keys($top_categories)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($top_categories)); ?>,
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
    </script>
</body>
</html> 
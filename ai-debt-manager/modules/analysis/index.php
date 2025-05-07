<?php
require_once __DIR__ . '/../../includes/auth_functions.php';
requireLogin();

// Get user's financial data
$stmt = $pdo->prepare("
    SELECT 
        d.amount as debt_amount,
        d.interest_rate,
        d.name as debt_name,
        t.amount as transaction_amount,
        t.description as transaction_description,
        t.transaction_date,
        c.name as category_name,
        c.type as category_type
    FROM debts d
    LEFT JOIN transactions t ON t.account_id IN (
        SELECT a.id 
        FROM accounts a 
        JOIN bank_connections bc ON a.bank_connection_id = bc.id 
        WHERE bc.user_id = d.user_id
    )
    LEFT JOIN categories c ON t.category_id = c.id
    WHERE d.user_id = ? AND d.status = 'active'
    ORDER BY t.transaction_date DESC
");
$stmt->execute([$_SESSION['user_id']]);
$transactions = $stmt->fetchAll();

// Calculate summary statistics
$total_debt = 0;
$total_interest = 0;
$total_transactions = 0;
$category_totals = [];

foreach ($transactions as $transaction) {
    if ($transaction['debt_amount']) {
        $total_debt += $transaction['debt_amount'];
        $total_interest += ($transaction['debt_amount'] * $transaction['interest_rate'] / 100);
    }
    
    if ($transaction['transaction_amount']) {
        $total_transactions += $transaction['transaction_amount'];
        
        if ($transaction['category_name']) {
            if (!isset($category_totals[$transaction['category_name']])) {
                $category_totals[$transaction['category_name']] = 0;
            }
            $category_totals[$transaction['category_name']] += $transaction['transaction_amount'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Análisis Financiero - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <main class="flex-grow-1">
        <div class="container py-4">
            <h1 class="mb-4">Análisis Financiero</h1>

            <!-- Resumen -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Deuda Total</h5>
                            <h2 class="text-danger">$<?php echo number_format($total_debt, 2); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Interés Total</h5>
                            <h2 class="text-warning">$<?php echo number_format($total_interest, 2); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Transacciones Totales</h5>
                            <h2 class="text-primary">$<?php echo number_format($total_transactions, 2); ?></h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráficos -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Distribución por Categoría</h5>
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Tendencia de Gastos</h5>
                            <canvas id="trendChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Últimas Transacciones -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Últimas Transacciones</h5>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Descripción</th>
                                    <th>Categoría</th>
                                    <th>Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $transaction): ?>
                                    <?php if ($transaction['transaction_amount']): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($transaction['transaction_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($transaction['transaction_description']); ?></td>
                                            <td>
                                                <?php if ($transaction['category_name']): ?>
                                                    <span class="badge bg-<?php echo $transaction['category_type'] === 'income' ? 'success' : 'danger'; ?>">
                                                        <?php echo htmlspecialchars($transaction['category_name']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Sin categoría</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="<?php echo $transaction['category_type'] === 'income' ? 'text-success' : 'text-danger'; ?>">
                                                $<?php echo number_format($transaction['transaction_amount'], 2); ?>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>

    <script>
        // Gráfico de Categorías
        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(categoryCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_keys($category_totals)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($category_totals)); ?>,
                    backgroundColor: [
                        '#FF6384',
                        '#36A2EB',
                        '#FFCE56',
                        '#4BC0C0',
                        '#9966FF'
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

        // Gráfico de Tendencia
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        const dates = <?php echo json_encode(array_map(function($t) { 
            return date('d/m/Y', strtotime($t['transaction_date'])); 
        }, array_filter($transactions, function($t) { 
            return $t['transaction_amount']; 
        }))); ?>;
        const amounts = <?php echo json_encode(array_map(function($t) { 
            return $t['transaction_amount']; 
        }, array_filter($transactions, function($t) { 
            return $t['transaction_amount']; 
        }))); ?>;

        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [{
                    label: 'Monto',
                    data: amounts,
                    borderColor: '#36A2EB',
                    tension: 0.1
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
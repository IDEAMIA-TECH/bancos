<?php
require_once __DIR__ . '/../../includes/auth_functions.php';
requireLogin();

// Get user's financial analysis data
$stmt = $pdo->prepare("
    SELECT 
        (SELECT SUM(d.amount) FROM debts d WHERE d.user_id = ? AND d.status = 'active') as total_debt,
        (SELECT SUM(p.amount) FROM payments p 
         JOIN debts d ON p.debt_id = d.id 
         WHERE d.user_id = ? 
         AND p.payment_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)) as monthly_payments,
        (SELECT AVG(d.interest_rate) FROM debts d WHERE d.user_id = ? AND d.status = 'active') as avg_interest_rate
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$analysis = $stmt->fetch();

// Get debt distribution by interest rate
$stmt = $pdo->prepare("
    SELECT 
        CASE 
            WHEN interest_rate <= 10 THEN 'Bajo (0-10%)'
            WHEN interest_rate <= 20 THEN 'Medio (11-20%)'
            WHEN interest_rate <= 30 THEN 'Alto (21-30%)'
            ELSE 'Muy Alto (>30%)'
        END as rate_category,
        COUNT(*) as count,
        SUM(amount) as total_amount
    FROM debts 
    WHERE user_id = ? AND status = 'active'
    GROUP BY rate_category
    ORDER BY MIN(interest_rate)
");
$stmt->execute([$_SESSION['user_id']]);
$interest_distribution = $stmt->fetchAll();

// Get payment history
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(payment_date, '%Y-%m') as month,
        SUM(amount) as total_payment
    FROM payments p
    JOIN debts d ON p.debt_id = d.id
    WHERE d.user_id = ?
    GROUP BY month
    ORDER BY month DESC
    LIMIT 12
");
$stmt->execute([$_SESSION['user_id']]);
$payment_history = $stmt->fetchAll();
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
                            <h2 class="text-danger">$<?php echo number_format($analysis['total_debt'], 2); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Pagos Mensuales</h5>
                            <h2 class="text-success">$<?php echo number_format($analysis['monthly_payments'], 2); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Tasa de Interés Promedio</h5>
                            <h2 class="text-primary"><?php echo number_format($analysis['avg_interest_rate'], 1); ?>%</h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Gráficos -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Distribución por Tasa de Interés</h5>
                            <div class="chart-container">
                                <canvas id="interestDistributionChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Historial de Pagos</h5>
                            <div class="chart-container">
                                <canvas id="paymentHistoryChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recomendaciones -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Recomendaciones</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Estrategia de Pago</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Prioriza el pago de deudas con tasas de interés más altas
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Considera consolidar deudas con tasas similares
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Optimización</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Mantén un fondo de emergencia equivalente a 3 meses de pagos
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Revisa periódicamente las tasas de interés del mercado
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>

    <script>
        // Distribución por Tasa de Interés
        const interestCtx = document.getElementById('interestDistributionChart').getContext('2d');
        new Chart(interestCtx, {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_column($interest_distribution, 'rate_category')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($interest_distribution, 'total_amount')); ?>,
                    backgroundColor: [
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

        // Historial de Pagos
        const paymentCtx = document.getElementById('paymentHistoryChart').getContext('2d');
        new Chart(paymentCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_column($payment_history, 'month')); ?>,
                datasets: [{
                    label: 'Pagos Mensuales',
                    data: <?php echo json_encode(array_column($payment_history, 'total_payment')); ?>,
                    borderColor: '#2196f3',
                    tension: 0.1
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
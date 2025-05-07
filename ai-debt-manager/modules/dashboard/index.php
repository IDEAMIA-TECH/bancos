<?php
require_once __DIR__ . '/../../includes/auth_functions.php';
requireLogin();

// Get user's financial summary
$stmt = $pdo->prepare("
    SELECT 
        (SELECT SUM(amount) FROM debts WHERE user_id = ? AND status = 'active') as total_debt,
        (SELECT COUNT(*) FROM debts WHERE user_id = ? AND status = 'active') as active_debts,
        (SELECT SUM(amount) FROM payments p 
         JOIN debts d ON p.debt_id = d.id 
         WHERE d.user_id = ? 
         AND p.payment_date >= DATE_SUB(CURRENT_DATE, INTERVAL 30 DAY)) as monthly_payments
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id'], $_SESSION['user_id']]);
$summary = $stmt->fetch();

// Get recent payments
$stmt = $pdo->prepare("
    SELECT p.*, d.name as debt_name
    FROM payments p
    JOIN debts d ON p.debt_id = d.id
    WHERE d.user_id = ?
    ORDER BY p.payment_date DESC
    LIMIT 10
");
$stmt->execute([$_SESSION['user_id']]);
$recent_payments = $stmt->fetchAll();

// Get debt distribution
$stmt = $pdo->prepare("
    SELECT name, amount, interest_rate
    FROM debts
    WHERE user_id = ? AND status = 'active'
    ORDER BY amount DESC
");
$stmt->execute([$_SESSION['user_id']]);
$debt_distribution = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#"><?php echo APP_NAME; ?></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="<?php echo APP_URL; ?>/logout">Cerrar sesión</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-4">
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Deuda Total</h5>
                        <h2 class="text-danger">$<?php echo number_format($summary['total_debt'], 2); ?></h2>
                        <p class="text-muted">Deudas activas: <?php echo $summary['active_debts']; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">Pagos del Mes</h5>
                        <h2 class="text-success">$<?php echo number_format($summary['monthly_payments'], 2); ?></h2>
                        <p class="text-muted">Últimos 30 días</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Distribución de Deudas</h5>
                        <div class="chart-container">
                            <canvas id="debtDistributionChart"></canvas>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Pagos Recientes</h5>
                        <div class="list-group">
                            <?php foreach ($recent_payments as $payment): ?>
                                <div class="list-group-item">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1"><?php echo htmlspecialchars($payment['debt_name']); ?></h6>
                                        <small><?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?></small>
                                    </div>
                                    <p class="mb-1">$<?php echo number_format($payment['amount'], 2); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Debt Distribution Chart
        const debtCtx = document.getElementById('debtDistributionChart').getContext('2d');
        new Chart(debtCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_column($debt_distribution, 'name')); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_column($debt_distribution, 'amount')); ?>,
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
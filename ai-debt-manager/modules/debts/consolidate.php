<?php
require_once __DIR__ . '/../../includes/auth_functions.php';
requireLogin();

// Get all active debts
$stmt = $pdo->prepare("
    SELECT 
        d.*,
        (SELECT SUM(amount) FROM payments WHERE debt_id = d.id) as total_paid
    FROM debts d
    WHERE d.user_id = ? AND d.status = 'active'
    ORDER BY d.interest_rate DESC
");
$stmt->execute([$_SESSION['user_id']]);
$debts = $stmt->fetchAll();

// Calculate remaining amounts
foreach ($debts as &$debt) {
    $debt['remaining_amount'] = $debt['amount'] - ($debt['total_paid'] ?? 0);
}
unset($debt);

// Calculate total debt and weighted average interest rate
$total_debt = 0;
$total_interest = 0;
foreach ($debts as $debt) {
    $total_debt += $debt['remaining_amount'];
    $total_interest += $debt['remaining_amount'] * $debt['interest_rate'];
}
$weighted_avg_rate = $total_debt > 0 ? ($total_interest / $total_debt) : 0;

// Calculate potential savings with different consolidation rates
$consolidation_rates = [
    'excellent' => 10,    // Excelente crédito
    'good' => 15,         // Buen crédito
    'fair' => 20,         // Crédito regular
    'poor' => 25          // Crédito bajo
];

$potential_savings = [];
foreach ($consolidation_rates as $credit => $rate) {
    if ($rate < $weighted_avg_rate) {
        $current_interest = $total_debt * ($weighted_avg_rate / 100);
        $new_interest = $total_debt * ($rate / 100);
        $potential_savings[$credit] = $current_interest - $new_interest;
    } else {
        $potential_savings[$credit] = 0;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consolidación de Deudas - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <main class="flex-grow-1">
        <div class="container py-4">
            <h1 class="mb-4">Consolidación de Deudas</h1>

            <!-- Resumen -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Deuda Total</h5>
                            <h2 class="text-danger">$<?php echo number_format($total_debt, 2); ?></h2>
                            <p class="text-muted">Deudas activas: <?php echo count($debts); ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Tasa Promedio Actual</h5>
                            <h2 class="text-warning"><?php echo number_format($weighted_avg_rate, 1); ?>%</h2>
                            <p class="text-muted">Tasa ponderada</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Potencial de Ahorro</h5>
                            <h2 class="text-success">$<?php echo number_format(max($potential_savings), 2); ?></h2>
                            <p class="text-muted">Mejor escenario</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Simulación de Consolidación -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Simulación de Consolidación</h5>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Perfil de Crédito</th>
                                    <th>Tasa Estimada</th>
                                    <th>Ahorro Anual</th>
                                    <th>Ahorro Total</th>
                                    <th>Pago Mensual</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($consolidation_rates as $credit => $rate): ?>
                                    <tr>
                                        <td>
                                            <?php
                                            $icon = match($credit) {
                                                'excellent' => 'fa-star text-warning',
                                                'good' => 'fa-thumbs-up text-success',
                                                'fair' => 'fa-hand text-warning',
                                                'poor' => 'fa-exclamation-triangle text-danger',
                                                default => 'fa-question text-muted'
                                            };
                                            ?>
                                            <i class="fas <?php echo $icon; ?> me-2"></i>
                                            <?php echo ucfirst($credit); ?>
                                        </td>
                                        <td><?php echo number_format($rate, 1); ?>%</td>
                                        <td>
                                            <?php if ($potential_savings[$credit] > 0): ?>
                                                <span class="text-success">
                                                    $<?php echo number_format($potential_savings[$credit], 2); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">No aplica</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($potential_savings[$credit] > 0): ?>
                                                <span class="text-success">
                                                    $<?php echo number_format($potential_savings[$credit] * 5, 2); ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">No aplica</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($rate < $weighted_avg_rate): ?>
                                                $<?php echo number_format(($total_debt * ($rate / 100)) / 12, 2); ?>
                                            <?php else: ?>
                                                <span class="text-muted">No aplica</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Deudas Actuales -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Deudas Actuales</h5>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Deuda</th>
                                    <th>Saldo</th>
                                    <th>Tasa</th>
                                    <th>Pago Mínimo</th>
                                    <th>Próximo Pago</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($debts as $debt): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($debt['name']); ?></td>
                                        <td>$<?php echo number_format($debt['remaining_amount'], 2); ?></td>
                                        <td><?php echo number_format($debt['interest_rate'], 1); ?>%</td>
                                        <td>$<?php echo number_format($debt['minimum_payment'], 2); ?></td>
                                        <td><?php echo date('d/m/Y', strtotime($debt['next_payment_date'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recomendaciones -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Recomendaciones</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Antes de Consolidar</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Revisa tu puntaje de crédito actual
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Compara ofertas de diferentes instituciones
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Después de Consolidar</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Realiza pagos puntuales para mejorar tu crédito
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Considera pagos adicionales cuando sea posible
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html> 
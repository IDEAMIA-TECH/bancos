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

// Calculate remaining amounts and monthly payments
$total_debt = 0;
$total_monthly_payment = 0;
foreach ($debts as &$debt) {
    $debt['remaining_amount'] = $debt['amount'] - ($debt['total_paid'] ?? 0);
    $total_debt += $debt['remaining_amount'];
    $total_monthly_payment += $debt['minimum_payment'];
}
unset($debt);

// Calculate debt payoff strategies
$strategies = [
    'avalanche' => [
        'name' => 'Método Avalancha',
        'description' => 'Paga primero las deudas con las tasas de interés más altas',
        'icon' => 'fa-mountain',
        'color' => 'primary'
    ],
    'snowball' => [
        'name' => 'Método Bola de Nieve',
        'description' => 'Paga primero las deudas más pequeñas para ganar impulso',
        'icon' => 'fa-snowflake',
        'color' => 'info'
    ],
    'hybrid' => [
        'name' => 'Método Híbrido',
        'description' => 'Combina ambos métodos para optimizar el pago',
        'icon' => 'fa-arrows-split-up-and-left',
        'color' => 'success'
    ]
];

// Sort debts for each strategy
$avalanche_debts = $debts;
usort($avalanche_debts, function($a, $b) {
    return $b['interest_rate'] <=> $a['interest_rate'];
});

$snowball_debts = $debts;
usort($snowball_debts, function($a, $b) {
    return $a['remaining_amount'] <=> $b['remaining_amount'];
});

$hybrid_debts = $debts;
usort($hybrid_debts, function($a, $b) {
    $score_a = ($a['interest_rate'] * 0.7) + (1 / $a['remaining_amount'] * 0.3);
    $score_b = ($b['interest_rate'] * 0.7) + (1 / $b['remaining_amount'] * 0.3);
    return $score_b <=> $score_a;
});
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estrategia de Deudas - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <main class="flex-grow-1">
        <div class="container py-4">
            <h1 class="mb-4">Estrategia de Deudas</h1>

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
                            <h5 class="card-title">Pago Mensual Total</h5>
                            <h2 class="text-warning">$<?php echo number_format($total_monthly_payment, 2); ?></h2>
                            <p class="text-muted">Pagos mínimos</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Tiempo Estimado</h5>
                            <h2 class="text-success"><?php echo ceil($total_debt / ($total_monthly_payment * 0.8)); ?> meses</h2>
                            <p class="text-muted">Con pagos adicionales</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Estrategias -->
            <div class="row mb-4">
                <?php foreach ($strategies as $key => $strategy): ?>
                    <div class="col-md-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <i class="fas <?php echo $strategy['icon']; ?> fa-2x text-<?php echo $strategy['color']; ?> me-3"></i>
                                    <h5 class="card-title mb-0"><?php echo $strategy['name']; ?></h5>
                                </div>
                                <p class="card-text"><?php echo $strategy['description']; ?></p>
                                <div class="mt-3">
                                    <h6>Orden de Pago Recomendado:</h6>
                                    <ol class="list-group list-group-numbered">
                                        <?php 
                                        $debts_to_show = ${$key . '_debts'};
                                        foreach (array_slice($debts_to_show, 0, 3) as $debt): 
                                        ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <?php echo htmlspecialchars($debt['name']); ?>
                                                <span class="badge bg-<?php echo $strategy['color']; ?> rounded-pill">
                                                    $<?php echo number_format($debt['remaining_amount'], 2); ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    </ol>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Recomendaciones -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Recomendaciones de Pago</h5>
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Estrategias de Pago</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Realiza pagos adicionales cuando sea posible
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Prioriza las deudas según tu estrategia elegida
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Consejos Adicionales</h6>
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Establece un presupuesto mensual para pagos
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check-circle text-success me-2"></i>
                                    Automatiza tus pagos para evitar retrasos
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Plan de Acción -->
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Plan de Acción</h5>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Deuda</th>
                                    <th>Saldo</th>
                                    <th>Pago Mínimo</th>
                                    <th>Pago Recomendado</th>
                                    <th>Prioridad</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($avalanche_debts as $index => $debt): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($debt['name']); ?></td>
                                        <td>$<?php echo number_format($debt['remaining_amount'], 2); ?></td>
                                        <td>$<?php echo number_format($debt['minimum_payment'], 2); ?></td>
                                        <td>
                                            <?php
                                            $extra_payment = $index === 0 ? $total_monthly_payment * 0.2 : 0;
                                            echo '$' . number_format($debt['minimum_payment'] + $extra_payment, 2);
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-<?php echo $index === 0 ? 'danger' : 'secondary'; ?>">
                                                <?php echo $index + 1; ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html> 
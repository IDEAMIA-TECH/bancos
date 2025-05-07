<?php
require_once __DIR__ . '/../../includes/auth_functions.php';
requireLogin();

// Get all active debts for the user
$stmt = $pdo->prepare("
    SELECT 
        d.*,
        COALESCE(SUM(dp.amount), 0) as total_paid,
        COUNT(dp.id) as payment_count,
        MAX(dp.payment_date) as last_payment_date
    FROM debts d
    LEFT JOIN debt_payments dp ON d.id = dp.debt_id AND dp.status = 'completed'
    WHERE d.user_id = ? AND d.status = 'active'
    GROUP BY d.id
    ORDER BY d.created_at DESC
");
$stmt->execute([$_SESSION['user_id']]);
$debts = $stmt->fetchAll();

// Calculate remaining amounts and progress
foreach ($debts as &$debt) {
    $debt['remaining_amount'] = $debt['amount'] - $debt['total_paid'];
    $debt['progress_percentage'] = ($debt['total_paid'] / $debt['amount']) * 100;
    
    // Calculate next payment date (30 days after last payment or start date if no payments)
    $last_date = $debt['last_payment_date'] ? strtotime($debt['last_payment_date']) : strtotime($debt['start_date']);
    $debt['next_payment_date'] = date('Y-m-d', strtotime('+30 days', $last_date));
}
unset($debt);

// Handle debt deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_debt'])) {
    $debt_id = (int)$_POST['debt_id'];
    $stmt = $pdo->prepare("UPDATE debts SET status = 'inactive' WHERE id = ? AND user_id = ?");
    $stmt->execute([$debt_id, $_SESSION['user_id']]);
    header('Location: /deudas/debts/list');
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Deudas - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <main class="flex-grow-1">
        <div class="container py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Mis Deudas</h1>
                <a href="/deudas/debts/add" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Agregar Deuda
                </a>
            </div>

            <?php if (empty($debts)): ?>
                <div class="alert alert-info">
                    No tienes deudas activas. ¡Excelente trabajo!
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($debts as $debt): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <h5 class="card-title"><?php echo htmlspecialchars($debt['name']); ?></h5>
                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span>Progreso</span>
                                            <span><?php echo number_format($debt['progress_percentage'], 1); ?>%</span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?php echo $debt['progress_percentage']; ?>%"
                                                 aria-valuenow="<?php echo $debt['progress_percentage']; ?>" 
                                                 aria-valuemin="0" aria-valuemax="100">
                                            </div>
                                        </div>
                                    </div>
                                    <ul class="list-unstyled">
                                        <li><strong>Monto Original:</strong> $<?php echo number_format($debt['amount'], 2); ?></li>
                                        <li><strong>Saldo Restante:</strong> $<?php echo number_format($debt['remaining_amount'], 2); ?></li>
                                        <li><strong>Tasa de Interés:</strong> <?php echo $debt['interest_rate']; ?>%</li>
                                        <li><strong>Próximo Pago:</strong> <?php echo date('d/m/Y', strtotime($debt['next_payment_date'])); ?></li>
                                        <li><strong>Método de Pago:</strong> <?php echo ucfirst($debt['payment_method']); ?></li>
                                    </ul>
                                    <div class="d-flex justify-content-between mt-3">
                                        <a href="/deudas/debts/plan?id=<?php echo $debt['id']; ?>" class="btn btn-primary btn-sm">
                                            <i class="fas fa-calendar-alt"></i> Plan de Pagos
                                        </a>
                                        <div class="btn-group">
                                            <a href="/deudas/debts/edit?id=<?php echo $debt['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de que deseas eliminar esta deuda?');">
                                                <input type="hidden" name="debt_id" value="<?php echo $debt['id']; ?>">
                                                <button type="submit" name="delete_debt" class="btn btn-outline-danger btn-sm">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html> 
<?php
require_once __DIR__ . '/../../includes/auth_functions.php';
requireLogin();

// Handle debt deletion
if (isset($_POST['delete_debt']) && isset($_POST['debt_id'])) {
    $stmt = $pdo->prepare("UPDATE debts SET status = 'inactive' WHERE id = ? AND user_id = ?");
    $stmt->execute([$_POST['debt_id'], $_SESSION['user_id']]);
    header('Location: ' . APP_URL . '/debts/list');
    exit;
}

// Get all active debts
$stmt = $pdo->prepare("
    SELECT 
        d.*,
        (SELECT SUM(amount) FROM payments WHERE debt_id = d.id) as total_paid,
        (SELECT COUNT(*) FROM payments WHERE debt_id = d.id) as payment_count
    FROM debts d
    WHERE d.user_id = ? AND d.status = 'active'
    ORDER BY d.next_payment_date ASC
");
$stmt->execute([$_SESSION['user_id']]);
$debts = $stmt->fetchAll();

// Calculate remaining amounts and progress
foreach ($debts as &$debt) {
    $debt['remaining_amount'] = $debt['amount'] - ($debt['total_paid'] ?? 0);
    $debt['progress'] = ($debt['total_paid'] ?? 0) / $debt['amount'] * 100;
}
unset($debt);
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
                <a href="<?php echo APP_URL; ?>/debts/add" class="btn btn-primary">
                    <i class="fas fa-plus me-2"></i>Agregar Deuda
                </a>
            </div>

            <?php if (empty($debts)): ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No tienes deudas activas. ¡Agrega una nueva deuda para comenzar!
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($debts as $debt): ?>
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-3">
                                        <h5 class="card-title mb-0"><?php echo htmlspecialchars($debt['name']); ?></h5>
                                        <div class="dropdown">
                                            <button class="btn btn-link text-dark p-0" data-bs-toggle="dropdown">
                                                <i class="fas fa-ellipsis-v"></i>
                                            </button>
                                            <ul class="dropdown-menu">
                                                <li>
                                                    <a class="dropdown-item" href="<?php echo APP_URL; ?>/debts/edit/<?php echo $debt['id']; ?>">
                                                        <i class="fas fa-edit me-2"></i>Editar
                                                    </a>
                                                </li>
                                                <li>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('¿Estás seguro de que deseas eliminar esta deuda?');">
                                                        <input type="hidden" name="debt_id" value="<?php echo $debt['id']; ?>">
                                                        <button type="submit" name="delete_debt" class="dropdown-item text-danger">
                                                            <i class="fas fa-trash me-2"></i>Eliminar
                                                        </button>
                                                    </form>
                                                </li>
                                            </ul>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span class="text-muted">Progreso</span>
                                            <span class="text-muted"><?php echo number_format($debt['progress'], 1); ?>%</span>
                                        </div>
                                        <div class="progress">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?php echo $debt['progress']; ?>%"
                                                 aria-valuenow="<?php echo $debt['progress']; ?>" 
                                                 aria-valuemin="0" aria-valuemax="100">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-6">
                                            <small class="text-muted d-block">Monto Original</small>
                                            <strong>$<?php echo number_format($debt['amount'], 2); ?></strong>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Saldo Restante</small>
                                            <strong>$<?php echo number_format($debt['remaining_amount'], 2); ?></strong>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Tasa de Interés</small>
                                            <strong><?php echo number_format($debt['interest_rate'], 1); ?>%</strong>
                                        </div>
                                        <div class="col-6">
                                            <small class="text-muted d-block">Pago Mínimo</small>
                                            <strong>$<?php echo number_format($debt['minimum_payment'], 2); ?></strong>
                                        </div>
                                    </div>

                                    <hr>

                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small class="text-muted d-block">Próximo Pago</small>
                                            <strong><?php echo date('d/m/Y', strtotime($debt['next_payment_date'])); ?></strong>
                                        </div>
                                        <a href="<?php echo APP_URL; ?>/debts/payments/<?php echo $debt['id']; ?>" 
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-history me-1"></i>
                                            Ver Pagos
                                        </a>
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
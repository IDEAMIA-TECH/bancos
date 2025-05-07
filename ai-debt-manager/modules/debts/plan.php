<?php
require_once __DIR__ . '/../../includes/auth_functions.php';
requireLogin();

// Get debt ID from URL
$debt_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Get debt details
$stmt = $pdo->prepare("
    SELECT 
        d.*,
        (SELECT SUM(amount) FROM payments WHERE debt_id = d.id) as total_paid
    FROM debts d
    WHERE d.id = ? AND d.user_id = ? AND d.status = 'active'
");
$stmt->execute([$debt_id, $_SESSION['user_id']]);
$debt = $stmt->fetch();

if (!$debt) {
    header('Location: ' . APP_URL . '/debts/list');
    exit;
}

// Calculate remaining amount and progress
$debt['remaining_amount'] = $debt['amount'] - ($debt['total_paid'] ?? 0);
$debt['progress'] = ($debt['total_paid'] ?? 0) / $debt['amount'] * 100;

// Get payment history
$stmt = $pdo->prepare("
    SELECT * FROM payments 
    WHERE debt_id = ? 
    ORDER BY payment_date DESC
");
$stmt->execute([$debt_id]);
$payments = $stmt->fetchAll();

// Calculate payment schedule
$monthly_interest = $debt['interest_rate'] / 12 / 100;
$remaining_months = ceil($debt['remaining_amount'] / $debt['minimum_payment']);
$payment_schedule = [];

$balance = $debt['remaining_amount'];
$payment_date = new DateTime($debt['next_payment_date']);

for ($i = 0; $i < $remaining_months && $balance > 0; $i++) {
    $interest = $balance * $monthly_interest;
    $principal = $debt['minimum_payment'] - $interest;
    $balance -= $principal;

    $payment_schedule[] = [
        'date' => $payment_date->format('Y-m-d'),
        'payment' => $debt['minimum_payment'],
        'interest' => $interest,
        'principal' => $principal,
        'balance' => max(0, $balance)
    ];

    $payment_date->modify('+1 month');
}

// Handle new payment submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount'])) {
    $stmt = $pdo->prepare("
        INSERT INTO payments (debt_id, amount, payment_date, notes)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([
        $debt_id,
        $_POST['amount'],
        $_POST['payment_date'] ?? date('Y-m-d'),
        $_POST['notes'] ?? null
    ]);

    // Redirect to avoid form resubmission
    header('Location: ' . $_SERVER['REQUEST_URI']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plan de Pagos - <?php echo htmlspecialchars($debt['name']); ?> - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <main class="flex-grow-1">
        <div class="container py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="mb-1"><?php echo htmlspecialchars($debt['name']); ?></h1>
                    <p class="text-muted mb-0">Plan de Pagos</p>
                </div>
                <a href="<?php echo APP_URL; ?>/debts/list" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Volver a Deudas
                </a>
            </div>

            <!-- Resumen -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Monto Original</h5>
                            <h2 class="text-primary">$<?php echo number_format($debt['amount'], 2); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Saldo Restante</h5>
                            <h2 class="text-danger">$<?php echo number_format($debt['remaining_amount'], 2); ?></h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Tasa de Interés</h5>
                            <h2 class="text-warning"><?php echo number_format($debt['interest_rate'], 1); ?>%</h2>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Pago Mínimo</h5>
                            <h2 class="text-success">$<?php echo number_format($debt['minimum_payment'], 2); ?></h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progreso -->
            <div class="card mb-4">
                <div class="card-body">
                    <h5 class="card-title">Progreso de Pago</h5>
                    <div class="progress mb-2" style="height: 25px;">
                        <div class="progress-bar bg-success" role="progressbar" 
                             style="width: <?php echo $debt['progress']; ?>%"
                             aria-valuenow="<?php echo $debt['progress']; ?>" 
                             aria-valuemin="0" 
                             aria-valuemax="100">
                            <?php echo number_format($debt['progress'], 1); ?>%
                        </div>
                    </div>
                    <div class="d-flex justify-content-between text-muted">
                        <small>Pagado: $<?php echo number_format($debt['total_paid'] ?? 0, 2); ?></small>
                        <small>Total: $<?php echo number_format($debt['amount'], 2); ?></small>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Calendario de Pagos -->
                <div class="col-md-8">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Calendario de Pagos</h5>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Fecha</th>
                                            <th>Pago</th>
                                            <th>Interés</th>
                                            <th>Principal</th>
                                            <th>Saldo</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($payment_schedule as $payment): ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y', strtotime($payment['date'])); ?></td>
                                                <td>$<?php echo number_format($payment['payment'], 2); ?></td>
                                                <td>$<?php echo number_format($payment['interest'], 2); ?></td>
                                                <td>$<?php echo number_format($payment['principal'], 2); ?></td>
                                                <td>$<?php echo number_format($payment['balance'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Registrar Pago -->
                <div class="col-md-4">
                    <div class="card mb-4">
                        <div class="card-body">
                            <h5 class="card-title">Registrar Pago</h5>
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Monto</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" class="form-control" name="amount" 
                                               step="0.01" min="<?php echo $debt['minimum_payment']; ?>" 
                                               required>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Fecha de Pago</label>
                                    <input type="date" class="form-control" name="payment_date" 
                                           value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Notas</label>
                                    <textarea class="form-control" name="notes" rows="2"></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-save me-2"></i>Registrar Pago
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Historial de Pagos -->
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title">Historial de Pagos</h5>
                            <?php if (empty($payments)): ?>
                                <p class="text-muted">No hay pagos registrados</p>
                            <?php else: ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($payments as $payment): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <h6 class="mb-1">$<?php echo number_format($payment['amount'], 2); ?></h6>
                                                    <small class="text-muted">
                                                        <?php echo date('d/m/Y', strtotime($payment['payment_date'])); ?>
                                                    </small>
                                                </div>
                                                <?php if ($payment['notes']): ?>
                                                    <span class="badge bg-light text-dark" data-bs-toggle="tooltip" 
                                                          title="<?php echo htmlspecialchars($payment['notes']); ?>">
                                                        <i class="fas fa-info-circle"></i>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>

    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    </script>
</body>
</html> 
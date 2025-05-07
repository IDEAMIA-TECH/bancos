<?php
require_once __DIR__ . '/../../includes/auth_functions.php';
requireLogin();

// Validate debt_id parameter
if (!isset($_GET['debt_id'])) {
    header('Location: list.php');
    exit();
}

$debt_id = $_GET['debt_id'];

// Get debt information
$stmt = $pdo->prepare("
    SELECT d.*, a.account_number, a.account_type, bc.institution_id
    FROM debts d
    JOIN accounts a ON d.account_id = a.id
    JOIN bank_connections bc ON a.bank_connection_id = bc.id
    WHERE d.id = ? AND d.user_id = ?
");
$stmt->execute([$debt_id, $_SESSION['user_id']]);
$debt = $stmt->fetch();

if (!$debt) {
    header('Location: list.php');
    exit();
}

// Get existing payment plan if any
$stmt = $pdo->prepare("
    SELECT * FROM payment_plans 
    WHERE debt_id = ? AND status = 'active'
    ORDER BY created_at DESC LIMIT 1
");
$stmt->execute([$debt_id]);
$current_plan = $stmt->fetch();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plan de Pagos - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/debts.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <div class="container">
        <div class="debt-info-header">
            <div class="bank-info">
                <h2><?php echo htmlspecialchars($debt['institution_id']); ?></h2>
                <p class="account-number">Cuenta: <?php echo htmlspecialchars($debt['account_number']); ?></p>
            </div>
            <div class="debt-summary">
                <div class="summary-item">
                    <span class="label">Deuda Actual</span>
                    <span class="value">$<?php echo number_format($debt['current_amount'], 2); ?></span>
                </div>
                <div class="summary-item">
                    <span class="label">Tasa de Interés</span>
                    <span class="value"><?php echo $debt['interest_rate']; ?>%</span>
                </div>
                <div class="summary-item">
                    <span class="label">Fecha de Vencimiento</span>
                    <span class="value"><?php echo date('d/m/Y', strtotime($debt['due_date'])); ?></span>
                </div>
            </div>
        </div>

        <?php if ($current_plan): ?>
            <div class="current-plan">
                <h3>Plan de Pagos Actual</h3>
                <div class="plan-details">
                    <div class="detail">
                        <span class="label">Pago Mensual</span>
                        <span class="value">$<?php echo number_format($current_plan['monthly_payment'], 2); ?></span>
                    </div>
                    <div class="detail">
                        <span class="label">Fecha de Inicio</span>
                        <span class="value"><?php echo date('d/m/Y', strtotime($current_plan['start_date'])); ?></span>
                    </div>
                    <div class="detail">
                        <span class="label">Fecha de Término</span>
                        <span class="value"><?php echo date('d/m/Y', strtotime($current_plan['end_date'])); ?></span>
                    </div>
                </div>
                <div class="plan-progress">
                    <canvas id="progressChart"></canvas>
                </div>
            </div>
        <?php else: ?>
            <div class="plan-options">
                <h3>Crear Nuevo Plan de Pagos</h3>
                <form id="planForm" class="plan-form">
                    <input type="hidden" name="debt_id" value="<?php echo $debt_id; ?>">
                    
                    <div class="form-group">
                        <label for="payment_method">Método de Pago</label>
                        <select name="payment_method" id="payment_method" required>
                            <option value="50_30_20">Método 50/30/20</option>
                            <option value="snowball">Método Snowball</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="monthly_payment">Pago Mensual</label>
                        <input type="number" name="monthly_payment" id="monthly_payment" 
                               min="<?php echo $debt['current_amount'] * 0.01; ?>" 
                               step="0.01" required>
                        <small>Mínimo: $<?php echo number_format($debt['current_amount'] * 0.01, 2); ?></small>
                    </div>

                    <div class="form-group">
                        <label for="start_date">Fecha de Inicio</label>
                        <input type="date" name="start_date" id="start_date" 
                               min="<?php echo date('Y-m-d'); ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="target_date">Fecha Objetivo</label>
                        <input type="date" name="target_date" id="target_date" required>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Generar Plan</button>
                        <button type="button" onclick="calculateProjection()" class="btn-secondary">
                            Calcular Proyección
                        </button>
                    </div>
                </form>

                <div id="projectionResults" class="projection-results" style="display: none;">
                    <h4>Proyección de Pagos</h4>
                    <div class="projection-chart">
                        <canvas id="projectionChart"></canvas>
                    </div>
                    <div class="projection-summary">
                        <div class="summary-item">
                            <span class="label">Total a Pagar</span>
                            <span class="value" id="totalPayment"></span>
                        </div>
                        <div class="summary-item">
                            <span class="label">Intereses Totales</span>
                            <span class="value" id="totalInterest"></span>
                        </div>
                        <div class="summary-item">
                            <span class="label">Tiempo Estimado</span>
                            <span class="value" id="estimatedTime"></span>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function calculateProjection() {
            const formData = new FormData(document.getElementById('planForm'));
            const data = Object.fromEntries(formData.entries());

            fetch('/api/debt/calculate.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(data)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    displayProjection(data.projection);
                } else {
                    alert('Error: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al calcular la proyección');
            });
        }

        function displayProjection(projection) {
            document.getElementById('projectionResults').style.display = 'block';
            document.getElementById('totalPayment').textContent = '$' + projection.total_payment.toFixed(2);
            document.getElementById('totalInterest').textContent = '$' + projection.total_interest.toFixed(2);
            document.getElementById('estimatedTime').textContent = projection.months + ' meses';

            // Create projection chart
            const ctx = document.getElementById('projectionChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: projection.months_labels,
                    datasets: [{
                        label: 'Saldo Restante',
                        data: projection.balance_projection,
                        borderColor: '#2196f3',
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
        }

        <?php if ($current_plan): ?>
        // Initialize progress chart for existing plan
        const ctx = document.getElementById('progressChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Pagado', 'Pendiente'],
                datasets: [{
                    data: [
                        <?php echo $debt['original_amount'] - $debt['current_amount']; ?>,
                        <?php echo $debt['current_amount']; ?>
                    ],
                    backgroundColor: ['#4caf50', '#f44336']
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
        <?php endif; ?>
    </script>
</body>
</html> 
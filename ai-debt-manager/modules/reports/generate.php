<?php
require_once __DIR__ . '/../../includes/auth_functions.php';
requireLogin();

// Get user's debts for report options
$stmt = $pdo->prepare("
    SELECT d.*, a.account_number, bc.institution_id
    FROM debts d
    JOIN accounts a ON d.account_id = a.id
    JOIN bank_connections bc ON a.bank_connection_id = bc.id
    WHERE d.user_id = ?
    ORDER BY d.current_amount DESC
");
$stmt->execute([$_SESSION['user_id']]);
$debts = $stmt->fetchAll();

// Get date range for report
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Generar Reportes - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/reports.css">
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <div class="container">
        <div class="reports-header">
            <h1>Generar Reportes</h1>
            <p>Exporta tu información financiera en diferentes formatos</p>
        </div>

        <div class="reports-grid">
            <div class="report-card">
                <h3>Reporte de Deudas</h3>
                <form action="export.php" method="POST" class="report-form">
                    <input type="hidden" name="report_type" value="debts">
                    
                    <div class="form-group">
                        <label for="debt_id">Seleccionar Deuda</label>
                        <select name="debt_id" id="debt_id" required>
                            <option value="all">Todas las Deudas</option>
                            <?php foreach ($debts as $debt): ?>
                                <option value="<?php echo $debt['id']; ?>">
                                    <?php echo htmlspecialchars($debt['institution_id']); ?> - 
                                    $<?php echo number_format($debt['current_amount'], 2); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="start_date">Fecha Inicio</label>
                        <input type="date" name="start_date" id="start_date" 
                               value="<?php echo $start_date; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="end_date">Fecha Fin</label>
                        <input type="date" name="end_date" id="end_date" 
                               value="<?php echo $end_date; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="format">Formato</label>
                        <select name="format" id="format" required>
                            <option value="pdf">PDF</option>
                            <option value="excel">Excel</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Generar Reporte</button>
                    </div>
                </form>
            </div>

            <div class="report-card">
                <h3>Reporte de Transacciones</h3>
                <form action="export.php" method="POST" class="report-form">
                    <input type="hidden" name="report_type" value="transactions">
                    
                    <div class="form-group">
                        <label for="account_id">Seleccionar Cuenta</label>
                        <select name="account_id" id="account_id" required>
                            <option value="all">Todas las Cuentas</option>
                            <?php foreach ($debts as $debt): ?>
                                <option value="<?php echo $debt['account_id']; ?>">
                                    <?php echo htmlspecialchars($debt['institution_id']); ?> - 
                                    <?php echo htmlspecialchars($debt['account_number']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="trans_start_date">Fecha Inicio</label>
                        <input type="date" name="start_date" id="trans_start_date" 
                               value="<?php echo $start_date; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="trans_end_date">Fecha Fin</label>
                        <input type="date" name="end_date" id="trans_end_date" 
                               value="<?php echo $end_date; ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="trans_format">Formato</label>
                        <select name="format" id="trans_format" required>
                            <option value="pdf">PDF</option>
                            <option value="excel">Excel</option>
                        </select>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn-primary">Generar Reporte</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html> 
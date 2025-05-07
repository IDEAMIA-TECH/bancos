<?php
require_once __DIR__ . '/../../includes/auth_functions.php';
requireLogin();

// Get uncategorized transactions
$stmt = $pdo->prepare("
    SELECT 
        t.*,
        a.account_number,
        bc.institution_name
    FROM transactions t
    JOIN accounts a ON t.account_id = a.id
    JOIN bank_connections bc ON a.bank_connection_id = bc.id
    WHERE bc.user_id = ? 
    AND t.category_id IS NULL
    ORDER BY t.transaction_date DESC
    LIMIT 50
");
$stmt->execute([$_SESSION['user_id']]);
$transactions = $stmt->fetchAll();

// Get available categories
$stmt = $pdo->prepare("
    SELECT * FROM categories 
    WHERE user_id = ? OR is_default = 1
    ORDER BY name
");
$stmt->execute([$_SESSION['user_id']]);
$categories = $stmt->fetchAll();

// Handle category updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['transaction_id'], $_POST['category_id'])) {
    $stmt = $pdo->prepare("
        UPDATE transactions 
        SET category_id = ? 
        WHERE id = ? AND account_id IN (
            SELECT a.id 
            FROM accounts a 
            JOIN bank_connections bc ON a.bank_connection_id = bc.id 
            WHERE bc.user_id = ?
        )
    ");
    $stmt->execute([$_POST['category_id'], $_POST['transaction_id'], $_SESSION['user_id']]);
    
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
    <title>Categorización de Transacciones - <?php echo APP_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body class="d-flex flex-column min-vh-100 bg-light">
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <main class="flex-grow-1">
        <div class="container py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>Categorización de Transacciones</h1>
                <div>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#aiCategorizeModal">
                        <i class="fas fa-robot me-2"></i>Categorizar con IA
                    </button>
                </div>
            </div>

            <?php if (empty($transactions)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    ¡Todas las transacciones están categorizadas!
                </div>
            <?php else: ?>
                <!-- Filtros -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Institución</label>
                                <select class="form-select" name="institution">
                                    <option value="">Todas</option>
                                    <?php
                                    $institutions = array_unique(array_column($transactions, 'institution_name'));
                                    foreach ($institutions as $institution):
                                    ?>
                                        <option value="<?php echo htmlspecialchars($institution); ?>">
                                            <?php echo htmlspecialchars($institution); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Fecha</label>
                                <input type="date" class="form-control" name="date">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Monto</label>
                                <input type="number" class="form-control" name="amount" placeholder="Monto">
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Lista de Transacciones -->
                <div class="card">
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Fecha</th>
                                        <th>Descripción</th>
                                        <th>Institución</th>
                                        <th>Monto</th>
                                        <th>Categoría</th>
                                        <th>Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transactions as $transaction): ?>
                                        <tr>
                                            <td><?php echo date('d/m/Y', strtotime($transaction['transaction_date'])); ?></td>
                                            <td><?php echo htmlspecialchars($transaction['description']); ?></td>
                                            <td><?php echo htmlspecialchars($transaction['institution_name']); ?></td>
                                            <td class="<?php echo $transaction['amount'] < 0 ? 'text-danger' : 'text-success'; ?>">
                                                $<?php echo number_format(abs($transaction['amount']), 2); ?>
                                            </td>
                                            <td>
                                                <form method="POST" class="d-flex gap-2">
                                                    <input type="hidden" name="transaction_id" value="<?php echo $transaction['id']; ?>">
                                                    <select name="category_id" class="form-select form-select-sm" required>
                                                        <option value="">Seleccionar...</option>
                                                        <?php foreach ($categories as $category): ?>
                                                            <option value="<?php echo $category['id']; ?>">
                                                                <?php echo htmlspecialchars($category['name']); ?>
                                                            </option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="submit" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-save"></i>
                                                    </button>
                                                </form>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-primary" 
                                                        data-bs-toggle="tooltip" 
                                                        title="Sugerir Categoría"
                                                        onclick="suggestCategory(<?php echo $transaction['id']; ?>)">
                                                    <i class="fas fa-lightbulb"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Modal de Categorización con IA -->
    <div class="modal fade" id="aiCategorizeModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Categorización con IA</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>La IA analizará las transacciones sin categorizar y sugerirá las categorías más apropiadas basándose en:</p>
                    <ul>
                        <li>Descripción de la transacción</li>
                        <li>Monto y frecuencia</li>
                        <li>Patrones históricos</li>
                    </ul>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Este proceso puede tomar unos minutos dependiendo de la cantidad de transacciones.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="button" class="btn btn-primary" onclick="startAICategorization()">
                        <i class="fas fa-robot me-2"></i>Iniciar Categorización
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php include __DIR__ . '/../../includes/footer.php'; ?>

    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Suggest category for a single transaction
        function suggestCategory(transactionId) {
            // TODO: Implement AI suggestion for single transaction
            alert('Función en desarrollo');
        }

        // Start AI categorization for all transactions
        function startAICategorization() {
            // TODO: Implement bulk AI categorization
            alert('Función en desarrollo');
        }
    </script>
</body>
</html> 
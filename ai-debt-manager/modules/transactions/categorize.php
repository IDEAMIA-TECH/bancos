<?php
require_once __DIR__ . '/../../includes/auth_functions.php';
requireLogin();

// Get uncategorized transactions
$stmt = $pdo->prepare("
    SELECT t.*, a.account_number, bc.institution_id
    FROM transactions t
    JOIN accounts a ON t.account_id = a.id
    JOIN bank_connections bc ON a.bank_connection_id = bc.id
    WHERE bc.user_id = ? AND t.category_id IS NULL
    ORDER BY t.transaction_date DESC
");
$stmt->execute([$_SESSION['user_id']]);
$transactions = $stmt->fetchAll();

// Get categories
$stmt = $pdo->prepare("
    SELECT * FROM categories 
    WHERE type = 'expense' 
    ORDER BY name
");
$stmt->execute();
$categories = $stmt->fetchAll();

// Get common patterns for auto-categorization
$patterns = [
    'supermercado' => ['walmart', 'soriana', 'chedraui', 'heb', 'costco'],
    'restaurantes' => ['restaurante', 'restaurant', 'cafe', 'starbucks', 'vips'],
    'transporte' => ['uber', 'taxi', 'metro', 'gasolina', 'pemex'],
    'servicios' => ['cf', 'telcel', 'izzi', 'totalplay', 'sky'],
    'entretenimiento' => ['netflix', 'spotify', 'cinemex', 'cinepolis'],
    'ropa' => ['zara', 'h&m', 'forever 21', 'liverpool', 'palacio'],
    'salud' => ['farmacia', 'hospital', 'doctor', 'medico'],
    'educacion' => ['escuela', 'universidad', 'cursos', 'libros'],
    'vivienda' => ['renta', 'hipoteca', 'predial', 'agua', 'luz'],
    'otros' => []
];

// Function to suggest category based on description
function suggestCategory($description, $patterns) {
    $description = strtolower($description);
    foreach ($patterns as $category => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($description, $keyword) !== false) {
                return $category;
            }
        }
    }
    return 'otros';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Categorizar Transacciones - <?php echo APP_NAME; ?></title>
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/dashboard.css">
    <link rel="stylesheet" href="<?php echo APP_URL; ?>/assets/css/transactions.css">
</head>
<body>
    <?php include __DIR__ . '/../../includes/header.php'; ?>

    <div class="container">
        <div class="categorize-header">
            <h1>Categorizar Transacciones</h1>
            <p>Ayuda a mejorar el análisis de tus gastos</p>
        </div>

        <?php if (empty($transactions)): ?>
            <div class="no-transactions">
                <p>No hay transacciones pendientes de categorizar</p>
            </div>
        <?php else: ?>
            <div class="transactions-grid">
                <?php foreach ($transactions as $transaction): ?>
                    <div class="transaction-card" data-id="<?php echo $transaction['id']; ?>">
                        <div class="transaction-info">
                            <span class="institution"><?php echo htmlspecialchars($transaction['institution_id']); ?></span>
                            <span class="account"><?php echo htmlspecialchars($transaction['account_number']); ?></span>
                        </div>
                        <div class="transaction-details">
                            <p class="description"><?php echo htmlspecialchars($transaction['description']); ?></p>
                            <span class="date"><?php echo date('d/m/Y', strtotime($transaction['transaction_date'])); ?></span>
                        </div>
                        <div class="transaction-amount">
                            $<?php echo number_format(abs($transaction['amount']), 2); ?>
                        </div>
                        <div class="categorization">
                            <select class="category-select" onchange="updateCategory(<?php echo $transaction['id']; ?>, this.value)">
                                <option value="">Seleccionar categoría</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo $category['id']; ?>"
                                            <?php echo suggestCategory($transaction['description'], $patterns) === $category['name'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <script>
        function updateCategory(transactionId, categoryId) {
            fetch('/api/transactions/categorize.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    transaction_id: transactionId,
                    category_id: categoryId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove transaction card after successful categorization
                    const card = document.querySelector(`[data-id="${transactionId}"]`);
                    if (card) {
                        card.remove();
                    }
                    
                    // Check if there are any transactions left
                    const remainingCards = document.querySelectorAll('.transaction-card');
                    if (remainingCards.length === 0) {
                        location.reload();
                    }
                } else {
                    alert('Error al categorizar la transacción: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Error al categorizar la transacción');
            });
        }
    </script>
</body>
</html> 
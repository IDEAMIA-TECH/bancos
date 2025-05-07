<?php
require_once __DIR__ . '/../../../includes/auth_functions.php';
requireLogin();

header('Content-Type: application/json');

// Verify request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Get and validate input data
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['debt_id']) || !isset($data['monthly_payment']) || 
    !isset($data['payment_method']) || !isset($data['start_date']) || 
    !isset($data['target_date'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
    exit;
}

try {
    // Verify debt belongs to user
    $stmt = $pdo->prepare("
        SELECT d.*, a.account_type
        FROM debts d
        JOIN accounts a ON d.account_id = a.id
        WHERE d.id = ? AND d.user_id = ?
    ");
    $stmt->execute([$data['debt_id'], $_SESSION['user_id']]);
    $debt = $stmt->fetch();

    if (!$debt) {
        throw new Exception('Deuda no encontrada o no autorizada');
    }

    // Calculate payment projection
    $start_date = new DateTime($data['start_date']);
    $target_date = new DateTime($data['target_date']);
    $current_balance = $debt['current_amount'];
    $monthly_payment = floatval($data['monthly_payment']);
    $interest_rate = $debt['interest_rate'] / 100 / 12; // Monthly interest rate
    $months = $start_date->diff($target_date)->m + ($start_date->diff($target_date)->y * 12);

    $balance_projection = [];
    $months_labels = [];
    $total_payment = 0;
    $total_interest = 0;
    $current_date = clone $start_date;

    for ($i = 0; $i < $months && $current_balance > 0; $i++) {
        // Calculate interest for the month
        $monthly_interest = $current_balance * $interest_rate;
        $total_interest += $monthly_interest;

        // Calculate principal payment
        $principal_payment = $monthly_payment - $monthly_interest;
        if ($principal_payment > $current_balance) {
            $principal_payment = $current_balance;
            $monthly_payment = $principal_payment + $monthly_interest;
        }

        // Update balance
        $current_balance -= $principal_payment;
        $total_payment += $monthly_payment;

        // Store projection data
        $balance_projection[] = $current_balance;
        $months_labels[] = $current_date->format('M Y');
        $current_date->modify('+1 month');
    }

    // Prepare response
    $projection = [
        'balance_projection' => $balance_projection,
        'months_labels' => $months_labels,
        'total_payment' => $total_payment,
        'total_interest' => $total_interest,
        'months' => count($balance_projection)
    ];

    echo json_encode([
        'success' => true,
        'projection' => $projection
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error al calcular la proyección: ' . $e->getMessage()
    ]);
} 
<?php
require_once __DIR__ . '/db_functions.php';

// Expense Categories
define('EXPENSE_CATEGORIES', [
    'food' => ['keywords' => ['restaurant', 'supermarket', 'grocery', 'food', 'comida', 'restaurante', 'supermercado']],
    'rent' => ['keywords' => ['rent', 'lease', 'housing', 'renta', 'alquiler', 'vivienda']],
    'utilities' => ['keywords' => ['electricity', 'water', 'gas', 'internet', 'phone', 'luz', 'agua', 'gas', 'internet', 'teléfono']],
    'transportation' => ['keywords' => ['transport', 'bus', 'metro', 'taxi', 'uber', 'gasoline', 'gasolina', 'transporte']],
    'entertainment' => ['keywords' => ['movie', 'theater', 'concert', 'cinema', 'película', 'teatro', 'concierto', 'cine']],
    'shopping' => ['keywords' => ['store', 'shop', 'mall', 'tienda', 'centro comercial']],
    'health' => ['keywords' => ['doctor', 'hospital', 'pharmacy', 'medical', 'médico', 'hospital', 'farmacia']],
    'education' => ['keywords' => ['school', 'university', 'course', 'book', 'escuela', 'universidad', 'curso', 'libro']],
    'personal_care' => ['keywords' => ['salon', 'spa', 'beauty', 'salón', 'belleza']],
    'other' => ['keywords' => []]
]);

/**
 * Classify a transaction into an expense category
 * @param string $description Transaction description
 * @param float $amount Transaction amount
 * @return string Category name
 */
function classifyExpense($description, $amount) {
    global $pdo;
    
    // Convert to lowercase for case-insensitive matching
    $description = strtolower($description);
    
    // Check for exact matches in transaction history
    $stmt = $pdo->prepare("
        SELECT category_id, COUNT(*) as count
        FROM transactions
        WHERE LOWER(description) LIKE ?
        AND category_id IS NOT NULL
        GROUP BY category_id
        ORDER BY count DESC
        LIMIT 1
    ");
    
    $stmt->execute(['%' . $description . '%']);
    $result = $stmt->fetch();
    
    if ($result) {
        return $result['category_id'];
    }
    
    // Check for keyword matches
    foreach (EXPENSE_CATEGORIES as $category => $data) {
        foreach ($data['keywords'] as $keyword) {
            if (strpos($description, strtolower($keyword)) !== false) {
                return $category;
            }
        }
    }
    
    // Use amount-based heuristics for unclassified transactions
    if ($amount > 1000) {
        return 'rent';
    } elseif ($amount > 500) {
        return 'utilities';
    } elseif ($amount > 200) {
        return 'shopping';
    } elseif ($amount > 100) {
        return 'food';
    }
    
    return 'other';
}

/**
 * Predict monthly liquidity based on historical data
 * @param int $user_id User ID
 * @param int $months Number of months to predict
 * @return array Predicted monthly balances
 */
function predictMonthlyLiquidity($user_id, $months = 3) {
    global $pdo;
    
    // Get historical income and expenses
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(transaction_date, '%Y-%m') as month,
            SUM(CASE WHEN amount > 0 THEN amount ELSE 0 END) as income,
            SUM(CASE WHEN amount < 0 THEN ABS(amount) ELSE 0 END) as expenses
        FROM transactions t
        JOIN accounts a ON t.account_id = a.id
        JOIN bank_connections bc ON a.bank_connection_id = bc.id
        WHERE bc.user_id = ?
        AND transaction_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
        GROUP BY DATE_FORMAT(transaction_date, '%Y-%m')
        ORDER BY month DESC
    ");
    
    $stmt->execute([$user_id]);
    $history = $stmt->fetchAll();
    
    if (empty($history)) {
        return [];
    }
    
    // Calculate average monthly income and expenses
    $total_income = 0;
    $total_expenses = 0;
    $count = 0;
    
    foreach ($history as $month) {
        $total_income += $month['income'];
        $total_expenses += $month['expenses'];
        $count++;
    }
    
    $avg_income = $total_income / $count;
    $avg_expenses = $total_expenses / $count;
    
    // Get current balance
    $stmt = $pdo->prepare("
        SELECT SUM(balance) as current_balance
        FROM accounts a
        JOIN bank_connections bc ON a.bank_connection_id = bc.id
        WHERE bc.user_id = ?
    ");
    
    $stmt->execute([$user_id]);
    $current_balance = $stmt->fetch()['current_balance'];
    
    // Generate predictions
    $predictions = [];
    $balance = $current_balance;
    
    for ($i = 0; $i < $months; $i++) {
        $month = date('Y-m', strtotime("+$i months"));
        
        // Add some randomness to predictions (±10%)
        $predicted_income = $avg_income * (1 + (rand(-10, 10) / 100));
        $predicted_expenses = $avg_expenses * (1 + (rand(-10, 10) / 100));
        
        $balance += $predicted_income - $predicted_expenses;
        
        $predictions[$month] = [
            'income' => $predicted_income,
            'expenses' => $predicted_expenses,
            'balance' => $balance
        ];
    }
    
    return $predictions;
}

/**
 * Generate payment scenarios based on user inputs
 * @param float $total_debt Total debt amount
 * @param float $interest_rate Annual interest rate
 * @param int $desired_term Desired payment term in months
 * @param string $payment_method Payment method (snowball, avalanche, custom)
 * @param array $debts Array of individual debts with amounts and rates
 * @return array Payment scenarios
 */
function generatePaymentScenarios($total_debt, $interest_rate, $desired_term, $payment_method, $debts = []) {
    $scenarios = [];
    
    // Snowball Method (pay smallest debts first)
    if ($payment_method === 'snowball') {
        usort($debts, function($a, $b) {
            return $a['amount'] <=> $b['amount'];
        });
        
        $remaining_debt = $total_debt;
        $monthly_payment = $total_debt * ($interest_rate / 12) / (1 - pow(1 + $interest_rate / 12, -$desired_term));
        
        foreach ($debts as $debt) {
            $months_to_pay = ceil($debt['amount'] / $monthly_payment);
            $total_interest = ($monthly_payment * $months_to_pay) - $debt['amount'];
            
            $scenarios['snowball'][] = [
                'debt_amount' => $debt['amount'],
                'monthly_payment' => $monthly_payment,
                'months_to_pay' => $months_to_pay,
                'total_interest' => $total_interest,
                'completion_date' => date('Y-m-d', strtotime("+$months_to_pay months"))
            ];
            
            $remaining_debt -= $debt['amount'];
        }
    }
    
    // Avalanche Method (pay highest interest debts first)
    if ($payment_method === 'avalanche') {
        usort($debts, function($a, $b) {
            return $b['interest_rate'] <=> $a['interest_rate'];
        });
        
        $remaining_debt = $total_debt;
        $monthly_payment = $total_debt * ($interest_rate / 12) / (1 - pow(1 + $interest_rate / 12, -$desired_term));
        
        foreach ($debts as $debt) {
            $months_to_pay = ceil($debt['amount'] / $monthly_payment);
            $total_interest = ($monthly_payment * $months_to_pay) - $debt['amount'];
            
            $scenarios['avalanche'][] = [
                'debt_amount' => $debt['amount'],
                'monthly_payment' => $monthly_payment,
                'months_to_pay' => $months_to_pay,
                'total_interest' => $total_interest,
                'completion_date' => date('Y-m-d', strtotime("+$months_to_pay months"))
            ];
            
            $remaining_debt -= $debt['amount'];
        }
    }
    
    // Custom Method (50/30/20 rule)
    if ($payment_method === 'custom') {
        $monthly_income = $total_debt / ($desired_term * 0.2); // 20% of income for debt
        $monthly_payment = $monthly_income * 0.2;
        
        $scenarios['custom'] = [
            'monthly_income' => $monthly_income,
            'monthly_payment' => $monthly_payment,
            'months_to_pay' => $desired_term,
            'total_interest' => ($monthly_payment * $desired_term) - $total_debt,
            'completion_date' => date('Y-m-d', strtotime("+$desired_term months"))
        ];
    }
    
    return $scenarios;
} 
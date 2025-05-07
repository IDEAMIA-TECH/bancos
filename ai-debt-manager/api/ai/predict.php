<?php
require_once __DIR__ . '/../../../includes/auth_functions.php';
require_once __DIR__ . '/../../../includes/ai_models.php';

// Verify user is logged in
requireLogin();

// Handle POST request for predictions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!$data) {
            throw new Exception('Invalid request data');
        }
        
        $type = $data['type'] ?? null;
        $response = [];
        
        switch ($type) {
            case 'liquidity':
                // Predict monthly liquidity
                $months = $data['months'] ?? 3;
                $response = predictMonthlyLiquidity($_SESSION['user_id'], $months);
                break;
                
            case 'payment':
                // Generate payment scenarios
                if (!isset($data['total_debt']) || !isset($data['interest_rate']) || 
                    !isset($data['desired_term']) || !isset($data['payment_method'])) {
                    throw new Exception('Missing required parameters for payment scenario');
                }
                
                $response = generatePaymentScenarios(
                    $data['total_debt'],
                    $data['interest_rate'],
                    $data['desired_term'],
                    $data['payment_method'],
                    $data['debts'] ?? []
                );
                break;
                
            case 'classify':
                // Classify a transaction
                if (!isset($data['description']) || !isset($data['amount'])) {
                    throw new Exception('Missing required parameters for classification');
                }
                
                $response = [
                    'category' => classifyExpense($data['description'], $data['amount'])
                ];
                break;
                
            default:
                throw new Exception('Invalid prediction type');
        }
        
        // Return success response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'data' => $response
        ]);
        
    } catch (Exception $e) {
        // Log error
        error_log("AI prediction error: " . $e->getMessage());
        
        // Return error response
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

// Return error for non-POST requests
header('Content-Type: application/json');
http_response_code(405);
echo json_encode([
    'success' => false,
    'message' => 'Method not allowed'
]); 
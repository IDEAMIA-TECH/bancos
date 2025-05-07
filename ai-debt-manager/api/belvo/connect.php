<?php
require_once __DIR__ . '/../../../config/belvo.php';
require_once __DIR__ . '/../../../includes/auth_functions.php';
require_once __DIR__ . '/../../../includes/db_functions.php';

// Verify user is logged in
requireLogin();

// Handle POST request for new connection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get POST data
        $institution = $_POST['institution'] ?? null;
        $username = $_POST['username'] ?? null;
        $password = $_POST['password'] ?? null;
        
        if (!$institution || !$username || !$password) {
            throw new Exception('Missing required fields');
        }
        
        // Create Belvo link
        $belvoResponse = createBelvoLink($institution, $username, $password, $_SESSION['user_id']);
        
        if (!isset($belvoResponse['id'])) {
            throw new Exception('Failed to create Belvo link');
        }
        
        // Store connection in database
        $stmt = $pdo->prepare("
            INSERT INTO bank_connections (
                user_id, 
                institution_id, 
                belvo_link_id, 
                status, 
                created_at
            ) VALUES (?, ?, ?, 'active', NOW())
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            $institution,
            $belvoResponse['id']
        ]);
        
        $connection_id = $pdo->lastInsertId();
        
        // Get accounts from Belvo
        $accounts = getBelvoAccounts($belvoResponse['id']);
        
        // Store accounts in database
        foreach ($accounts as $account) {
            $stmt = $pdo->prepare("
                INSERT INTO accounts (
                    bank_connection_id,
                    account_number,
                    account_type,
                    balance,
                    currency,
                    last_updated
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $connection_id,
                $account['number'],
                $account['type'],
                $account['balance']['current'],
                $account['balance']['currency']
            ]);
        }
        
        // Get transactions from last 30 days
        $date_from = date('Y-m-d', strtotime('-30 days'));
        $transactions = getBelvoTransactions($belvoResponse['id'], $date_from);
        
        // Store transactions in database
        foreach ($transactions as $transaction) {
            $stmt = $pdo->prepare("
                INSERT INTO transactions (
                    account_id,
                    amount,
                    description,
                    category_id,
                    transaction_date,
                    created_at
                ) VALUES (?, ?, ?, NULL, ?, NOW())
            ");
            
            $stmt->execute([
                $connection_id,
                $transaction['amount'],
                $transaction['description'],
                $transaction['date']
            ]);
        }
        
        // Log the action
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (
                user_id, 
                action, 
                details, 
                ip_address
            ) VALUES (?, 'bank_connection_create', ?, ?)
        ");
        
        $stmt->execute([
            $_SESSION['user_id'],
            json_encode(['connection_id' => $connection_id, 'institution' => $institution]),
            $_SERVER['REMOTE_ADDR']
        ]);
        
        // Return success response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Bank connection created successfully',
            'connection_id' => $connection_id
        ]);
        
    } catch (Exception $e) {
        // Log error
        error_log("Belvo connection error: " . $e->getMessage());
        
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

// Handle GET request for available institutions
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        // Get list of available institutions from Belvo
        $institutions = belvoApiRequest('/api/institutions/');
        
        // Return success response
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'institutions' => $institutions
        ]);
        
    } catch (Exception $e) {
        // Log error
        error_log("Belvo institutions error: " . $e->getMessage());
        
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
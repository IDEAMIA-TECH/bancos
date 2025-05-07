<?php
require_once __DIR__ . '/../../../config/belvo.php';
require_once __DIR__ . '/../../../includes/db_functions.php';

// Get webhook payload
$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_X_BELVO_SIGNATURE'] ?? '';

// Verify webhook signature
if (!verifyBelvoWebhook($payload, $signature)) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid signature']);
    exit;
}

// Parse webhook payload
$data = json_decode($payload, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid payload']);
    exit;
}

try {
    // Start transaction
    $pdo->beginTransaction();
    
    // Handle different webhook events
    switch ($data['event']) {
        case 'link.updated':
            // Update connection status
            $stmt = $pdo->prepare("
                UPDATE bank_connections 
                SET status = ?, 
                    last_sync = NOW() 
                WHERE belvo_link_id = ?
            ");
            $stmt->execute([$data['status'], $data['link_id']]);
            break;
            
        case 'account.updated':
            // Update account balance
            $stmt = $pdo->prepare("
                UPDATE accounts 
                SET balance = ?, 
                    currency = ?, 
                    last_updated = NOW() 
                WHERE account_number = ? 
                AND bank_connection_id = (
                    SELECT id 
                    FROM bank_connections 
                    WHERE belvo_link_id = ?
                )
            ");
            $stmt->execute([
                $data['balance']['current'],
                $data['balance']['currency'],
                $data['account']['number'],
                $data['link_id']
            ]);
            break;
            
        case 'transaction.created':
            // Add new transaction
            $stmt = $pdo->prepare("
                INSERT INTO transactions (
                    account_id,
                    amount,
                    description,
                    category_id,
                    transaction_date,
                    created_at
                ) VALUES (
                    (SELECT id FROM accounts WHERE account_number = ? AND bank_connection_id = (
                        SELECT id FROM bank_connections WHERE belvo_link_id = ?
                    )),
                    ?,
                    ?,
                    NULL,
                    ?,
                    NOW()
                )
            ");
            $stmt->execute([
                $data['account']['number'],
                $data['link_id'],
                $data['amount'],
                $data['description'],
                $data['date']
            ]);
            break;
            
        case 'link.deleted':
            // Mark connection as revoked
            $stmt = $pdo->prepare("
                UPDATE bank_connections 
                SET status = 'revoked', 
                    last_sync = NOW() 
                WHERE belvo_link_id = ?
            ");
            $stmt->execute([$data['link_id']]);
            break;
            
        default:
            throw new Exception('Unknown event type: ' . $data['event']);
    }
    
    // Log webhook event
    $stmt = $pdo->prepare("
        INSERT INTO webhook_logs (
            event_type,
            payload,
            processed_at
        ) VALUES (?, ?, NOW())
    ");
    $stmt->execute([$data['event'], $payload]);
    
    // Commit transaction
    $pdo->commit();
    
    // Return success response
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    // Rollback transaction
    $pdo->rollBack();
    
    // Log error
    error_log("Belvo webhook error: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ]);
} 
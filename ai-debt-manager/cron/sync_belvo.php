<?php
require_once __DIR__ . '/../config/belvo.php';
require_once __DIR__ . '/../includes/db_functions.php';

// Get all active bank connections
$stmt = $pdo->prepare("
    SELECT bc.*, u.email 
    FROM bank_connections bc
    JOIN users u ON bc.user_id = u.id
    WHERE bc.status = 'active'
");
$stmt->execute();
$connections = $stmt->fetchAll();

foreach ($connections as $connection) {
    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Get latest transactions from Belvo
        $date_from = date('Y-m-d', strtotime('-1 day')); // Get last 24 hours
        $transactions = getBelvoTransactions($connection['belvo_link_id'], $date_from);
        
        // Get current balances
        $balances = getBelvoBalances($connection['belvo_link_id']);
        
        // Update account balances
        foreach ($balances as $balance) {
            $stmt = $pdo->prepare("
                UPDATE accounts 
                SET balance = ?, 
                    currency = ?, 
                    last_updated = NOW() 
                WHERE account_number = ? 
                AND bank_connection_id = ?
            ");
            
            $stmt->execute([
                $balance['current'],
                $balance['currency'],
                $balance['account']['number'],
                $connection['id']
            ]);
        }
        
        // Add new transactions
        foreach ($transactions as $transaction) {
            // Check if transaction already exists
            $stmt = $pdo->prepare("
                SELECT id 
                FROM transactions 
                WHERE account_id = (
                    SELECT id 
                    FROM accounts 
                    WHERE account_number = ? 
                    AND bank_connection_id = ?
                )
                AND amount = ?
                AND transaction_date = ?
                AND description = ?
            ");
            
            $stmt->execute([
                $transaction['account']['number'],
                $connection['id'],
                $transaction['amount'],
                $transaction['date'],
                $transaction['description']
            ]);
            
            if (!$stmt->fetch()) {
                // Transaction doesn't exist, add it
                $stmt = $pdo->prepare("
                    INSERT INTO transactions (
                        account_id,
                        amount,
                        description,
                        category_id,
                        transaction_date,
                        created_at
                    ) VALUES (
                        (SELECT id FROM accounts WHERE account_number = ? AND bank_connection_id = ?),
                        ?,
                        ?,
                        NULL,
                        ?,
                        NOW()
                    )
                ");
                
                $stmt->execute([
                    $transaction['account']['number'],
                    $connection['id'],
                    $transaction['amount'],
                    $transaction['description'],
                    $transaction['date']
                ]);
            }
        }
        
        // Update connection last sync time
        $stmt = $pdo->prepare("
            UPDATE bank_connections 
            SET last_sync = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$connection['id']]);
        
        // Log sync
        $stmt = $pdo->prepare("
            INSERT INTO sync_logs (
                user_id,
                connection_id,
                sync_type,
                status,
                details,
                created_at
            ) VALUES (?, ?, 'belvo', 'success', ?, NOW())
        ");
        
        $stmt->execute([
            $connection['user_id'],
            $connection['id'],
            json_encode([
                'transactions_synced' => count($transactions),
                'accounts_updated' => count($balances)
            ])
        ]);
        
        // Commit transaction
        $pdo->commit();
        
    } catch (Exception $e) {
        // Rollback transaction
        $pdo->rollBack();
        
        // Log error
        error_log("Belvo sync error for connection {$connection['id']}: " . $e->getMessage());
        
        // Log failed sync
        $stmt = $pdo->prepare("
            INSERT INTO sync_logs (
                user_id,
                connection_id,
                sync_type,
                status,
                details,
                created_at
            ) VALUES (?, ?, 'belvo', 'error', ?, NOW())
        ");
        
        $stmt->execute([
            $connection['user_id'],
            $connection['id'],
            json_encode(['error' => $e->getMessage()])
        ]);
        
        // Send notification to user
        $stmt = $pdo->prepare("
            INSERT INTO notifications (
                user_id,
                type,
                message,
                details,
                created_at
            ) VALUES (?, 'system', ?, ?, NOW())
        ");
        
        $stmt->execute([
            $connection['user_id'],
            'Error en sincronización bancaria',
            json_encode([
                'connection_id' => $connection['id'],
                'error' => $e->getMessage()
            ])
        ]);
    }
} 
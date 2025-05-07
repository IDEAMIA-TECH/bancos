<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/banks.php';
require_once __DIR__ . '/../../config/database.php';

// Verificar que se proporcionó el ID de conexión
if (!isset($argv[1])) {
    die("Se requiere el ID de conexión\n");
}

$connectionId = (int)$argv[1];

try {
    // Obtener la información de la conexión
    $stmt = $pdo->prepare("
        SELECT * FROM bank_connections 
        WHERE id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$connectionId]);
    $connection = $stmt->fetch();

    if (!$connection) {
        die("Conexión no encontrada\n");
    }

    // Desencriptar credenciales
    $credentials = decryptBankCredentials($connection['credentials']);

    // Configurar Selenium
    $host = sprintf('http://%s:%s/wd/hub', SELENIUM_HOST, SELENIUM_PORT);
    $capabilities = DesiredCapabilities::chrome();
    $driver = RemoteWebDriver::create($host, $capabilities);

    try {
        // Navegar a la página de inicio de sesión
        $driver->get($SUPPORTED_BANKS[$connection['bank_id']]['login_url']);

        // Esperar a que cargue la página
        $driver->wait(10, 1000)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::id('username'))
        );

        // Ingresar credenciales
        $driver->findElement(WebDriverBy::id('username'))->sendKeys($credentials['username']);
        $driver->findElement(WebDriverBy::id('password'))->sendKeys($credentials['password']);

        // Hacer clic en el botón de inicio de sesión
        $driver->findElement(WebDriverBy::cssSelector('button[type="submit"]'))->click();

        // Esperar a que cargue la página principal
        $driver->wait(10, 1000)->until(
            WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::className('account-balance'))
        );

        // Obtener saldos de cuentas
        $accounts = [];
        $accountElements = $driver->findElements(WebDriverBy::className('account-item'));
        
        foreach ($accountElements as $element) {
            $accountNumber = $element->findElement(WebDriverBy::className('account-number'))->getText();
            $balance = $element->findElement(WebDriverBy::className('account-balance'))->getText();
            
            $accounts[] = [
                'account_number' => $accountNumber,
                'balance' => $balance
            ];
        }

        // Guardar las cuentas en la base de datos
        foreach ($accounts as $account) {
            $stmt = $pdo->prepare("
                INSERT INTO accounts (
                    bank_connection_id,
                    account_number,
                    balance,
                    last_sync
                ) VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    balance = VALUES(balance),
                    last_sync = VALUES(last_sync)
            ");

            $stmt->execute([
                $connectionId,
                $account['account_number'],
                $account['balance']
            ]);
        }

        // Obtener movimientos recientes
        $transactions = [];
        $transactionElements = $driver->findElements(WebDriverBy::className('transaction-item'));
        
        foreach ($transactionElements as $element) {
            $date = $element->findElement(WebDriverBy::className('transaction-date'))->getText();
            $description = $element->findElement(WebDriverBy::className('transaction-description'))->getText();
            $amount = $element->findElement(WebDriverBy::className('transaction-amount'))->getText();
            
            $transactions[] = [
                'date' => $date,
                'description' => $description,
                'amount' => $amount
            ];
        }

        // Guardar las transacciones en la base de datos
        foreach ($transactions as $transaction) {
            $stmt = $pdo->prepare("
                INSERT INTO transactions (
                    account_id,
                    date,
                    description,
                    amount,
                    created_at
                ) VALUES (
                    (SELECT id FROM accounts WHERE bank_connection_id = ? LIMIT 1),
                    ?,
                    ?,
                    ?,
                    NOW()
                )
            ");

            $stmt->execute([
                $connectionId,
                $transaction['date'],
                $transaction['description'],
                $transaction['amount']
            ]);
        }

        // Actualizar la última sincronización
        $stmt = $pdo->prepare("
            UPDATE bank_connections 
            SET last_sync = NOW() 
            WHERE id = ?
        ");
        $stmt->execute([$connectionId]);

    } finally {
        // Cerrar el navegador
        $driver->quit();
    }

} catch (Exception $e) {
    error_log('Error en scraper de Banamex: ' . $e->getMessage());
    
    // Marcar la conexión como fallida
    $stmt = $pdo->prepare("
        UPDATE bank_connections 
        SET last_error = ?, 
            last_error_at = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$e->getMessage(), $connectionId]);
} 
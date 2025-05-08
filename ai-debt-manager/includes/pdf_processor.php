<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/config.php';

use Smalot\PdfParser\Parser;

class PDFProcessor {
    private $pdo;
    private $parser;
    private $statement_id;
    private $user_id;
    private $bank_name;
    private $account_number;
    private $statement_date;
    private $file_path;

    public function __construct($statement_id) {
        $this->pdo = $GLOBALS['pdo'];
        $this->parser = new Parser();
        $this->statement_id = $statement_id;
        $this->loadStatement();
    }

    private function loadStatement() {
        $stmt = $this->pdo->prepare("
            SELECT * FROM bank_statements 
            WHERE id = ? AND status = 'pending'
        ");
        $stmt->execute([$this->statement_id]);
        $statement = $stmt->fetch();

        if (!$statement) {
            throw new Exception('Estado de cuenta no encontrado o ya procesado');
        }

        $this->user_id = $statement['user_id'];
        $this->bank_name = $statement['bank_name'];
        $this->account_number = $statement['account_number'];
        $this->statement_date = $statement['statement_date'];
        $this->file_path = __DIR__ . '/../' . $statement['file_path'];
    }

    public function process() {
        try {
            // Parsear PDF
            $pdf = $this->parser->parseFile($this->file_path);
            $text = $pdf->getText();

            // Extraer transacciones según el banco
            $transactions = $this->extractTransactions($text);

            // Guardar transacciones
            $this->saveTransactions($transactions);

            // Actualizar estado
            $this->updateStatus('processed');

            return true;
        } catch (Exception $e) {
            $this->updateStatus('error');
            error_log("Error procesando PDF: " . $e->getMessage());
            throw $e;
        }
    }

    private function extractTransactions($text) {
        $transactions = [];
        $lines = explode("\n", $text);

        // Obtener categorías y patrones
        $categories = $this->getCategories();

        foreach ($lines as $line) {
            // Limpiar línea
            $line = trim($line);
            if (empty($line)) continue;

            // Intentar extraer transacción según el banco
            $transaction = $this->parseTransactionLine($line);
            if ($transaction) {
                // Categorizar transacción
                $transaction['category_id'] = $this->categorizeTransaction($transaction['description'], $categories);
                $transactions[] = $transaction;
            }
        }

        return $transactions;
    }

    private function parseTransactionLine($line) {
        // Patrones específicos por banco
        $patterns = [
            'BBVA' => '/^(\d{2}\/\d{2}\/\d{2})\s+(\d{2}\/\d{2}\/\d{2})\s+([^\d]+)\s+([\d,]+\.\d{2})/',
            'Santander' => '/^(\d{2}\/\d{2}\/\d{2})\s+([^\d]+)\s+([\d,]+\.\d{2})/',
            'Banamex' => '/^(\d{2}\/\d{2}\/\d{2})\s+([^\d]+)\s+([\d,]+\.\d{2})/',
            'Banorte' => '/^(\d{2}\/\d{2}\/\d{2})\s+([^\d]+)\s+([\d,]+\.\d{2})/',
            'HSBC' => '/^(\d{2}\/\d{2}\/\d{2})\s+([^\d]+)\s+([\d,]+\.\d{2})/'
        ];

        $pattern = $patterns[$this->bank_name] ?? null;
        if (!$pattern) return null;

        if (preg_match($pattern, $line, $matches)) {
            $amount = str_replace(',', '', $matches[count($matches) - 1]);
            $is_income = $amount > 0;

            return [
                'date' => $matches[1],
                'description' => trim($matches[2]),
                'amount' => abs($amount),
                'is_income' => $is_income
            ];
        }

        return null;
    }

    private function getCategories() {
        $stmt = $this->pdo->prepare("
            SELECT id, name, patterns 
            FROM categories 
            WHERE user_id = ? OR user_id IS NULL
        ");
        $stmt->execute([$this->user_id]);
        return $stmt->fetchAll();
    }

    private function categorizeTransaction($description, $categories) {
        $description = strtoupper($description);
        
        foreach ($categories as $category) {
            if (empty($category['patterns'])) continue;
            
            $patterns = json_decode($category['patterns'], true);
            foreach ($patterns as $pattern) {
                if (stripos($description, $pattern) !== false) {
                    return $category['id'];
                }
            }
        }

        // Categoría por defecto
        return 1; // "Otros"
    }

    private function saveTransactions($transactions) {
        $stmt = $this->pdo->prepare("
            INSERT INTO transactions (
                user_id,
                bank_statement_id,
                date,
                description,
                amount,
                is_income,
                category_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($transactions as $transaction) {
            $stmt->execute([
                $this->user_id,
                $this->statement_id,
                $transaction['date'],
                $transaction['description'],
                $transaction['amount'],
                $transaction['is_income'],
                $transaction['category_id']
            ]);
        }
    }

    private function updateStatus($status) {
        $stmt = $this->pdo->prepare("
            UPDATE bank_statements 
            SET status = ?, 
                processed_at = CURRENT_TIMESTAMP 
            WHERE id = ?
        ");
        $stmt->execute([$status, $this->statement_id]);
    }
} 
<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/pdf_processor.php';

// Obtener estados de cuenta pendientes
$stmt = $pdo->prepare("
    SELECT id 
    FROM bank_statements 
    WHERE status = 'pending' 
    ORDER BY created_at ASC
");
$stmt->execute();
$statements = $stmt->fetchAll();

foreach ($statements as $statement) {
    try {
        $processor = new PDFProcessor($statement['id']);
        $processor->process();
        echo "Estado de cuenta {$statement['id']} procesado correctamente\n";
    } catch (Exception $e) {
        echo "Error procesando estado de cuenta {$statement['id']}: " . $e->getMessage() . "\n";
        continue;
    }
} 
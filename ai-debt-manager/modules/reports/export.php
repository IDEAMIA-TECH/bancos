<?php
require_once __DIR__ . '/../../includes/auth_functions.php';
requireLogin();

// Verify request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: generate.php');
    exit;
}

// Get and validate input data
$report_type = $_POST['report_type'] ?? '';
$format = $_POST['format'] ?? '';
$start_date = $_POST['start_date'] ?? '';
$end_date = $_POST['end_date'] ?? '';

if (!in_array($report_type, ['debts', 'transactions']) || 
    !in_array($format, ['pdf', 'excel']) || 
    empty($start_date) || empty($end_date)) {
    header('Location: generate.php');
    exit;
}

try {
    // Get user data for report
    $user = $pdo->prepare("SELECT * FROM users WHERE id = ?")->execute([$_SESSION['user_id']])->fetch();

    if ($report_type === 'debts') {
        // Get debts data
        $stmt = $pdo->prepare("
            SELECT d.*, a.account_number, bc.institution_id
            FROM debts d
            JOIN accounts a ON d.account_id = a.id
            JOIN bank_connections bc ON a.bank_connection_id = bc.id
            WHERE d.user_id = ?
            " . ($_POST['debt_id'] !== 'all' ? "AND d.id = ?" : "") . "
            AND d.start_date BETWEEN ? AND ?
            ORDER BY d.current_amount DESC
        ");

        $params = [$_SESSION['user_id']];
        if ($_POST['debt_id'] !== 'all') {
            $params[] = $_POST['debt_id'];
        }
        $params[] = $start_date;
        $params[] = $end_date;

        $stmt->execute($params);
        $data = $stmt->fetchAll();

        // Generate report
        if ($format === 'pdf') {
            require_once __DIR__ . '/../../../vendor/tecnickcom/tcpdf/tcpdf.php';
            
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            $pdf->SetCreator(PDF_CREATOR);
            $pdf->SetAuthor($user['full_name']);
            $pdf->SetTitle('Reporte de Deudas');

            $pdf->AddPage();
            $pdf->SetFont('helvetica', '', 12);

            // Add content
            $html = '<h1>Reporte de Deudas</h1>';
            $html .= '<p>Período: ' . date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date)) . '</p>';
            
            $html .= '<table border="1" cellpadding="5">
                <tr>
                    <th>Institución</th>
                    <th>Cuenta</th>
                    <th>Monto Actual</th>
                    <th>Tasa de Interés</th>
                    <th>Fecha de Vencimiento</th>
                </tr>';

            foreach ($data as $debt) {
                $html .= '<tr>
                    <td>' . htmlspecialchars($debt['institution_id']) . '</td>
                    <td>' . htmlspecialchars($debt['account_number']) . '</td>
                    <td>$' . number_format($debt['current_amount'], 2) . '</td>
                    <td>' . $debt['interest_rate'] . '%</td>
                    <td>' . date('d/m/Y', strtotime($debt['due_date'])) . '</td>
                </tr>';
            }

            $html .= '</table>';

            $pdf->writeHTML($html, true, false, true, false, '');
            
            // Output PDF
            $pdf->Output('reporte_deudas.pdf', 'D');
        } else {
            // Generate Excel
            require_once __DIR__ . '/../../../vendor/phpoffice/phpspreadsheet/src/Bootstrap.php';

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set headers
            $sheet->setCellValue('A1', 'Institución');
            $sheet->setCellValue('B1', 'Cuenta');
            $sheet->setCellValue('C1', 'Monto Actual');
            $sheet->setCellValue('D1', 'Tasa de Interés');
            $sheet->setCellValue('E1', 'Fecha de Vencimiento');

            // Add data
            $row = 2;
            foreach ($data as $debt) {
                $sheet->setCellValue('A' . $row, $debt['institution_id']);
                $sheet->setCellValue('B' . $row, $debt['account_number']);
                $sheet->setCellValue('C' . $row, $debt['current_amount']);
                $sheet->setCellValue('D' . $row, $debt['interest_rate']);
                $sheet->setCellValue('E' . $row, date('d/m/Y', strtotime($debt['due_date'])));
                $row++;
            }

            // Output Excel
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="reporte_deudas.xlsx"');
            header('Cache-Control: max-age=0');
            $writer->save('php://output');
        }
    } else {
        // Get transactions data
        $stmt = $pdo->prepare("
            SELECT t.*, a.account_number, bc.institution_id, c.name as category
            FROM transactions t
            JOIN accounts a ON t.account_id = a.id
            JOIN bank_connections bc ON a.bank_connection_id = bc.id
            LEFT JOIN categories c ON t.category_id = c.id
            WHERE bc.user_id = ?
            " . ($_POST['account_id'] !== 'all' ? "AND t.account_id = ?" : "") . "
            AND t.transaction_date BETWEEN ? AND ?
            ORDER BY t.transaction_date DESC
        ");

        $params = [$_SESSION['user_id']];
        if ($_POST['account_id'] !== 'all') {
            $params[] = $_POST['account_id'];
        }
        $params[] = $start_date;
        $params[] = $end_date;

        $stmt->execute($params);
        $data = $stmt->fetchAll();

        // Generate report
        if ($format === 'pdf') {
            require_once __DIR__ . '/../../../vendor/tecnickcom/tcpdf/tcpdf.php';
            
            $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
            $pdf->SetCreator(PDF_CREATOR);
            $pdf->SetAuthor($user['full_name']);
            $pdf->SetTitle('Reporte de Transacciones');

            $pdf->AddPage();
            $pdf->SetFont('helvetica', '', 12);

            // Add content
            $html = '<h1>Reporte de Transacciones</h1>';
            $html .= '<p>Período: ' . date('d/m/Y', strtotime($start_date)) . ' - ' . date('d/m/Y', strtotime($end_date)) . '</p>';
            
            $html .= '<table border="1" cellpadding="5">
                <tr>
                    <th>Fecha</th>
                    <th>Institución</th>
                    <th>Cuenta</th>
                    <th>Descripción</th>
                    <th>Categoría</th>
                    <th>Monto</th>
                </tr>';

            foreach ($data as $transaction) {
                $html .= '<tr>
                    <td>' . date('d/m/Y', strtotime($transaction['transaction_date'])) . '</td>
                    <td>' . htmlspecialchars($transaction['institution_id']) . '</td>
                    <td>' . htmlspecialchars($transaction['account_number']) . '</td>
                    <td>' . htmlspecialchars($transaction['description']) . '</td>
                    <td>' . htmlspecialchars($transaction['category']) . '</td>
                    <td>$' . number_format($transaction['amount'], 2) . '</td>
                </tr>';
            }

            $html .= '</table>';

            $pdf->writeHTML($html, true, false, true, false, '');
            
            // Output PDF
            $pdf->Output('reporte_transacciones.pdf', 'D');
        } else {
            // Generate Excel
            require_once __DIR__ . '/../../../vendor/phpoffice/phpspreadsheet/src/Bootstrap.php';

            $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
            $sheet = $spreadsheet->getActiveSheet();

            // Set headers
            $sheet->setCellValue('A1', 'Fecha');
            $sheet->setCellValue('B1', 'Institución');
            $sheet->setCellValue('C1', 'Cuenta');
            $sheet->setCellValue('D1', 'Descripción');
            $sheet->setCellValue('E1', 'Categoría');
            $sheet->setCellValue('F1', 'Monto');

            // Add data
            $row = 2;
            foreach ($data as $transaction) {
                $sheet->setCellValue('A' . $row, date('d/m/Y', strtotime($transaction['transaction_date'])));
                $sheet->setCellValue('B' . $row, $transaction['institution_id']);
                $sheet->setCellValue('C' . $row, $transaction['account_number']);
                $sheet->setCellValue('D' . $row, $transaction['description']);
                $sheet->setCellValue('E' . $row, $transaction['category']);
                $sheet->setCellValue('F' . $row, $transaction['amount']);
                $row++;
            }

            // Output Excel
            $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment;filename="reporte_transacciones.xlsx"');
            header('Cache-Control: max-age=0');
            $writer->save('php://output');
        }
    }
} catch (Exception $e) {
    // Log error and redirect
    error_log('Error generating report: ' . $e->getMessage());
    header('Location: generate.php?error=1');
    exit;
} 
<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/auth.php';

// Auth::requireLogin();

$db = Database::getInstance();
$pdo = $db->getConnection();
$user_id = 1; // TODO: Replace with actual user ID

// Get filters
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-d');
$format = $_GET['format'] ?? 'csv'; // csv or excel

try {
    $stmt = $pdo->prepare("
        SELECT 
            invoice_no,
            client_name,
            client_email,
            client_phone,
            invoice_date,
            due_date,
            subtotal,
            tax_rate,
            tax_amount,
            total,
            status,
            payment_method,
            payment_reference,
            paid_at,
            created_at
        FROM invoices 
        WHERE user_id = ? 
        AND invoice_date BETWEEN ? AND ?
        ORDER BY invoice_date DESC
    ");
    
    $stmt->execute([$user_id, $startDate, $endDate]);
    $invoices = $stmt->fetchAll();
    
    if ($format === 'excel') {
        // Export as Excel (using simple CSV with .xls extension)
        header('Content-Type: application/vnd.ms-excel');
        header('Content-Disposition: attachment; filename="invoices_' . date('Ymd') . '.xls"');
        
        echo "Invoice No\tClient Name\tEmail\tPhone\tInvoice Date\tDue Date\tSubtotal\tTax Rate\tTax Amount\tTotal\tStatus\tPayment Method\tPayment Reference\tPaid At\tCreated At\n";
        
        foreach ($invoices as $invoice) {
            echo implode("\t", [
                $invoice['invoice_no'],
                $invoice['client_name'],
                $invoice['client_email'],
                $invoice['client_phone'],
                $invoice['invoice_date'],
                $invoice['due_date'],
                $invoice['subtotal'],
                $invoice['tax_rate'],
                $invoice['tax_amount'],
                $invoice['total'],
                $invoice['status'],
                $invoice['payment_method'],
                $invoice['payment_reference'],
                $invoice['paid_at'],
                $invoice['created_at']
            ]) . "\n";
        }
        
    } else {
        // Export as CSV
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="invoices_' . date('Ymd') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Add BOM for UTF-8
        fwrite($output, "\xEF\xBB\xBF");
        
        // Headers
        fputcsv($output, [
            'Invoice No', 'Client Name', 'Email', 'Phone', 
            'Invoice Date', 'Due Date', 'Subtotal', 'Tax Rate', 
            'Tax Amount', 'Total', 'Status', 'Payment Method', 
            'Payment Reference', 'Paid At', 'Created At'
        ]);
        
        // Data
        foreach ($invoices as $invoice) {
            fputcsv($output, [
                $invoice['invoice_no'],
                $invoice['client_name'],
                $invoice['client_email'],
                $invoice['client_phone'],
                $invoice['invoice_date'],
                $invoice['due_date'],
                $invoice['subtotal'],
                $invoice['tax_rate'],
                $invoice['tax_amount'],
                $invoice['total'],
                $invoice['status'],
                $invoice['payment_method'],
                $invoice['payment_reference'],
                $invoice['paid_at'],
                $invoice['created_at']
            ]);
        }
        
        fclose($output);
    }
    
} catch (Exception $e) {
    jsonResponse(['success' => false, 'error' => 'Export failed: ' . $e->getMessage()], 500);
}
?>
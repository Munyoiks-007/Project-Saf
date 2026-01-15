<?php
require_once __DIR__ . '/../includes/functions.php';

function generateSequentialInvoiceNumber() {
    // Get last invoice number
    require_once __DIR__ . '/../includes/Database.php';
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    $year = date('Y');
    $month = date('m');
    
    // Format: INV-YYYYMM-001
    $stmt = $pdo->prepare("
        SELECT invoice_no 
        FROM invoices 
        WHERE invoice_no LIKE ? 
        ORDER BY id DESC 
        LIMIT 1
    ");
    $stmt->execute(["INV-$year$month-%"]);
    $lastInvoice = $stmt->fetch();
    
    if ($lastInvoice) {
        // Extract number and increment
        $parts = explode('-', $lastInvoice['invoice_no']);
        $lastNum = intval(end($parts));
        $nextNum = str_pad($lastNum + 1, 3, '0', STR_PAD_LEFT);
    } else {
        $nextNum = '001';
    }
    
    return "INV-$year$month-$nextNum";
}

$invoiceNo = generateSequentialInvoiceNumber();
jsonResponse(['invoice_no' => $invoiceNo]);
?>
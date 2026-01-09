<?php
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

// Include TCPDF
require_once __DIR__ . '/../vendor/tecnickcom/tcpdf/tcpdf.php';

$id = intval($_GET['id']);

if ($id <= 0) {
    jsonResponse([
        'success' => false,
        'error' => 'Invalid invoice ID'
    ], 400);
}

$db = Database::getInstance();
$pdo = $db->getConnection();

try {
    // Get invoice data
    $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->execute([$id]);
    $invoice = $stmt->fetch();
    
    if (!$invoice) {
        jsonResponse([
            'success' => false,
            'error' => 'Invoice not found'
        ], 404);
    }
    
    $itemStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
    $itemStmt->execute([$id]);
    $items = $itemStmt->fetchAll();
    
    // Create PDF
    $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);
    
    // Set document information
    $pdf->SetCreator('Mojo System');
    $pdf->SetAuthor('Mojo Electrical Enterprise');
    $pdf->SetTitle('Invoice ' . $invoice['invoice_no']);
    $pdf->SetSubject('Invoice');
    
    // Remove default header/footer
    $pdf->setPrintHeader(false);
    $pdf->setPrintFooter(false);
    
    // Add a page
    $pdf->AddPage();
    
    // Company Info
    $pdf->SetFont('helvetica', 'B', 16);
    $pdf->Cell(0, 10, 'Mojo Electrical Enterprise', 0, 1);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(0, 5, 'P.O. Box 98664 - 80100, Mombasa', 0, 1);
    $pdf->Cell(0, 5, 'Phone: +254 721 856 011 / 0731 120 072', 0, 1);
    $pdf->Cell(0, 5, 'Email: gathucimoses@gmail.com', 0, 1);
    
    $pdf->Ln(10);
    
    // Invoice Info
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 10, 'INVOICE', 0, 1);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->Cell(50, 5, 'Invoice #:', 0, 0);
    $pdf->Cell(0, 5, $invoice['invoice_no'], 0, 1);
    
    $pdf->Cell(50, 5, 'Client:', 0, 0);
    $pdf->Cell(0, 5, $invoice['client_name'], 0, 1);
    
    $pdf->Cell(50, 5, 'Date:', 0, 0);
    $pdf->Cell(0, 5, $invoice['invoice_date'], 0, 1);
    
    $pdf->Ln(10);
    
    // Items Table Header
    $pdf->SetFillColor(200, 200, 200);
    $pdf->SetFont('helvetica', 'B', 10);
    
    $pdf->Cell(30, 7, 'Item', 1, 0, 'L', true);
    $pdf->Cell(80, 7, 'Description', 1, 0, 'L', true);
    $pdf->Cell(20, 7, 'Qty', 1, 0, 'C', true);
    $pdf->Cell(30, 7, 'Unit Price', 1, 0, 'R', true);
    $pdf->Cell(30, 7, 'Total', 1, 1, 'R', true);
    
    $pdf->SetFont('helvetica', '', 10);
    $pdf->SetFillColor(255, 255, 255);
    
    // Items Table Rows
    foreach ($items as $item) {
        $pdf->Cell(30, 7, substr($item['item'], 0, 20), 1, 0, 'L');
        $pdf->Cell(80, 7, substr($item['description'], 0, 40), 1, 0, 'L');
        $pdf->Cell(20, 7, $item['quantity'], 1, 0, 'C');
        $pdf->Cell(30, 7, number_format($item['unit_price'], 2), 1, 0, 'R');
        $pdf->Cell(30, 7, number_format($item['total'], 2), 1, 1, 'R');
    }
    
    $pdf->Ln(10);
    
    // Totals
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(140, 7, 'Subtotal:', 0, 0, 'R');
    $pdf->Cell(30, 7, 'KES ' . number_format($invoice['subtotal'], 2), 0, 1, 'R');
    
    $pdf->Cell(140, 7, 'Tax:', 0, 0, 'R');
    $pdf->Cell(30, 7, 'KES ' . number_format($invoice['tax'], 2), 0, 1, 'R');
    
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(140, 10, 'Total:', 0, 0, 'R');
    $pdf->Cell(30, 10, 'KES ' . number_format($invoice['total'], 2), 0, 1, 'R');
    
    // Output PDF
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="Invoice_' . $invoice['invoice_no'] . '.pdf"');
    $pdf->Output('Invoice_' . $invoice['invoice_no'] . '.pdf', 'D');
    
} catch (Exception $e) {
    jsonResponse([
        'success' => false,
        'error' => 'Failed to generate PDF',
        'details' => $e->getMessage()
    ], 500);
}
?>
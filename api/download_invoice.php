<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/Database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();


$pdf = new TCPDF();


// ---------------- VALIDATE ----------------
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    exit('Invalid invoice ID');
}

$invoiceId = (int)$_GET['id'];

// ---------------- FETCH INVOICE ----------------
$stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
$stmt->execute([$invoiceId]);
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$invoice) {
    exit('Invoice not found');
}

// ---------------- FETCH ITEMS ----------------
$itemStmt = $pdo->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$itemStmt->execute([$invoiceId]);
$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

// ---------------- PDF ----------------
$pdf = new TCPDF();
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 15);
$pdf->AddPage();

// ---------------- LOGO ----------------
// IMPORTANT: absolute filesystem path
$logoPath = realpath(__DIR__ . '/../FrontEnd/logo.png');

if ($logoPath && file_exists($logoPath)) {
    $pdf->Image($logoPath, 15, 10, 30);
}

$pdf->SetY(15);
$pdf->SetX(50);
$pdf->SetFont('helvetica', 'B', 16);
$pdf->Cell(0, 8, 'Mojo Electrical Enterprise', 0, 1);

$pdf->SetX(50);
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, 'Reliability in electrical services', 0, 1);
$pdf->SetX(50);
$pdf->Cell(0, 6, 'P.O. Box 98664 - 80100, Mombasa', 0, 1);
$pdf->SetX(50);
$pdf->Cell(0, 6, 'Phone: +254 721 856 011 / 0731 120 072', 0, 1);
$pdf->SetX(50);
$pdf->Cell(0, 6, 'Email: gathucimoses@gmail.com', 0, 1);

$pdf->Ln(10);
$pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
$pdf->Ln(6);

// ---------------- INVOICE TITLE ----------------
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 8, 'INVOICE', 0, 1);

// ---------------- META ----------------
$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(100, 6, 'Invoice No: ' . $invoice['invoice_no'], 0, 0);
$pdf->Cell(0, 6, 'Date: ' . $invoice['invoice_date'], 0, 1);
$pdf->Cell(100, 6, 'Due Date: ' . $invoice['due_date'], 0, 1);

$pdf->Ln(6);

// ---------------- CLIENT ----------------
$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(0, 6, 'Bill To:', 0, 1);

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 6, $invoice['client_name'], 0, 1);
$pdf->Cell(0, 6, 'Phone: ' . $invoice['client_phone'], 0, 1);
$pdf->Cell(0, 6, 'Email: ' . $invoice['client_email'], 0, 1);
$pdf->MultiCell(0, 6, 'Address: ' . $invoice['client_address']);

$pdf->Ln(6);

// ---------------- ITEMS TABLE ----------------
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(10, 7, '#', 1);
$pdf->Cell(50, 7, 'Item', 1);
$pdf->Cell(50, 7, 'Description', 1);
$pdf->Cell(15, 7, 'Qty', 1);
$pdf->Cell(30, 7, 'Unit', 1);
$pdf->Cell(30, 7, 'Total', 1);
$pdf->Ln();

$pdf->SetFont('helvetica', '', 10);
$i = 1;
foreach ($items as $item) {
    $pdf->Cell(10, 7, $i++, 1);
    $pdf->Cell(50, 7, $item['item'], 1);
    $pdf->Cell(50, 7, $item['description'], 1);
    $pdf->Cell(15, 7, $item['quantity'], 1);
    $pdf->Cell(30, 7, number_format($item['unit_price'], 2), 1);
    $pdf->Cell(30, 7, number_format($item['total'], 2), 1);
    $pdf->Ln();
}

// ---------------- TOTALS ----------------
$pdf->Ln(4);
$pdf->Cell(125);
$pdf->Cell(40, 6, 'Subtotal', 1);
$pdf->Cell(30, 6, number_format($invoice['subtotal'], 2), 1);
$pdf->Ln();

$pdf->Cell(125);
$pdf->Cell(40, 6, 'Tax', 1);
$pdf->Cell(30, 6, number_format($invoice['tax_amount'], 2), 1);
$pdf->Ln();

$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(125);
$pdf->Cell(40, 7, 'TOTAL', 1);
$pdf->Cell(30, 7, number_format($invoice['total'], 2), 1);

// ---------------- OUTPUT ----------------
$pdf->Output('Invoice_' . $invoice['invoice_no'] . '.pdf', 'I');

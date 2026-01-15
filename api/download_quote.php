<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);


// ================= DEPENDENCIES =================
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../includes/Database.php';

// ================= DB =================
$pdo = Database::getInstance()->getConnection();

// ================= VALIDATE =================
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    http_response_code(400);
    exit;
}

$quoteId = (int) $_GET['id'];

// ================= FETCH QUOTE =================
$stmt = $pdo->prepare("SELECT * FROM quotes WHERE id = ?");
$stmt->execute([$quoteId]);
$quote = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quote) {
    http_response_code(404);
    exit;
}

// ================= FETCH ITEMS =================
$itemStmt = $pdo->prepare("SELECT * FROM quotation_items WHERE quote_id = ?");
$itemStmt->execute([$quoteId]);
$items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);

// ================= CREATE PDF =================
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
$pdf->SetCreator('Mojo Electrical Enterprise');
$pdf->SetAuthor('Mojo Electrical Enterprise');
$pdf->SetTitle('Quote ' . $quote['quote_no']);
$pdf->SetMargins(15, 25, 15);
$pdf->AddPage();

// ================= LOGO (SAFE) =================
$logoPath = realpath(__DIR__ . '/../FrontEnd/logo.png');
if ($logoPath && file_exists($logoPath)) {
    $pdf->Image($logoPath, 15, 10, 35);
}

// ================= HEADER =================
$pdf->SetFont('helvetica', 'B', 14);
$pdf->Cell(0, 10, 'MOJO ELECTRICAL ENTERPRISE', 0, 1, 'R');

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(0, 5, 'P.O. Box 98664 - 80100, Mombasa', 0, 1, 'R');
$pdf->Cell(0, 5, 'Phone: +254 721 856 011 / 0731 120 072', 0, 1, 'R');
$pdf->Ln(10);

// ================= QUOTE DETAILS =================
$pdf->SetFont('helvetica', 'B', 12);
$pdf->Cell(0, 8, 'QUOTATION', 0, 1);

$pdf->SetFont('helvetica', '', 10);
$pdf->Cell(100, 6, 'Quote No: ' . $quote['quote_no'], 0, 0);
$pdf->Cell(0, 6, 'Date: ' . $quote['quote_date'], 0, 1);

$pdf->Cell(100, 6, 'Client: ' . $quote['client_name'], 0, 0);
$pdf->Cell(0, 6, 'Valid Until: ' . $quote['valid_until'], 0, 1);
$pdf->Ln(4);

// ================= ITEMS TABLE =================
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(75, 8, 'Item', 1);
$pdf->Cell(20, 8, 'Unit', 1);
$pdf->Cell(20, 8, 'Qty', 1);
$pdf->Cell(30, 8, 'Unit Price', 1);
$pdf->Cell(30, 8, 'Total', 1);
$pdf->Ln();

$pdf->SetFont('helvetica', '', 10);
foreach ($items as $item) {
    $pdf->Cell(75, 8, $item['item_name'], 1);
    $pdf->Cell(20, 8, $item['unit'], 1);
    $pdf->Cell(20, 8, $item['quantity'], 1);
    $pdf->Cell(30, 8, number_format($item['unit_price'], 2), 1);
    $pdf->Cell(30, 8, number_format($item['total'], 2), 1);
    $pdf->Ln();
}

// ================= TOTALS =================
$pdf->Ln(5);
$pdf->SetFont('helvetica', 'B', 10);
$pdf->Cell(150, 6, 'Subtotal', 0, 0, 'R');
$pdf->Cell(30, 6, number_format($quote['subtotal'], 2), 0, 1, 'R');

$pdf->Cell(150, 6, 'Discount', 0, 0, 'R');
$pdf->Cell(30, 6, number_format($quote['discount'], 2), 0, 1, 'R');

$pdf->Cell(150, 6, 'Tax', 0, 0, 'R');
$pdf->Cell(30, 6, number_format($quote['tax_amount'], 2), 0, 1, 'R');

$pdf->SetFont('helvetica', 'B', 11);
$pdf->Cell(150, 8, 'TOTAL', 0, 0, 'R');
$pdf->Cell(30, 8, number_format($quote['total'], 2), 0, 1, 'R');

// ================= OUTPUT =================
$pdf->Output('Quote_' . $quote['quote_no'] . '.pdf', 'I');
exit;

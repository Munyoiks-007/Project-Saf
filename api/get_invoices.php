<?php
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

// Get query parameters
$page = max(1, intval($_GET['page'] ?? 1));
$limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$clientName = $_GET['client_name'] ?? null;
$dateFrom = $_GET['date_from'] ?? null;
$dateTo = $_GET['date_to'] ?? null;

// Build query
$query = "
    SELECT 
        id, invoice_no, client_name, invoice_date,
        subtotal, tax, total,
        created_at, updated_at
    FROM invoices
    WHERE 1=1
";

$params = [];
$types = [];

if ($clientName) {
    $query .= " AND client_name LIKE ?";
    $params[] = "%$clientName%";
    $types[] = 's';
}

if ($dateFrom) {
    $query .= " AND invoice_date >= ?";
    $params[] = $dateFrom;
    $types[] = 's';
}

if ($dateTo) {
    $query .= " AND invoice_date <= ?";
    $params[] = $dateTo;
    $types[] = 's';
}

$query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$types[] = 'i';
$params[] = $offset;
$types[] = 'i';

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

// Get total count
$countQuery = "SELECT COUNT(*) as total FROM invoices WHERE 1=1";
$countParams = [];
$countTypes = [];

if ($clientName) {
    $countQuery .= " AND client_name LIKE ?";
    $countParams[] = "%$clientName%";
    $countTypes[] = 's';
}

if ($dateFrom) {
    $countQuery .= " AND invoice_date >= ?";
    $countParams[] = $dateFrom;
    $countTypes[] = 's';
}

if ($dateTo) {
    $countQuery .= " AND invoice_date <= ?";
    $countParams[] = $dateTo;
    $countTypes[] = 's';
}

$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($countParams);
$totalResult = $countStmt->fetch();
$total = $totalResult['total'];

// Format response
$formattedInvoices = array_map(function($invoice) {
    return [
        'id' => (int) $invoice['id'],
        'invoice_no' => $invoice['invoice_no'],
        'client_name' => $invoice['client_name'],
        'invoice_date' => $invoice['invoice_date'],
        'subtotal' => toNumber($invoice['subtotal']),
        'tax' => toNumber($invoice['tax']),
        'total' => toNumber($invoice['total']),
        'created_at' => $invoice['created_at'],
        'updated_at' => $invoice['updated_at']
    ];
}, $invoices);

jsonResponse([
    'success' => true,
    'data' => $formattedInvoices,
    'pagination' => [
        'total' => (int) $total,
        'page' => $page,
        'limit' => $limit,
        'totalPages' => ceil($total / $limit)
    ]
]);
?>
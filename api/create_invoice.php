<?php
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/functions.php';

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    jsonResponse([
        'success' => false,
        'error' => 'Invalid JSON input'
    ], 400);
}

// Validation
$required = ['invoice_no', 'client_name', 'invoice_date', 'items'];
foreach ($required as $field) {
    if (!isset($input[$field])) {
        jsonResponse([
            'success' => false,
            'error' => "Missing required field: $field"
        ], 400);
    }
}

if (!is_array($input['items']) || count($input['items']) === 0) {
    jsonResponse([
        'success' => false,
        'error' => 'At least one item is required'
    ], 400);
}

$db = Database::getInstance();
$pdo = $db->getConnection();

try {
    $db->beginTransaction();
    
    // Insert invoice
   $stmt = $pdo->prepare(
  "INSERT INTO invoices (
    user_id,
    invoice_no,
    client_name,
    client_email,
    client_phone,
    client_address,
    invoice_date,
    due_date,
    subtotal,
    tax_rate,
    tax_amount,
    `total`,
    status,
    notes
  ) VALUES (
    ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?
  )"
);

    
    $stmt->execute([
  $input['user_id'] ?? null,
  $input['invoice_no'],
  $input['client_name'],
  $input['client_email'] ?? null,
  $input['client_phone'] ?? null,
  $input['client_address'] ?? null,
  $input['invoice_date'],
  $input['due_date'] ?? null,
  validateNumber($input['subtotal'] ?? 0, 'subtotal'),
  validateNumber($input['tax_rate'] ?? 0, 'tax_rate'),
  validateNumber($input['tax_amount'] ?? 0, 'tax_amount'),
  validateNumber($input['total'] ?? 0, 'total'),
  $input['status'] ?? 'draft',
  $input['notes'] ?? null
]);

    
    $invoiceId = $pdo->lastInsertId();
    
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    
    // Insert items
    $itemStmt = $pdo->prepare("
        INSERT INTO invoice_items 
        (invoice_id, item, description, quantity, unit_price, total)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    foreach ($input['items'] as $item) {
        $itemStmt->execute([
            $invoiceId,
            substr($item['item'] ?? 'Unspecified Item', 0, 100),
            substr($item['description'] ?? '', 0, 500),
            validateNumber($item['quantity'] ?? 0, 'quantity'),
            validateNumber($item['unit_price'] ?? 0, 'unit_price'),
            validateNumber($item['total'] ?? 0, 'item total')
        ]);
    }
    
    $db->commit();
    
    jsonResponse([
        'success' => true,
        'invoiceId' => $invoiceId,
        'invoiceNo' => $input['invoice_no']
    ], 201);
    
} catch (Exception $e) {
    $db->rollBack();

    if (str_contains($e->getMessage(), 'Duplicate')) {
        jsonResponse([
            'success' => false,
            'error' => 'Invoice already saved'
        ], 409);
    }

    jsonResponse([
        'success' => false,
        'error' => $e->getMessage()
    ], 400);
}
?>
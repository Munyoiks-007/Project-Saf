<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/Database.php';

$db = Database::getInstance();
$pdo = $db->getConnection();

// Read JSON body
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['id']) || !is_numeric($input['id'])) {
    echo json_encode([
        'success' => false,
        'error' => 'Invalid invoice ID'
    ]);
    exit;
}

$invoiceId = (int)$input['id'];

try {
    $pdo->beginTransaction();

    // Delete invoice items first
    $stmt = $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
    $stmt->execute([$invoiceId]);

    // Delete invoice
    $stmt = $pdo->prepare("DELETE FROM invoices WHERE id = ?");
    $stmt->execute([$invoiceId]);

    if ($stmt->rowCount() === 0) {
        $pdo->rollBack();
        echo json_encode([
            'success' => false,
            'error' => 'Invoice not found'
        ]);
        exit;
    }

    $pdo->commit();

    echo json_encode([
        'success' => true
    ]);

} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode([
        'success' => false,
        'error' => 'Failed to delete invoice'
    ]);
}

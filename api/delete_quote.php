<?php
header('Content-Type: application/json');

require '../includes/Database.php';

try {
    $pdo = Database::getInstance()->getConnection();

    // Get quote ID (from POST or GET)
    $quoteId = $_POST['id'] ?? $_GET['id'] ?? null;

    if (!$quoteId || !is_numeric($quoteId)) {
        throw new Exception('Invalid quote ID');
    }

    $pdo->beginTransaction();

    /* ================= DELETE QUOTE ITEMS ================= */
    $stmtItems = $pdo->prepare("
        DELETE FROM quotation_items
        WHERE quote_id = :quote_id
    ");
    $stmtItems->execute([
        ':quote_id' => $quoteId
    ]);

    /* ================= DELETE QUOTE ================= */
    $stmtQuote = $pdo->prepare("
        DELETE FROM quotes
        WHERE id = :id
    ");
    $stmtQuote->execute([
        ':id' => $quoteId
    ]);

    if ($stmtQuote->rowCount() === 0) {
        throw new Exception('Quote not found or already deleted');
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Quote deleted successfully'
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

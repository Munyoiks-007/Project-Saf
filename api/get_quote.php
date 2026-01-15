<?php
header('Content-Type: application/json');

require '../includes/Database.php';
$pdo = Database::getInstance()->getConnection();

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid quote ID']);
    exit;
}

try {
    // Get main quote details, including vat_percentage
    $stmt = $pdo->prepare('SELECT * FROM quotes WHERE id = ?');
    $stmt->execute([$id]);
    $quote = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$quote) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Quote not found']);
        exit;
    }

    // Get quote items
    $itemStmt = $pdo->prepare('SELECT * FROM quote_items WHERE quote_id = ?');
    $itemStmt->execute([$id]);
    $items = $itemStmt->fetchAll(PDO::FETCH_ASSOC);
    $quote['items'] = $items;

    // Ensure vat_percentage is present in response
    if (!isset($quote['vat_percentage'])) {
        $quote['vat_percentage'] = null;
    }

    echo json_encode(['success' => true, 'data' => $quote]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to load quote']);
}

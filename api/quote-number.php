<?php

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/Database.php';
$pdo = Database::getInstance()->getConnection();

try {
    // Get last quote number
    $stmt = $pdo->query("
        SELECT quote_no
        FROM quotes
        ORDER BY id DESC
        LIMIT 1
    ");

    $lastQuote = $stmt->fetch(PDO::FETCH_ASSOC);

    $year = date('Y');
    $nextNumber = 1;

    if ($lastQuote && isset($lastQuote['quote_no'])) {
        // Extract numeric part (Q-YYYY-XXXX)
        if (preg_match('/Q-\d{4}-(\d+)/', $lastQuote['quote_no'], $matches)) {
            $nextNumber = (int)$matches[1] + 1;
        }
    }

    $quoteNo = sprintf('Q-%s-%04d', $year, $nextNumber);

    echo json_encode([
        'success' => true,
        'quote_no' => $quoteNo
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to generate quote number'
    ]);
}

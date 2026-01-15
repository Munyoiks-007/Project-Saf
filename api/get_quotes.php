<?php
header('Content-Type: application/json');

require '../includes/Database.php'; // must define $pdo
$pdo = Database::getInstance()->getConnection();

try {
    $stmt = $pdo->query("
        SELECT 
            id,
            quote_no,
            quote_date,
            client_name,
            total,
            quote_status,
            valid_until,
            created_at,
            vat_percentage
        FROM quotes
        ORDER BY created_at DESC
    ");

    $quotes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'data' => $quotes
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to load quotes'
    ]);
}

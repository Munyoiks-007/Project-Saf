<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/auth.php';

// Auth::requireLogin();

$db = Database::getInstance();
$pdo = $db->getConnection();
$user_id = 1;

try {
    // Yearly summary
    $yearlyStmt = $pdo->prepare("
        SELECT 
            YEAR(invoice_date) as year,
            MONTH(invoice_date) as month,
            COUNT(*) as invoice_count,
            SUM(total) as total_revenue,
            SUM(CASE WHEN status = 'paid' THEN total ELSE 0 END) as paid_amount,
            SUM(CASE WHEN status = 'pending' THEN total ELSE 0 END) as pending_amount
        FROM invoices 
        WHERE user_id = ? 
        AND invoice_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
        GROUP BY YEAR(invoice_date), MONTH(invoice_date)
        ORDER BY year DESC, month DESC
    ");
    
    $yearlyStmt->execute([$user_id]);
    $yearlySummary = $yearlyStmt->fetchAll();
    
    // Status summary
    $statusStmt = $pdo->prepare("
        SELECT 
            status,
            COUNT(*) as count,
            SUM(total) as amount
        FROM invoices 
        WHERE user_id = ?
        GROUP BY status
    ");
    
    $statusStmt->execute([$user_id]);
    $statusSummary = $statusStmt->fetchAll();
    
    // Top clients
    $clientsStmt = $pdo->prepare("
        SELECT 
            client_name,
            COUNT(*) as invoice_count,
            SUM(total) as total_spent,
            MAX(invoice_date) as last_invoice
        FROM invoices 
        WHERE user_id = ?
        GROUP BY client_name
        ORDER BY total_spent DESC
        LIMIT 10
    ");
    
    $clientsStmt->execute([$user_id]);
    $topClients = $clientsStmt->fetchAll();
    
    // Current month vs previous month
    $currentMonth = date('Y-m');
    $prevMonth = date('Y-m', strtotime('-1 month'));
    
    $comparisonStmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(invoice_date, '%Y-%m') as month,
            COUNT(*) as invoice_count,
            SUM(total) as revenue
        FROM invoices 
        WHERE user_id = ?
        AND DATE_FORMAT(invoice_date, '%Y-%m') IN (?, ?)
        GROUP BY DATE_FORMAT(invoice_date, '%Y-%m')
    ");
    
    $comparisonStmt->execute([$user_id, $currentMonth, $prevMonth]);
    $comparison = $comparisonStmt->fetchAll();
    
    // Overdue invoices
    $overdueStmt = $pdo->prepare("
        SELECT 
            COUNT(*) as count,
            SUM(total) as amount
        FROM invoices 
        WHERE user_id = ?
        AND status IN ('pending', 'sent')
        AND due_date < CURDATE()
    ");
    
    $overdueStmt->execute([$user_id]);
    $overdue = $overdueStmt->fetch();
    
    jsonResponse([
        'success' => true,
        'data' => [
            'yearly_summary' => $yearlySummary,
            'status_summary' => $statusSummary,
            'top_clients' => $topClients,
            'month_comparison' => $comparison,
            'overdue_invoices' => $overdue,
            'current_month' => $currentMonth,
            'previous_month' => $prevMonth
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Invoice Summary Error: " . $e->getMessage());
    jsonResponse(['success' => false, 'error' => 'Failed to fetch summary data'], 500);
}
?>
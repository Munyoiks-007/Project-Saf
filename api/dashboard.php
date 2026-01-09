<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/auth.php';

// Protect endpoint
Auth::requireLogin();

$db = Database::getInstance();
$pdo = $db->getConnection();
$user = (new Auth())->getCurrentUser();

try {
    // Get date range (default: last 30 days)
    $startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
    $endDate = $_GET['end_date'] ?? date('Y-m-d');
    
    // 1. Overall statistics
    $stats = [];
    
    // Total invoices
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_invoices,
            SUM(total) as total_revenue,
            AVG(total) as avg_invoice_amount
        FROM invoices 
        WHERE user_id = ? AND invoice_date BETWEEN ? AND ?
    ");
    $stmt->execute([$user['id'], $startDate, $endDate]);
    $stats['invoices'] = $stmt->fetch();
    
    // Total clients
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_clients 
        FROM clients 
        WHERE user_id = ?
    ");
    $stmt->execute([$user['id']]);
    $stats['clients'] = $stmt->fetch();
    
    // Total quotations
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_quotations,
            SUM(CASE WHEN status = 'accepted' THEN 1 ELSE 0 END) as accepted_quotations,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as pending_quotations,
            SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected_quotations
        FROM quotations 
        WHERE user_id = ? AND created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$user['id'], $startDate, $endDate]);
    $stats['quotations'] = $stmt->fetch();
    
    // 2. Recent activity
    $stmt = $pdo->prepare("
        (SELECT 
            'invoice' as type,
            invoice_no as reference,
            client_name as client,
            total as amount,
            invoice_date as date,
            created_at
        FROM invoices 
        WHERE user_id = ?
        ORDER BY created_at DESC 
        LIMIT 5)
        
        UNION ALL
        
        (SELECT 
            'quotation' as type,
            quotation_no as reference,
            c.company_name as client,
            q.total as amount,
            q.valid_until as date,
            q.created_at
        FROM quotations q
        LEFT JOIN clients c ON q.client_id = c.id
        WHERE q.user_id = ?
        ORDER BY q.created_at DESC 
        LIMIT 5)
        
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $stmt->execute([$user['id'], $user['id']]);
    $recentActivity = $stmt->fetchAll();
    
    // 3. Monthly revenue (last 6 months)
    $monthlyRevenue = [];
    for ($i = 5; $i >= 0; $i--) {
        $monthStart = date('Y-m-01', strtotime("-$i months"));
        $monthEnd = date('Y-m-t', strtotime("-$i months"));
        $monthName = date('M Y', strtotime("-$i months"));
        
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(SUM(total), 0) as revenue,
                COUNT(*) as invoice_count
            FROM invoices 
            WHERE user_id = ? 
            AND invoice_date BETWEEN ? AND ?
        ");
        $stmt->execute([$user['id'], $monthStart, $monthEnd]);
        $monthData = $stmt->fetch();
        
        $monthlyRevenue[] = [
            'month' => $monthName,
            'revenue' => (float)$monthData['revenue'],
            'invoice_count' => (int)$monthData['invoice_count']
        ];
    }
    
    // 4. Top clients
    $stmt = $pdo->prepare("
        SELECT 
            client_name,
            COUNT(*) as invoice_count,
            SUM(total) as total_spent,
            AVG(total) as avg_invoice_amount
        FROM invoices 
        WHERE user_id = ? AND invoice_date BETWEEN ? AND ?
        GROUP BY client_name
        ORDER BY total_spent DESC
        LIMIT 5
    ");
    $stmt->execute([$user['id'], $startDate, $endDate]);
    $topClients = $stmt->fetchAll();
    
    // 5. Quotation conversion rate
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_quotations,
            SUM(CASE WHEN invoice_id IS NOT NULL THEN 1 ELSE 0 END) as converted_quotations,
            CASE 
                WHEN COUNT(*) > 0 
                THEN ROUND((SUM(CASE WHEN invoice_id IS NOT NULL THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2)
                ELSE 0 
            END as conversion_rate
        FROM quotations 
        WHERE user_id = ? AND created_at BETWEEN ? AND ?
    ");
    $stmt->execute([$user['id'], $startDate, $endDate]);
    $conversionStats = $stmt->fetch();
    
    jsonResponse([
        'success' => true,
        'data' => [
            'stats' => $stats,
            'recent_activity' => $recentActivity,
            'monthly_revenue' => $monthlyRevenue,
            'top_clients' => $topClients,
            'conversion_stats' => $conversionStats,
            'date_range' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]
    ]);
    
} catch (Exception $e) {
    error_log("Dashboard API Error: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'error' => 'Failed to fetch dashboard data',
        'details' => $e->getMessage()
    ], 500);
}
?>
<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/auth.php';

// Protect endpoint - uncomment when authentication is ready
// Auth::requireLogin();

$db = Database::getInstance();
$pdo = $db->getConnection();
$method = $_SERVER['REQUEST_METHOD'];

// For now, use default user ID 1 until auth is implemented
$user_id = 1; // TODO: Replace with actual user ID from auth

// Helper function to generate invoice number
function generateInvoiceNumber() {
    $now = new DateTime();
    return 'INV-' . $now->format('Ymd') . '-' . str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
}

// Helper function to calculate totals
function calculateTotals($items) {
    $subtotal = 0;
    foreach ($items as $item) {
        $subtotal += (float)($item['total'] ?? 0);
    }
    
    // Default tax rate 16% (Kenya VAT)
    $taxRate = 16;
    $taxAmount = ($subtotal * $taxRate) / 100;
    $total = $subtotal + $taxAmount;
    
    return [
        'subtotal' => round($subtotal, 2),
        'tax_rate' => $taxRate,
        'tax_amount' => round($taxAmount, 2),
        'total' => round($total, 2)
    ];
}

try {
    switch ($method) {
        case 'GET':
            // List invoices or get single invoice
            if (isset($_GET['id'])) {
                // Get single invoice
                $id = intval($_GET['id']);
                $stmt = $pdo->prepare("
                    SELECT * FROM invoices 
                    WHERE id = ? AND user_id = ?
                ");
                $stmt->execute([$id, $user_id]);
                $invoice = $stmt->fetch();
                
                if (!$invoice) {
                    jsonResponse(['success' => false, 'error' => 'Invoice not found'], 404);
                }
                
                // Get invoice items
                $stmt = $pdo->prepare("
                    SELECT * FROM invoice_items 
                    WHERE invoice_id = ? 
                    ORDER BY id
                ");
                $stmt->execute([$id]);
                $items = $stmt->fetchAll();
                
                // Format response
                $formattedInvoice = [
                    'id' => (int)$invoice['id'],
                    'invoice_no' => $invoice['invoice_no'],
                    'client_name' => $invoice['client_name'],
                    'client_email' => $invoice['client_email'] ?? '',
                    'client_phone' => $invoice['client_phone'] ?? '',
                    'client_address' => $invoice['client_address'] ?? '',
                    'invoice_date' => $invoice['invoice_date'],
                    'due_date' => $invoice['due_date'] ?? '',
                    'subtotal' => (float)$invoice['subtotal'],
                    'tax_rate' => (float)$invoice['tax_rate'] ?? 16,
                    'tax_amount' => (float)$invoice['tax_amount'],
                    'total' => (float)$invoice['total'],
                    'status' => $invoice['status'] ?? 'pending',
                    'notes' => $invoice['notes'] ?? '',
                    'payment_method' => $invoice['payment_method'] ?? '',
                    'payment_reference' => $invoice['payment_reference'] ?? '',
                    'paid_at' => $invoice['paid_at'] ?? null,
                    'created_at' => $invoice['created_at'],
                    'updated_at' => $invoice['updated_at'],
                    'items' => array_map(function($item) {
                        return [
                            'id' => (int)$item['id'],
                            'item' => $item['item'],
                            'description' => $item['description'] ?? '',
                            'quantity' => (float)$item['quantity'],
                            'unit_price' => (float)$item['unit_price'],
                            'total' => (float)$item['total'],
                            'unit' => $item['unit'] ?? 'pcs'
                        ];
                    }, $items)
                ];
                
                jsonResponse([
                    'success' => true,
                    'data' => $formattedInvoice
                ]);
                
            } else {
                // List invoices with pagination and filters
                $page = max(1, intval($_GET['page'] ?? 1));
                $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
                $offset = ($page - 1) * $limit;
                
                // Filters
                $clientName = $_GET['client_name'] ?? '';
                $status = $_GET['status'] ?? '';
                $dateFrom = $_GET['date_from'] ?? '';
                $dateTo = $_GET['date_to'] ?? '';
                $search = $_GET['search'] ?? '';
                
                // Build query
                $query = "
                    SELECT 
                        id, invoice_no, client_name, invoice_date,
                        subtotal, tax_amount, total, status,
                        created_at, updated_at
                    FROM invoices 
                    WHERE user_id = ?
                ";
                
                $params = [$user_id];
                
                if ($clientName) {
                    $query .= " AND client_name LIKE ?";
                    $params[] = "%$clientName%";
                }
                
                if ($status && $status !== 'all') {
                    $query .= " AND status = ?";
                    $params[] = $status;
                }
                
                if ($dateFrom) {
                    $query .= " AND invoice_date >= ?";
                    $params[] = $dateFrom;
                }
                
                if ($dateTo) {
                    $query .= " AND invoice_date <= ?";
                    $params[] = $dateTo;
                }
                
                if ($search) {
                    $query .= " AND (invoice_no LIKE ? OR client_name LIKE ?)";
                    $searchTerm = "%$search%";
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                }
                
                // Sorting
                $sort = $_GET['sort'] ?? 'created_at';
                $order = $_GET['order'] ?? 'desc';
                $allowedSort = ['invoice_no', 'client_name', 'invoice_date', 'total', 'created_at'];
                $sort = in_array($sort, $allowedSort) ? $sort : 'created_at';
                $order = strtolower($order) === 'asc' ? 'ASC' : 'DESC';
                
                $query .= " ORDER BY $sort $order LIMIT ? OFFSET ?";
                $params[] = $limit;
                $params[] = $offset;
                
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $invoices = $stmt->fetchAll();
                
                // Get total count
                $countQuery = "SELECT COUNT(*) as total FROM invoices WHERE user_id = ?";
                $countParams = [$user_id];
                
                if ($clientName) {
                    $countQuery .= " AND client_name LIKE ?";
                    $countParams[] = "%$clientName%";
                }
                
                if ($status && $status !== 'all') {
                    $countQuery .= " AND status = ?";
                    $countParams[] = $status;
                }
                
                if ($dateFrom) {
                    $countQuery .= " AND invoice_date >= ?";
                    $countParams[] = $dateFrom;
                }
                
                if ($dateTo) {
                    $countQuery .= " AND invoice_date <= ?";
                    $countParams[] = $dateTo;
                }
                
                if ($search) {
                    $countQuery .= " AND (invoice_no LIKE ? OR client_name LIKE ?)";
                    $countParams[] = $searchTerm;
                    $countParams[] = $searchTerm;
                }
                
                $countStmt = $pdo->prepare($countQuery);
                $countStmt->execute($countParams);
                $total = $countStmt->fetch()['total'];
                
                // Format invoices
                $formattedInvoices = array_map(function($invoice) {
                    return [
                        'id' => (int)$invoice['id'],
                        'invoice_no' => $invoice['invoice_no'],
                        'client_name' => $invoice['client_name'],
                        'invoice_date' => $invoice['invoice_date'],
                        'subtotal' => (float)$invoice['subtotal'],
                        'tax_amount' => (float)$invoice['tax_amount'],
                        'total' => (float)$invoice['total'],
                        'status' => $invoice['status'] ?? 'pending',
                        'created_at' => $invoice['created_at'],
                        'updated_at' => $invoice['updated_at']
                    ];
                }, $invoices);
                
                // Get summary statistics
                $statsQuery = "
                    SELECT 
                        COUNT(*) as total_invoices,
                        SUM(total) as total_revenue,
                        SUM(CASE WHEN status = 'paid' THEN total ELSE 0 END) as paid_amount,
                        SUM(CASE WHEN status = 'pending' THEN total ELSE 0 END) as pending_amount,
                        AVG(total) as average_invoice
                    FROM invoices 
                    WHERE user_id = ?
                ";
                
                $statsParams = [$user_id];
                
                if ($dateFrom) {
                    $statsQuery .= " AND invoice_date >= ?";
                    $statsParams[] = $dateFrom;
                }
                
                if ($dateTo) {
                    $statsQuery .= " AND invoice_date <= ?";
                    $statsParams[] = $dateTo;
                }
                
                $statsStmt = $pdo->prepare($statsQuery);
                $statsStmt->execute($statsParams);
                $stats = $statsStmt->fetch();
                
                jsonResponse([
                    'success' => true,
                    'data' => $formattedInvoices,
                    'pagination' => [
                        'total' => (int)$total,
                        'page' => $page,
                        'limit' => $limit,
                        'totalPages' => ceil($total / $limit)
                    ],
                    'stats' => [
                        'total_invoices' => (int)$stats['total_invoices'],
                        'total_revenue' => (float)$stats['total_revenue'] ?? 0,
                        'paid_amount' => (float)$stats['paid_amount'] ?? 0,
                        'pending_amount' => (float)$stats['pending_amount'] ?? 0,
                        'average_invoice' => (float)$stats['average_invoice'] ?? 0
                    ]
                ]);
            }
            break;
            
        case 'POST':
            // Create new invoice
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                jsonResponse(['success' => false, 'error' => 'Invalid JSON input'], 400);
            }
            
            // Validation
            $required = ['client_name', 'invoice_date', 'items'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || empty(trim($data[$field]))) {
                    jsonResponse(['success' => false, 'error' => "Missing required field: $field"], 400);
                }
            }
            
            if (!is_array($data['items']) || count($data['items']) === 0) {
                jsonResponse(['success' => false, 'error' => 'At least one item is required'], 400);
            }
            
            // Validate items
            foreach ($data['items'] as $index => $item) {
                if (empty($item['item'])) {
                    jsonResponse(['success' => false, 'error' => "Item name is required for item #" . ($index + 1)], 400);
                }
                if (!isset($item['quantity']) || !is_numeric($item['quantity']) || $item['quantity'] <= 0) {
                    jsonResponse(['success' => false, 'error' => "Invalid quantity for item: " . $item['item']], 400);
                }
                if (!isset($item['unit_price']) || !is_numeric($item['unit_price']) || $item['unit_price'] < 0) {
                    jsonResponse(['success' => false, 'error' => "Invalid unit price for item: " . $item['item']], 400);
                }
            }
            
            $db->beginTransaction();
            
            try {
                // Generate invoice number if not provided
                $invoiceNo = $data['invoice_no'] ?? generateInvoiceNumber();
                
                // Calculate totals
                $totals = calculateTotals($data['items']);
                
                // Insert invoice
                $stmt = $pdo->prepare("
                    INSERT INTO invoices 
                    (user_id, invoice_no, client_name, client_email, client_phone, client_address,
                     invoice_date, due_date, subtotal, tax_rate, tax_amount, total, status, notes,
                     payment_method, payment_reference)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $dueDate = $data['due_date'] ?? date('Y-m-d', strtotime('+30 days'));
                $status = $data['status'] ?? 'pending';
                
                $stmt->execute([
                    $user_id,
                    $invoiceNo,
                    trim($data['client_name']),
                    $data['client_email'] ?? '',
                    $data['client_phone'] ?? '',
                    $data['client_address'] ?? '',
                    $data['invoice_date'],
                    $dueDate,
                    $totals['subtotal'],
                    $totals['tax_rate'],
                    $totals['tax_amount'],
                    $totals['total'],
                    $status,
                    $data['notes'] ?? '',
                    $data['payment_method'] ?? '',
                    $data['payment_reference'] ?? ''
                ]);
                
                $invoiceId = $pdo->lastInsertId();
                
                // Insert items
                $itemStmt = $pdo->prepare("
                    INSERT INTO invoice_items 
                    (invoice_id, item, description, quantity, unit_price, total, unit)
                    VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($data['items'] as $item) {
                    $itemTotal = (float)$item['quantity'] * (float)$item['unit_price'];
                    
                    $itemStmt->execute([
                        $invoiceId,
                        substr($item['item'], 0, 100),
                        substr($item['description'] ?? '', 0, 500),
                        validateNumber($item['quantity'], 'quantity'),
                        validateNumber($item['unit_price'], 'unit_price'),
                        $itemTotal,
                        $item['unit'] ?? 'pcs'
                    ]);
                }
                
                $db->commit();
                
                // Get created invoice
                $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
                $stmt->execute([$invoiceId]);
                $invoice = $stmt->fetch();
                
                jsonResponse([
                    'success' => true,
                    'message' => 'Invoice created successfully',
                    'data' => [
                        'id' => $invoiceId,
                        'invoice_no' => $invoiceNo,
                        'invoice' => $invoice
                    ]
                ], 201);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'PUT':
            // Update invoice
            if (!isset($_GET['id'])) {
                jsonResponse(['success' => false, 'error' => 'Invoice ID required'], 400);
            }
            
            $id = intval($_GET['id']);
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                jsonResponse(['success' => false, 'error' => 'Invalid JSON input'], 400);
            }
            
            // Check if invoice exists
            $stmt = $pdo->prepare("SELECT id FROM invoices WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            if (!$stmt->fetch()) {
                jsonResponse(['success' => false, 'error' => 'Invoice not found or access denied'], 404);
            }
            
            $db->beginTransaction();
            
            try {
                // Build update query
                $fields = [];
                $params = [];
                
                $updatable = [
                    'client_name', 'client_email', 'client_phone', 'client_address',
                    'invoice_date', 'due_date', 'status', 'notes',
                    'payment_method', 'payment_reference'
                ];
                
                foreach ($updatable as $field) {
                    if (isset($data[$field])) {
                        $fields[] = "$field = ?";
                        $params[] = $data[$field];
                    }
                }
                
                // If items are being updated, recalculate totals
                if (isset($data['items']) && is_array($data['items'])) {
                    // Validate items
                    foreach ($data['items'] as $index => $item) {
                        if (empty($item['item'])) {
                            throw new Exception("Item name is required for item #" . ($index + 1));
                        }
                        if (!isset($item['quantity']) || !is_numeric($item['quantity']) || $item['quantity'] <= 0) {
                            throw new Exception("Invalid quantity for item: " . $item['item']);
                        }
                        if (!isset($item['unit_price']) || !is_numeric($item['unit_price']) || $item['unit_price'] < 0) {
                            throw new Exception("Invalid unit price for item: " . $item['item']);
                        }
                    }
                    
                    $totals = calculateTotals($data['items']);
                    
                    $fields[] = "subtotal = ?";
                    $params[] = $totals['subtotal'];
                    
                    $fields[] = "tax_rate = ?";
                    $params[] = $totals['tax_rate'];
                    
                    $fields[] = "tax_amount = ?";
                    $params[] = $totals['tax_amount'];
                    
                    $fields[] = "total = ?";
                    $params[] = $totals['total'];
                }
                
                // Mark as paid if payment info provided
                if (isset($data['payment_reference']) && !empty($data['payment_reference']) && isset($data['payment_method'])) {
                    $fields[] = "status = 'paid'";
                    $fields[] = "paid_at = NOW()";
                }
                
                if (empty($fields)) {
                    jsonResponse(['success' => false, 'error' => 'No fields to update'], 400);
                }
                
                $params[] = $id;
                $params[] = $user_id;
                
                $query = "UPDATE invoices SET " . implode(', ', $fields) . " WHERE id = ? AND user_id = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                
                // Update items if provided
                if (isset($data['items']) && is_array($data['items'])) {
                    // Delete existing items
                    $stmt = $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
                    $stmt->execute([$id]);
                    
                    // Insert new items
                    $itemStmt = $pdo->prepare("
                        INSERT INTO invoice_items 
                        (invoice_id, item, description, quantity, unit_price, total, unit)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    foreach ($data['items'] as $item) {
                        $itemTotal = (float)$item['quantity'] * (float)$item['unit_price'];
                        
                        $itemStmt->execute([
                            $id,
                            substr($item['item'], 0, 100),
                            substr($item['description'] ?? '', 0, 500),
                            validateNumber($item['quantity'], 'quantity'),
                            validateNumber($item['unit_price'], 'unit_price'),
                            $itemTotal,
                            $item['unit'] ?? 'pcs'
                        ]);
                    }
                }
                
                $db->commit();
                
                // Get updated invoice
                $stmt = $pdo->prepare("SELECT * FROM invoices WHERE id = ?");
                $stmt->execute([$id]);
                $invoice = $stmt->fetch();
                
                jsonResponse([
                    'success' => true,
                    'message' => 'Invoice updated successfully',
                    'data' => $invoice
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
                jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
            }
            break;
            
        case 'DELETE':
            // Delete invoice
            if (!isset($_GET['id'])) {
                jsonResponse(['success' => false, 'error' => 'Invoice ID required'], 400);
            }
            
            $id = intval($_GET['id']);
            
            // Check if invoice exists
            $stmt = $pdo->prepare("SELECT id FROM invoices WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            if (!$stmt->fetch()) {
                jsonResponse(['success' => false, 'error' => 'Invoice not found or access denied'], 404);
            }
            
            $db->beginTransaction();
            
            try {
                // Delete items first
                $stmt = $pdo->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
                $stmt->execute([$id]);
                
                // Delete invoice
                $stmt = $pdo->prepare("DELETE FROM invoices WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $user_id]);
                
                $db->commit();
                
                jsonResponse([
                    'success' => true,
                    'message' => 'Invoice deleted successfully'
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
                jsonResponse(['success' => false, 'error' => 'Failed to delete invoice'], 500);
            }
            break;
            
        case 'PATCH':
            // Partial update (e.g., update status)
            if (!isset($_GET['id'])) {
                jsonResponse(['success' => false, 'error' => 'Invoice ID required'], 400);
            }
            
            $id = intval($_GET['id']);
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!$data) {
                jsonResponse(['success' => false, 'error' => 'Invalid JSON input'], 400);
            }
            
            // Check if invoice exists
            $stmt = $pdo->prepare("SELECT id, status FROM invoices WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user_id]);
            $invoice = $stmt->fetch();
            
            if (!$invoice) {
                jsonResponse(['success' => false, 'error' => 'Invoice not found or access denied'], 404);
            }
            
            try {
                $fields = [];
                $params = [];
                
                // Update status
                if (isset($data['status'])) {
                    $allowedStatuses = ['draft', 'pending', 'sent', 'paid', 'cancelled', 'overdue'];
                    if (!in_array($data['status'], $allowedStatuses)) {
                        jsonResponse(['success' => false, 'error' => 'Invalid status'], 400);
                    }
                    
                    $fields[] = "status = ?";
                    $params[] = $data['status'];
                    
                    // Set paid_at if marking as paid
                    if ($data['status'] === 'paid' && $invoice['status'] !== 'paid') {
                        $fields[] = "paid_at = NOW()";
                    }
                }
                
                // Update payment info
                if (isset($data['payment_method'])) {
                    $fields[] = "payment_method = ?";
                    $params[] = $data['payment_method'];
                }
                
                if (isset($data['payment_reference'])) {
                    $fields[] = "payment_reference = ?";
                    $params[] = $data['payment_reference'];
                }
                
                if (empty($fields)) {
                    jsonResponse(['success' => false, 'error' => 'No fields to update'], 400);
                }
                
                $params[] = $id;
                $params[] = $user_id;
                
                $query = "UPDATE invoices SET " . implode(', ', $fields) . " WHERE id = ? AND user_id = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                
                jsonResponse([
                    'success' => true,
                    'message' => 'Invoice updated successfully'
                ]);
                
            } catch (Exception $e) {
                jsonResponse(['success' => false, 'error' => 'Failed to update invoice'], 500);
            }
            break;
            
        default:
            jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    error_log("Invoices API Error: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'error' => 'Internal server error',
        'details' => $e->getMessage()
    ], 500);
}
?>
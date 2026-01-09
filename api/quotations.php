<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/auth.php';

// Protect endpoint
Auth::requireLogin();

$db = Database::getInstance();
$pdo = $db->getConnection();
$method = $_SERVER['REQUEST_METHOD'];
$user = (new Auth())->getCurrentUser();

// Generate quotation number
function generateQuotationNumber() {
    $now = new DateTime();
    return 'QUO-' . $now->format('Ymd') . '-' . str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
}

try {
    switch ($method) {
        case 'GET':
            // List quotations or get single quotation
            if (isset($_GET['id'])) {
                // Get single quotation
                $id = intval($_GET['id']);
                $stmt = $pdo->prepare("
                    SELECT q.*, c.company_name, c.contact_person, c.email as client_email
                    FROM quotations q
                    LEFT JOIN clients c ON q.client_id = c.id
                    WHERE q.id = ? AND q.user_id = ?
                ");
                $stmt->execute([$id, $user['id']]);
                $quotation = $stmt->fetch();
                
                if (!$quotation) {
                    jsonResponse(['success' => false, 'error' => 'Quotation not found'], 404);
                }
                
                // Get quotation items
                $stmt = $pdo->prepare("
                    SELECT * FROM quotation_items 
                    WHERE quotation_id = ? 
                    ORDER BY id
                ");
                $stmt->execute([$id]);
                $items = $stmt->fetchAll();
                
                jsonResponse([
                    'success' => true,
                    'data' => [
                        'quotation' => $quotation,
                        'items' => $items
                    ]
                ]);
                
            } else {
                // List quotations with pagination
                $page = max(1, intval($_GET['page'] ?? 1));
                $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
                $offset = ($page - 1) * $limit;
                
                $status = $_GET['status'] ?? '';
                $clientId = $_GET['client_id'] ?? '';
                $search = $_GET['search'] ?? '';
                
                // Build query
                $query = "
                    SELECT q.*, c.company_name, c.contact_person
                    FROM quotations q
                    LEFT JOIN clients c ON q.client_id = c.id
                    WHERE q.user_id = ?
                ";
                
                $params = [$user['id']];
                
                if ($status && $status !== 'all') {
                    $query .= " AND q.status = ?";
                    $params[] = $status;
                }
                
                if ($clientId) {
                    $query .= " AND q.client_id = ?";
                    $params[] = intval($clientId);
                }
                
                if ($search) {
                    $query .= " AND (q.quotation_no LIKE ? OR c.company_name LIKE ?)";
                    $searchTerm = "%$search%";
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                }
                
                $query .= " ORDER BY q.created_at DESC LIMIT ? OFFSET ?";
                $params[] = $limit;
                $params[] = $offset;
                
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $quotations = $stmt->fetchAll();
                
                // Get total count
                $countQuery = "
                    SELECT COUNT(*) as total 
                    FROM quotations q
                    LEFT JOIN clients c ON q.client_id = c.id
                    WHERE q.user_id = ?
                ";
                
                $countParams = [$user['id']];
                
                if ($status && $status !== 'all') {
                    $countQuery .= " AND q.status = ?";
                    $countParams[] = $status;
                }
                
                if ($clientId) {
                    $countQuery .= " AND q.client_id = ?";
                    $countParams[] = intval($clientId);
                }
                
                if ($search) {
                    $countQuery .= " AND (q.quotation_no LIKE ? OR c.company_name LIKE ?)";
                    $countParams[] = $searchTerm;
                    $countParams[] = $searchTerm;
                }
                
                $countStmt = $pdo->prepare($countQuery);
                $countStmt->execute($countParams);
                $total = $countStmt->fetch()['total'];
                
                jsonResponse([
                    'success' => true,
                    'data' => $quotations,
                    'pagination' => [
                        'total' => (int)$total,
                        'page' => $page,
                        'limit' => $limit,
                        'totalPages' => ceil($total / $limit)
                    ]
                ]);
            }
            break;
            
        case 'POST':
            // Create new quotation
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validation
            if (!isset($data['client_id']) || empty($data['client_id'])) {
                jsonResponse(['success' => false, 'error' => 'Client ID is required'], 400);
            }
            
            if (!isset($data['items']) || !is_array($data['items']) || count($data['items']) === 0) {
                jsonResponse(['success' => false, 'error' => 'At least one item is required'], 400);
            }
            
            $clientId = intval($data['client_id']);
            
            // Verify client belongs to user
            $stmt = $pdo->prepare("SELECT id FROM clients WHERE id = ? AND user_id = ?");
            $stmt->execute([$clientId, $user['id']]);
            if (!$stmt->fetch()) {
                jsonResponse(['success' => false, 'error' => 'Invalid client or access denied'], 400);
            }
            
            $db->beginTransaction();
            
            try {
                // Generate quotation number
                $quotationNo = $data['quotation_no'] ?? generateQuotationNumber();
                
                // Insert quotation
                $stmt = $pdo->prepare("
                    INSERT INTO quotations 
                    (user_id, client_id, quotation_no, valid_until, subtotal, tax, total, status, notes)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                
                $validUntil = $data['valid_until'] ?? date('Y-m-d', strtotime('+30 days'));
                $status = $data['status'] ?? 'draft';
                $notes = $data['notes'] ?? '';
                
                $stmt->execute([
                    $user['id'],
                    $clientId,
                    $quotationNo,
                    $validUntil,
                    validateNumber($data['subtotal'] ?? 0, 'subtotal'),
                    validateNumber($data['tax'] ?? 0, 'tax'),
                    validateNumber($data['total'] ?? 0, 'total'),
                    $status,
                    $notes
                ]);
                
                $quotationId = $pdo->lastInsertId();
                
                // Insert items
                $itemStmt = $pdo->prepare("
                    INSERT INTO quotation_items 
                    (quotation_id, item, description, quantity, unit_price, total)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($data['items'] as $item) {
                    $itemStmt->execute([
                        $quotationId,
                        substr($item['item'] ?? 'Unspecified Item', 0, 100),
                        substr($item['description'] ?? '', 0, 500),
                        validateNumber($item['quantity'] ?? 0, 'quantity'),
                        validateNumber($item['unit_price'] ?? 0, 'unit_price'),
                        validateNumber($item['total'] ?? 0, 'item total')
                    ]);
                }
                
                $db->commit();
                
                // Get created quotation
                $stmt = $pdo->prepare("
                    SELECT q.*, c.company_name, c.contact_person
                    FROM quotations q
                    LEFT JOIN clients c ON q.client_id = c.id
                    WHERE q.id = ?
                ");
                $stmt->execute([$quotationId]);
                $quotation = $stmt->fetch();
                
                jsonResponse([
                    'success' => true,
                    'message' => 'Quotation created successfully',
                    'data' => $quotation
                ], 201);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'PUT':
            // Update quotation
            if (!isset($_GET['id'])) {
                jsonResponse(['success' => false, 'error' => 'Quotation ID required'], 400);
            }
            
            $id = intval($_GET['id']);
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Verify ownership
            $stmt = $pdo->prepare("SELECT id FROM quotations WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user['id']]);
            if (!$stmt->fetch()) {
                jsonResponse(['success' => false, 'error' => 'Quotation not found or access denied'], 404);
            }
            
            $db->beginTransaction();
            
            try {
                // Update quotation
                $fields = [];
                $params = [];
                
                $updatable = ['valid_until', 'subtotal', 'tax', 'total', 'status', 'notes'];
                foreach ($updatable as $field) {
                    if (isset($data[$field])) {
                        $fields[] = "$field = ?";
                        if (in_array($field, ['subtotal', 'tax', 'total'])) {
                            $params[] = validateNumber($data[$field], $field);
                        } else {
                            $params[] = $data[$field];
                        }
                    }
                }
                
                if (empty($fields)) {
                    jsonResponse(['success' => false, 'error' => 'No fields to update'], 400);
                }
                
                $params[] = $id;
                $params[] = $user['id'];
                
                $query = "UPDATE quotations SET " . implode(', ', $fields) . " WHERE id = ? AND user_id = ?";
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                
                // Update items if provided
                if (isset($data['items']) && is_array($data['items'])) {
                    // Delete existing items
                    $stmt = $pdo->prepare("DELETE FROM quotation_items WHERE quotation_id = ?");
                    $stmt->execute([$id]);
                    
                    // Insert new items
                    $itemStmt = $pdo->prepare("
                        INSERT INTO quotation_items 
                        (quotation_id, item, description, quantity, unit_price, total)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    
                    foreach ($data['items'] as $item) {
                        $itemStmt->execute([
                            $id,
                            substr($item['item'] ?? 'Unspecified Item', 0, 100),
                            substr($item['description'] ?? '', 0, 500),
                            validateNumber($item['quantity'] ?? 0, 'quantity'),
                            validateNumber($item['unit_price'] ?? 0, 'unit_price'),
                            validateNumber($item['total'] ?? 0, 'item total')
                        ]);
                    }
                }
                
                $db->commit();
                
                // Get updated quotation
                $stmt = $pdo->prepare("
                    SELECT q.*, c.company_name, c.contact_person
                    FROM quotations q
                    LEFT JOIN clients c ON q.client_id = c.id
                    WHERE q.id = ?
                ");
                $stmt->execute([$id]);
                $quotation = $stmt->fetch();
                
                jsonResponse([
                    'success' => true,
                    'message' => 'Quotation updated successfully',
                    'data' => $quotation
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'DELETE':
            // Delete quotation
            if (!isset($_GET['id'])) {
                jsonResponse(['success' => false, 'error' => 'Quotation ID required'], 400);
            }
            
            $id = intval($_GET['id']);
            
            // Verify ownership
            $stmt = $pdo->prepare("SELECT id FROM quotations WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user['id']]);
            if (!$stmt->fetch()) {
                jsonResponse(['success' => false, 'error' => 'Quotation not found or access denied'], 404);
            }
            
            // Check if quotation is already converted to invoice
            $stmt = $pdo->prepare("SELECT invoice_id FROM quotations WHERE id = ?");
            $stmt->execute([$id]);
            $quotation = $stmt->fetch();
            
            if ($quotation['invoice_id']) {
                jsonResponse([
                    'success' => false,
                    'error' => 'Cannot delete quotation that has been converted to invoice'
                ], 400);
            }
            
            $db->beginTransaction();
            
            try {
                // Delete items first
                $stmt = $pdo->prepare("DELETE FROM quotation_items WHERE quotation_id = ?");
                $stmt->execute([$id]);
                
                // Delete quotation
                $stmt = $pdo->prepare("DELETE FROM quotations WHERE id = ? AND user_id = ?");
                $stmt->execute([$id, $user['id']]);
                
                $db->commit();
                
                jsonResponse([
                    'success' => true,
                    'message' => 'Quotation deleted successfully'
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        case 'POST':
            // Convert quotation to invoice
            if (!isset($_GET['id'])) {
                jsonResponse(['success' => false, 'error' => 'Quotation ID required'], 400);
            }
            
            if (!isset($_GET['action']) || $_GET['action'] !== 'convert') {
                jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
            }
            
            $id = intval($_GET['id']);
            
            // Verify ownership
            $stmt = $pdo->prepare("
                SELECT q.*, c.company_name, c.contact_person, c.email, c.phone, c.address
                FROM quotations q
                LEFT JOIN clients c ON q.client_id = c.id
                WHERE q.id = ? AND q.user_id = ?
            ");
            $stmt->execute([$id, $user['id']]);
            $quotation = $stmt->fetch();
            
            if (!$quotation) {
                jsonResponse(['success' => false, 'error' => 'Quotation not found or access denied'], 404);
            }
            
            // Check if already converted
            if ($quotation['invoice_id']) {
                jsonResponse([
                    'success' => false,
                    'error' => 'Quotation already converted to invoice',
                    'invoice_id' => $quotation['invoice_id']
                ], 400);
            }
            
            $db->beginTransaction();
            
            try {
                // Generate invoice number
                require_once __DIR__ . '/../includes/functions.php';
                $invoiceNo = generateInvoiceNumber();
                
                // Create invoice
                $stmt = $pdo->prepare("
                    INSERT INTO invoices 
                    (user_id, invoice_no, client_name, invoice_date, subtotal, tax, total)
                    VALUES (?, ?, ?, CURDATE(), ?, ?, ?)
                ");
                
                $stmt->execute([
                    $user['id'],
                    $invoiceNo,
                    $quotation['company_name'],
                    $quotation['subtotal'],
                    $quotation['tax'],
                    $quotation['total']
                ]);
                
                $invoiceId = $pdo->lastInsertId();
                
                // Get quotation items and copy to invoice
                $stmt = $pdo->prepare("SELECT * FROM quotation_items WHERE quotation_id = ?");
                $stmt->execute([$id]);
                $items = $stmt->fetchAll();
                
                $itemStmt = $pdo->prepare("
                    INSERT INTO invoice_items 
                    (invoice_id, item, description, quantity, unit_price, total)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                
                foreach ($items as $item) {
                    $itemStmt->execute([
                        $invoiceId,
                        $item['item'],
                        $item['description'],
                        $item['quantity'],
                        $item['unit_price'],
                        $item['total']
                    ]);
                }
                
                // Update quotation with invoice reference
                $stmt = $pdo->prepare("UPDATE quotations SET invoice_id = ? WHERE id = ?");
                $stmt->execute([$invoiceId, $id]);
                
                $db->commit();
                
                jsonResponse([
                    'success' => true,
                    'message' => 'Quotation converted to invoice successfully',
                    'data' => [
                        'invoice_id' => $invoiceId,
                        'invoice_no' => $invoiceNo,
                        'quotation_id' => $id
                    ]
                ]);
                
            } catch (Exception $e) {
                $db->rollBack();
                throw $e;
            }
            break;
            
        default:
            jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    error_log("Quotations API Error: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'error' => 'Internal server error',
        'details' => $e->getMessage()
    ], 500);
}
?>
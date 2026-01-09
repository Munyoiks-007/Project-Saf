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

try {
    switch ($method) {
        case 'GET':
            // List clients or get single client
            if (isset($_GET['id'])) {
                // Get single client
                $id = intval($_GET['id']);
                $stmt = $pdo->prepare("
                    SELECT c.*, u.username as created_by
                    FROM clients c
                    LEFT JOIN users u ON c.user_id = u.id
                    WHERE c.id = ? AND c.user_id = ?
                ");
                $stmt->execute([$id, $user['id']]);
                $client = $stmt->fetch();
                
                if (!$client) {
                    jsonResponse(['success' => false, 'error' => 'Client not found'], 404);
                }
                
                // Get client projects/invoices count
                $stmt = $pdo->prepare("
                    SELECT COUNT(*) as invoice_count, 
                           SUM(total) as total_revenue
                    FROM invoices 
                    WHERE client_name = ?
                ");
                $stmt->execute([$client['company_name']]);
                $stats = $stmt->fetch();
                
                jsonResponse([
                    'success' => true,
                    'data' => [
                        'client' => $client,
                        'stats' => $stats
                    ]
                ]);
                
            } else {
                // List clients with pagination
                $page = max(1, intval($_GET['page'] ?? 1));
                $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
                $offset = ($page - 1) * $limit;
                
                $search = $_GET['search'] ?? '';
                $sort = $_GET['sort'] ?? 'created_at';
                $order = $_GET['order'] ?? 'desc';
                
                // Build query
                $query = "
                    SELECT c.*, 
                           COUNT(i.id) as invoice_count,
                           COALESCE(SUM(i.total), 0) as total_revenue
                    FROM clients c
                    LEFT JOIN invoices i ON c.company_name = i.client_name
                    WHERE c.user_id = ?
                ";
                
                $params = [$user['id']];
                
                if ($search) {
                    $query .= " AND (c.company_name LIKE ? OR c.contact_person LIKE ? OR c.email LIKE ?)";
                    $searchTerm = "%$search%";
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                    $params[] = $searchTerm;
                }
                
                $query .= " GROUP BY c.id";
                
                // Validate sort field
                $allowedSort = ['company_name', 'contact_person', 'created_at', 'total_revenue'];
                $sort = in_array($sort, $allowedSort) ? $sort : 'created_at';
                $order = strtolower($order) === 'asc' ? 'ASC' : 'DESC';
                
                $query .= " ORDER BY $sort $order LIMIT ? OFFSET ?";
                $params[] = $limit;
                $params[] = $offset;
                
                $stmt = $pdo->prepare($query);
                $stmt->execute($params);
                $clients = $stmt->fetchAll();
                
                // Get total count
                $countQuery = "
                    SELECT COUNT(*) as total 
                    FROM clients 
                    WHERE user_id = ?
                ";
                
                if ($search) {
                    $countQuery .= " AND (company_name LIKE ? OR contact_person LIKE ? OR email LIKE ?)";
                }
                
                $countParams = [$user['id']];
                if ($search) {
                    $countParams[] = $searchTerm;
                    $countParams[] = $searchTerm;
                    $countParams[] = $searchTerm;
                }
                
                $countStmt = $pdo->prepare($countQuery);
                $countStmt->execute($countParams);
                $total = $countStmt->fetch()['total'];
                
                jsonResponse([
                    'success' => true,
                    'data' => $clients,
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
            // Create new client
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validation
            $required = ['company_name', 'contact_person', 'email'];
            foreach ($required as $field) {
                if (!isset($data[$field]) || empty(trim($data[$field]))) {
                    jsonResponse(['success' => false, 'error' => "Missing required field: $field"], 400);
                }
            }
            
            // Check if company already exists for this user
            $stmt = $pdo->prepare("SELECT id FROM clients WHERE company_name = ? AND user_id = ?");
            $stmt->execute([$data['company_name'], $user['id']]);
            if ($stmt->fetch()) {
                jsonResponse(['success' => false, 'error' => 'Company already exists'], 409);
            }
            
            // Validate email
            if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                jsonResponse(['success' => false, 'error' => 'Invalid email address'], 400);
            }
            
            // Insert client
            $stmt = $pdo->prepare("
                INSERT INTO clients 
                (user_id, company_name, contact_person, email, phone, address, tax_id)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $user['id'],
                trim($data['company_name']),
                trim($data['contact_person']),
                trim($data['email']),
                $data['phone'] ?? '',
                $data['address'] ?? '',
                $data['tax_id'] ?? ''
            ]);
            
            $clientId = $pdo->lastInsertId();
            
            // Get created client
            $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
            $stmt->execute([$clientId]);
            $client = $stmt->fetch();
            
            jsonResponse([
                'success' => true,
                'message' => 'Client created successfully',
                'data' => $client
            ], 201);
            break;
            
        case 'PUT':
            // Update client
            if (!isset($_GET['id'])) {
                jsonResponse(['success' => false, 'error' => 'Client ID required'], 400);
            }
            
            $id = intval($_GET['id']);
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Verify ownership
            $stmt = $pdo->prepare("SELECT id FROM clients WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user['id']]);
            if (!$stmt->fetch()) {
                jsonResponse(['success' => false, 'error' => 'Client not found or access denied'], 404);
            }
            
            // Build update query
            $fields = [];
            $params = [];
            
            $updatable = ['company_name', 'contact_person', 'email', 'phone', 'address', 'tax_id'];
            foreach ($updatable as $field) {
                if (isset($data[$field])) {
                    $fields[] = "$field = ?";
                    $params[] = trim($data[$field]);
                }
            }
            
            if (empty($fields)) {
                jsonResponse(['success' => false, 'error' => 'No fields to update'], 400);
            }
            
            // Validate email if being updated
            if (isset($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
                jsonResponse(['success' => false, 'error' => 'Invalid email address'], 400);
            }
            
            $params[] = $id;
            $params[] = $user['id'];
            
            $query = "UPDATE clients SET " . implode(', ', $fields) . " WHERE id = ? AND user_id = ?";
            $stmt = $pdo->prepare($query);
            $stmt->execute($params);
            
            // Get updated client
            $stmt = $pdo->prepare("SELECT * FROM clients WHERE id = ?");
            $stmt->execute([$id]);
            $client = $stmt->fetch();
            
            jsonResponse([
                'success' => true,
                'message' => 'Client updated successfully',
                'data' => $client
            ]);
            break;
            
        case 'DELETE':
            // Delete client
            if (!isset($_GET['id'])) {
                jsonResponse(['success' => false, 'error' => 'Client ID required'], 400);
            }
            
            $id = intval($_GET['id']);
            
            // Check if client has invoices
            $stmt = $pdo->prepare("
                SELECT c.company_name 
                FROM clients c 
                WHERE c.id = ? AND c.user_id = ?
            ");
            $stmt->execute([$id, $user['id']]);
            $client = $stmt->fetch();
            
            if (!$client) {
                jsonResponse(['success' => false, 'error' => 'Client not found or access denied'], 404);
            }
            
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as invoice_count 
                FROM invoices 
                WHERE client_name = ?
            ");
            $stmt->execute([$client['company_name']]);
            $invoiceCount = $stmt->fetch()['invoice_count'];
            
            if ($invoiceCount > 0) {
                jsonResponse([
                    'success' => false,
                    'error' => 'Cannot delete client with existing invoices',
                    'invoice_count' => $invoiceCount
                ], 400);
            }
            
            // Delete client
            $stmt = $pdo->prepare("DELETE FROM clients WHERE id = ? AND user_id = ?");
            $stmt->execute([$id, $user['id']]);
            
            jsonResponse([
                'success' => true,
                'message' => 'Client deleted successfully'
            ]);
            break;
            
        default:
            jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
    }
} catch (Exception $e) {
    error_log("Clients API Error: " . $e->getMessage());
    jsonResponse([
        'success' => false,
        'error' => 'Internal server error',
        'details' => $e->getMessage()
    ], 500);
}
?>
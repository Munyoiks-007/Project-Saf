<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../includes/Database.php';

try {
    /* ================= DATABASE ================= */
    $pdo = Database::getInstance()->getConnection();

    if (!$pdo) {
        throw new Exception('Database connection failed');
    }

    /* ================= READ RAW INPUT ================= */
    $rawInput = file_get_contents('php://input');

    if ($rawInput === false || trim($rawInput) === '') {
        throw new Exception('Empty request body');
    }

    $data = json_decode($rawInput, true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Invalid JSON payload: ' . json_last_error_msg());
    }

    /* ================= LOG INCOMING DATA FOR DEBUGGING ================= */
    error_log('Received quote data: ' . print_r($data, true));

    /* ================= BASIC VALIDATION ================= */
    $requiredFields = ['quote_no', 'client_name', 'client_phone', 'project_address', 'valid_until', 'scope_of_work'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            throw new Exception(ucfirst(str_replace('_', ' ', $field)) . ' is required');
        }
    }

    if (!isset($data['items']) || !is_array($data['items']) || count($data['items']) === 0) {
        throw new Exception('At least one quote item is required');
    }

    // Validate items
    foreach ($data['items'] as $index => $item) {
        if (empty($item['description']) || trim($item['description']) === '') {
            throw new Exception('Item description is required for item #' . ($index + 1));
        }
        if (empty($item['quantity']) || floatval($item['quantity']) <= 0) {
            throw new Exception('Valid quantity is required for item: ' . $item['description']);
        }
        if (empty($item['unit_price']) || floatval($item['unit_price']) < 0) {
            throw new Exception('Valid unit price is required for item: ' . $item['description']);
        }
    }

    /* ================= SANITIZE AND VALIDATE NUMBERS ================= */
    $subtotal = isset($data['subtotal']) ? floatval($data['subtotal']) : 0;
    $discount = isset($data['discount']) ? floatval($data['discount']) : 0;
    $tax_amount = isset($data['tax_amount']) ? floatval($data['tax_amount']) : 0;
    $total = isset($data['total']) ? floatval($data['total']) : 0;
    $vat_percentage = isset($data['vat_percentage']) ? floatval($data['vat_percentage']) : 0;
    $deposit_percentage = isset($data['deposit_percentage']) ? floatval($data['deposit_percentage']) : 0;
    $warranty_months = isset($data['warranty_months']) ? intval($data['warranty_months']) : 0;
    $project_duration = isset($data['project_duration']) && $data['project_duration'] !== '' 
        ? intval($data['project_duration']) 
        : null;

    // Validate numeric ranges
    if ($discount < 0) throw new Exception('Discount cannot be negative');
    if ($tax_amount < 0) throw new Exception('Tax amount cannot be negative');
    if ($total < 0) throw new Exception('Total cannot be negative');
    if ($vat_percentage < 0 || $vat_percentage > 100) throw new Exception('VAT percentage must be between 0 and 100');
    if ($deposit_percentage < 0 || $deposit_percentage > 100) throw new Exception('Deposit percentage must be between 0 and 100');
    if ($warranty_months < 0) throw new Exception('Warranty months cannot be negative');

    /* ================= VALIDATE AND FORMAT DATES ================= */
    // Fix date format - ensure it's YYYY-MM-DD
    function formatDateForMySQL($dateString) {
        if (empty($dateString)) return null;
        
        // If it's already in YYYY-MM-DD format
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateString)) {
            return $dateString;
        }
        
        // If it's in DD/MM/YYYY format
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dateString)) {
            $parts = explode('/', $dateString);
            return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
        }
        
        throw new Exception('Invalid date format: ' . $dateString);
    }

    $quote_date = isset($data['quote_date']) ? formatDateForMySQL($data['quote_date']) : date('Y-m-d');
    $valid_until = isset($data['valid_until']) ? formatDateForMySQL($data['valid_until']) : null;
    $project_start_date = isset($data['project_start_date']) && $data['project_start_date'] !== '' 
        ? formatDateForMySQL($data['project_start_date']) 
        : null;

    /* ================= HANDLE BOOLEAN VALUES ================= */
    // Convert apply_tax to proper boolean (0 or 1)
    $apply_tax = isset($data['apply_tax']) 
        ? ($data['apply_tax'] === true || $data['apply_tax'] === 'true' || $data['apply_tax'] === '1' || $data['apply_tax'] === 1 ? 1 : 0)
        : 0;

    /* ================= CHECK FOR DUPLICATE QUOTE NUMBER ================= */
    $checkStmt = $pdo->prepare("SELECT id FROM quotes WHERE quote_no = ?");
    $checkStmt->execute([$data['quote_no']]);
    if ($checkStmt->rowCount() > 0) {
        throw new Exception('Quote number already exists. Please generate a new quote number.');
    }

    /* ================= TRANSACTION START ================= */
    $pdo->beginTransaction();

    /* ================= INSERT QUOTE ================= */
    $stmt = $pdo->prepare("
        INSERT INTO quotes (
            quote_no, quote_date, client_ref, client_name, contact_person,
            client_email, client_phone, project_address,
            valid_until, project_start_date, project_duration,
            quote_status, priority_level,
            subtotal, discount, tax_amount, total,
            vat_percentage, apply_tax,
            deposit_percentage,
            pricing_notes, payment_terms,
            warranty_months, special_terms, scope_of_work,
            created_at, updated_at
        ) VALUES (
            :quote_no, :quote_date, :client_ref, :client_name, :contact_person,
            :client_email, :client_phone, :project_address,
            :valid_until, :project_start_date, :project_duration,
            :quote_status, :priority_level,
            :subtotal, :discount, :tax_amount, :total,
            :vat_percentage, :apply_tax,
            :deposit_percentage,
            :pricing_notes, :payment_terms,
            :warranty_months, :special_terms, :scope_of_work,
            NOW(), NOW()
        )
    ");

    $success = $stmt->execute([
        ':quote_no' => $data['quote_no'],
        ':quote_date' => $quote_date,
        ':client_ref' => isset($data['client_ref']) && $data['client_ref'] !== '' ? $data['client_ref'] : null,
        ':client_name' => trim($data['client_name']),
        ':contact_person' => isset($data['contact_person']) && $data['contact_person'] !== '' ? trim($data['contact_person']) : null,
        ':client_email' => isset($data['client_email']) && $data['client_email'] !== '' ? 
            (filter_var(trim($data['client_email']), FILTER_VALIDATE_EMAIL) ? $data['client_email'] : null) 
            : null,
        ':client_phone' => trim($data['client_phone']),
        ':project_address' => trim($data['project_address']),
        ':valid_until' => $valid_until,
        ':project_start_date' => $project_start_date,
        ':project_duration' => $project_duration,
        ':quote_status' => $data['quote_status'] ?? 'draft',
        ':priority_level' => $data['priority_level'] ?? 'medium',
        ':subtotal' => $subtotal,
        ':discount' => $discount,
        ':tax_amount' => $tax_amount,
        ':total' => $total,
        ':vat_percentage' => $vat_percentage,
        ':apply_tax' => $apply_tax,
        ':deposit_percentage' => $deposit_percentage,
        ':pricing_notes' => isset($data['pricing_notes']) && $data['pricing_notes'] !== '' ? trim($data['pricing_notes']) : null,
        ':payment_terms' => $data['payment_terms'] ?? '30_70',
        ':warranty_months' => $warranty_months,
        ':special_terms' => isset($data['special_terms']) && $data['special_terms'] !== '' ? trim($data['special_terms']) : null,
        ':scope_of_work' => trim($data['scope_of_work'])
    ]);

    if (!$success) {
        $errorInfo = $stmt->errorInfo();
        throw new Exception('Failed to save quote: ' . ($errorInfo[2] ?? 'Unknown database error'));
    }

    $quoteId = $pdo->lastInsertId();

    /* ================= INSERT QUOTE ITEMS ================= */
    $itemStmt = $pdo->prepare("
        INSERT INTO quotation_items (
            quote_id, item_name, description, unit, quantity, unit_price, total,
            created_at
        ) VALUES (
            :quote_id, :item_name, :description, :unit, :quantity, :unit_price, :total,
            NOW()
        )
    ");

    foreach ($data['items'] as $item) {
        $qty = floatval($item['quantity']);
        $price = floatval($item['unit_price']);
        $lineTotal = $qty * $price;

        $success = $itemStmt->execute([
            ':quote_id' => $quoteId,
            ':item_name' => trim($item['description']),
            ':description' => isset($item['specifications']) && $item['specifications'] !== '' ? trim($item['specifications']) : null,
            ':unit' => $item['unit'] ?? 'pcs',
            ':quantity' => $qty,
            ':unit_price' => $price,
            ':total' => $lineTotal
        ]);

        if (!$success) {
            $errorInfo = $itemStmt->errorInfo();
            throw new Exception('Failed to save quote items: ' . ($errorInfo[2] ?? 'Unknown database error'));
        }
    }

    /* ================= COMMIT ================= */
    $pdo->commit();

    echo json_encode([
        'success' => true,
        'quote_id' => $quoteId,
        'message' => 'Quote saved successfully',
        'quote_no' => $data['quote_no'],
        'data' => [
            'quote_date_formatted' => $quote_date,
            'apply_tax_value' => $apply_tax
        ]
    ]);

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        try {
            $pdo->rollBack();
        } catch (Exception $rollbackError) {
            error_log('Rollback failed: ' . $rollbackError->getMessage());
        }
    }

    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'message' => $e->getMessage(),
        'debug' => isset($data) ? [
            'quote_date_received' => $data['quote_date'] ?? 'not set',
            'apply_tax_received' => $data['apply_tax'] ?? 'not set'
        ] : null
    ]);
    exit;
}
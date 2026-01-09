<?php
// Enable CORS
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");
header("Access-Control-Allow-Credentials: true");

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Set headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// Error reporting
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/error.log');

require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/Database.php';

// Log request
logRequest();

// Get request method and URI
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = rtrim($path, '/');

// Simple routing
try {
    switch (true) {
        case $path === '/api/health':
            require_once __DIR__ . '/api/health.php';
            break;
            
        case $path === '/api/invoice-number':
            if ($method === 'GET') {
                $invoiceNo = generateInvoiceNumber();
                jsonResponse(['invoice_no' => $invoiceNo]);
            }
            break;
            
        case $path === '/api/invoices':
            if ($method === 'GET') {
                require_once __DIR__ . '/api/get_invoices.php';
            } elseif ($method === 'POST') {
                require_once __DIR__ . '/api/create_invoice.php';
            }
            break;
            
        case preg_match('/^\/api\/invoices\/(\d+)$/', $path, $matches):
            $_GET['id'] = $matches[1];
            if ($method === 'GET') {
                require_once __DIR__ . '/api/get_invoice.php';
            }
            break;
            
        case preg_match('/^\/api\/invoices\/(\d+)\/pdf$/', $path, $matches):
            $_GET['id'] = $matches[1];
            if ($method === 'GET') {
                require_once __DIR__ . '/pdf/generate.php';
            }
            break;
            
        case $path === '/api/logout':
            if ($method === 'POST') {
                setcookie('auth_token', '', time() - 3600, '/');
                jsonResponse([
                    'success' => true,
                    'message' => 'Logged out successfully',
                    'redirectUrl' => '/logout.php'
                ]);
            }
            break;
            
        default:
            http_response_code(404);
            jsonResponse([
                'success' => false,
                'error' => 'Endpoint not found'
            ]);
    }
} catch (Exception $e) {
    error_log($e->getMessage());
    jsonResponse([
        'success' => false,
        'error' => 'Internal server error',
        'message' => $e->getMessage()
    ], 500);
}
?>
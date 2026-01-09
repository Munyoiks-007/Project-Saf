<?php
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function validateNumber($value, $fieldName) {
    if (!is_numeric($value)) {
        throw new Exception("Invalid $fieldName: must be a number");
    }
    return (float) $value;
}

function toNumber($value) {
    if ($value === null || $value === '') {
        return 0;
    }
    $num = is_string($value) ? (float) $value : (float) $value;
    return is_nan($num) ? 0 : $num;
}

function generateInvoiceNumber() {
    $now = new DateTime();
    return 'INV-' . $now->format('Ymd') . '-' . str_pad(rand(0, 999), 3, '0', STR_PAD_LEFT);
}

function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function logRequest() {
    $log = sprintf("[%s] %s %s\n", date('Y-m-d H:i:s'), $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
    file_put_contents(__DIR__ . '/../logs/access.log', $log, FILE_APPEND);
}
?>
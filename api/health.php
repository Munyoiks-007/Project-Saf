<?php
require_once __DIR__ . '/../includes/Database.php';

try {
    $db = Database::getInstance();
    $stmt = $db->getConnection()->query("SELECT NOW() as time");
    $result = $stmt->fetch();
    
    jsonResponse([
        'status' => 'healthy',
        'database' => [
            'connected' => true,
            'timestamp' => $result['time']
        ],
        'uptime' => round(microtime(true) - $_SERVER['REQUEST_TIME_FLOAT'], 2),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} catch (Exception $e) {
    jsonResponse([
        'status' => 'unhealthy',
        'database' => [
            'connected' => false,
            'error' => $e->getMessage()
        ]
    ], 503);
}
?>
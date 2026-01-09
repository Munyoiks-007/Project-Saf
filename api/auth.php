<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$auth = new Auth();
$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'register':
                    if (!isset($data['username']) || !isset($data['password']) || !isset($data['email'])) {
                        jsonResponse(['success' => false, 'error' => 'Missing required fields'], 400);
                    }
                    
                    $result = $auth->register(
                        $data['username'],
                        $data['password'],
                        $data['email'],
                        $data['full_name'] ?? ''
                    );
                    jsonResponse($result, $result['success'] ? 201 : 400);
                    break;
                    
                case 'login':
                    if (!isset($data['username']) || !isset($data['password'])) {
                        jsonResponse(['success' => false, 'error' => 'Missing credentials'], 400);
                    }
                    
                    $result = $auth->login($data['username'], $data['password']);
                    jsonResponse($result, $result['success'] ? 200 : 401);
                    break;
                    
                case 'logout':
                    $result = $auth->logout();
                    jsonResponse($result);
                    break;
                    
                default:
                    jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
            }
        } else {
            jsonResponse(['success' => false, 'error' => 'Action parameter required'], 400);
        }
        break;
        
    case 'GET':
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'check':
                    $user = $auth->getCurrentUser();
                    jsonResponse([
                        'success' => true,
                        'authenticated' => $auth->isLoggedIn(),
                        'user' => $user
                    ]);
                    break;
                    
                default:
                    jsonResponse(['success' => false, 'error' => 'Invalid action'], 400);
            }
        } else {
            jsonResponse(['success' => false, 'error' => 'Action parameter required'], 400);
        }
        break;
        
    default:
        jsonResponse(['success' => false, 'error' => 'Method not allowed'], 405);
}
?>
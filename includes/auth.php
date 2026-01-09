<?php
session_start();

class Auth {
    private $db;
    
    public function __construct() {
        require_once __DIR__ . '/Database.php';
        $this->db = Database::getInstance()->getConnection();
    }
    
    // Hash password
    public function hashPassword($password) {
        return password_hash($password, PASSWORD_BCRYPT);
    }
    
    // Verify password
    public function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    // User registration
    public function register($username, $password, $email, $fullName = '') {
        try {
            // Check if user exists
            $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ? OR email = ?");
            $stmt->execute([$username, $email]);
            
            if ($stmt->fetch()) {
                return ['success' => false, 'error' => 'Username or email already exists'];
            }
            
            // Insert new user
            $stmt = $this->db->prepare("
                INSERT INTO users (username, password_hash, email, full_name)
                VALUES (?, ?, ?, ?)
            ");
            
            $hashedPassword = $this->hashPassword($password);
            $stmt->execute([$username, $hashedPassword, $email, $fullName]);
            
            $userId = $this->db->lastInsertId();
            
            return [
                'success' => true,
                'userId' => $userId,
                'message' => 'Registration successful'
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Registration failed: ' . $e->getMessage()];
        }
    }
    
    // User login
    public function login($username, $password) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, username, password_hash, email, full_name 
                FROM users 
                WHERE username = ? OR email = ?
            ");
            $stmt->execute([$username, $username]);
            $user = $stmt->fetch();
            
            if (!$user || !$this->verifyPassword($password, $user['password_hash'])) {
                return ['success' => false, 'error' => 'Invalid credentials'];
            }
            
            // Create session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['email'] = $user['email'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['logged_in'] = true;
            $_SESSION['login_time'] = time();
            
            // Generate token for API
            $token = bin2hex(random_bytes(32));
            $_SESSION['api_token'] = $token;
            
            return [
                'success' => true,
                'user' => [
                    'id' => $user['id'],
                    'username' => $user['username'],
                    'email' => $user['email'],
                    'full_name' => $user['full_name']
                ],
                'token' => $token
            ];
            
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Login failed: ' . $e->getMessage()];
        }
    }
    
    // Check if user is logged in
    public function isLoggedIn() {
        return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    }
    
    // Get current user
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'username' => $_SESSION['username'] ?? null,
            'email' => $_SESSION['email'] ?? null,
            'full_name' => $_SESSION['full_name'] ?? null
        ];
    }
    
    // Logout
    public function logout() {
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        return ['success' => true, 'message' => 'Logged out successfully'];
    }
    
    // Validate API token
    public function validateToken($token) {
        return isset($_SESSION['api_token']) && $_SESSION['api_token'] === $token;
    }
    
    // Update user profile
    public function updateProfile($userId, $data) {
        try {
            $fields = [];
            $params = [];
            
            if (isset($data['email'])) {
                // Check if email exists for another user
                $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $stmt->execute([$data['email'], $userId]);
                if ($stmt->fetch()) {
                    return ['success' => false, 'error' => 'Email already exists'];
                }
                $fields[] = "email = ?";
                $params[] = $data['email'];
            }
            
            if (isset($data['full_name'])) {
                $fields[] = "full_name = ?";
                $params[] = $data['full_name'];
            }
            
            if (isset($data['password']) && !empty($data['password'])) {
                $fields[] = "password_hash = ?";
                $params[] = $this->hashPassword($data['password']);
            }
            
            if (empty($fields)) {
                return ['success' => false, 'error' => 'No fields to update'];
            }
            
            $params[] = $userId;
            $query = "UPDATE users SET " . implode(', ', $fields) . " WHERE id = ?";
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            // Update session if current user
            if ($_SESSION['user_id'] == $userId) {
                if (isset($data['email'])) $_SESSION['email'] = $data['email'];
                if (isset($data['full_name'])) $_SESSION['full_name'] = $data['full_name'];
            }
            
            return ['success' => true, 'message' => 'Profile updated successfully'];
            
        } catch (PDOException $e) {
            return ['success' => false, 'error' => 'Update failed: ' . $e->getMessage()];
        }
    }
    
    // Middleware to protect routes
    public static function requireLogin() {
        $auth = new self();
        if (!$auth->isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Authentication required']);
            exit;
        }
    }
    
    // Middleware for API token authentication
    public static function requireApiToken() {
        $auth = new self();
        $headers = getallheaders();
        $token = $_SERVER['HTTP_AUTHORIZATION'] ?? $headers['Authorization'] ?? '';
        $token = str_replace('Bearer ', '', $token);
        
        if (!$auth->validateToken($token)) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Invalid or missing API token']);
            exit;
        }
    }
}
?>
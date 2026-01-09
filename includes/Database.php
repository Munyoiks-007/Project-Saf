<?php
require_once __DIR__ . '/../BackEnd/database.php';

class Database {
    private static $instance = null;
    private $pdo;
    
    private function __construct() {
        $dsn = "mysql:host=" . DatabaseConfig::HOST . ";dbname=" . DatabaseConfig::DBNAME . ";charset=" . DatabaseConfig::CHARSET;
        
        try {
            $this->pdo = new PDO($dsn, DatabaseConfig::USERNAME, DatabaseConfig::PASSWORD);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
        } catch (PDOException $e) {
            die("Database connection failed: " . $e->getMessage());
        }
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    public function commit() {
        return $this->pdo->commit();
    }
    
    public function rollBack() {
        return $this->pdo->rollBack();
    }
}
?>
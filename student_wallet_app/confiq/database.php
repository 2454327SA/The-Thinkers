<?php
/**
 * Database Configuration
 * Student ID Wallet Application
 */

class Database {
    private $host = "localhost";
    private $db_name = "student_wallet";
    private $username = "root";
    private $password = "";
    public $conn;
    private static $instance = null;

    public function __construct() {
        $this->getConnection();
    }

    // Singleton pattern to prevent multiple connections
    public static function getInstance() {
        if (self::$instance == null) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, 
                                  $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            throw new Exception("Database connection failed");
        }
        return $this->conn;
    }

    public function closeConnection() {
        $this->conn = null;
    }
}

// Helper function to get database connection
function getDB() {
    return Database::getInstance()->getConnection();
}
?>
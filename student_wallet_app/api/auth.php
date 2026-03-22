<?php
/**
 * Authentication API
 * Handles user login and registration
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

require_once '../config/database.php';

class AuthAPI {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function login() {
        $data = json_decode(file_get_contents("php://input"));
        
        if(!isset($data->email) || !isset($data->password)) {
            echo json_encode(['success' => false, 'message' => 'Email and password required']);
            return;
        }
        
        $query = "SELECT id, email, password, full_name, student_number, is_verified, is_active 
                  FROM users 
                  WHERE email = :email AND is_active = 1";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':email', $data->email);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if(password_verify($data->password, $row['password'])) {
                // Generate session token
                $token = bin2hex(random_bytes(32));
                
                // Log session
                $this->logSession($row['id'], $token);
                
                // Remove password from response
                unset($row['password']);
                
                echo json_encode([
                    'success' => true,
                    'token' => $token,
                    'user' => $row
                ]);
                return;
            }
        }
        
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
    }

    public function register() {
        $data = json_decode(file_get_contents("php://input"));
        
        // Validate required fields
        $required = ['full_name', 'email', 'student_number', 'phone_number', 'password'];
        foreach($required as $field) {
            if(!isset($data->$field) || empty($data->$field)) {
                echo json_encode(['success' => false, 'message' => "Missing field: $field"]);
                return;
            }
        }
        
        // Validate email domain
        if(!strpos($data->email, 'wlv.ac.uk')) {
            echo json_encode(['success' => false, 'message' => 'Must use university email (@wlv.ac.uk)']);
            return;
        }
        
        // Validate student number
        if(!preg_match('/^[0-9]{7,8}$/', $data->student_number)) {
            echo json_encode(['success' => false, 'message' => 'Invalid student number format']);
            return;
        }
        
        // Check if user exists
        $check_query = "SELECT id FROM users WHERE email = :email OR student_number = :student_number";
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->bindParam(':email', $data->email);
        $check_stmt->bindParam(':student_number', $data->student_number);
        $check_stmt->execute();
        
        if($check_stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'User already exists']);
            return;
        }
        
        $query = "INSERT INTO users (email, password, student_number, phone_number, full_name) 
                  VALUES (:email, :password, :student_number, :phone_number, :full_name)";
        
        $stmt = $this->conn->prepare($query);
        
        $hashed_password = password_hash($data->password, PASSWORD_DEFAULT);
        
        $stmt->bindParam(':email', $data->email);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':student_number', $data->student_number);
        $stmt->bindParam(':phone_number', $data->phone_number);
        $stmt->bindParam(':full_name', $data->full_name);
        
        if($stmt->execute()) {
            $user_id = $this->conn->lastInsertId();
            
            // Create wallet for user
            $wallet_query = "INSERT INTO wallet (user_id, balance) VALUES (:user_id, 0.00)";
            $wallet_stmt = $this->conn->prepare($wallet_query);
            $wallet_stmt->bindParam(':user_id', $user_id);
            $wallet_stmt->execute();
            
            // Create digital ID
            $expiry_date = date('Y-m-d', strtotime('+4 years'));
            $id_query = "INSERT INTO digital_ids (user_id, expiry_date) VALUES (:user_id, :expiry_date)";
            $id_stmt = $this->conn->prepare($id_query);
            $id_stmt->bindParam(':user_id', $user_id);
            $id_stmt->bindParam(':expiry_date', $expiry_date);
            $id_stmt->execute();
            
            // Send welcome notification
            $this->sendNotification($user_id, 'Welcome to Student ID Wallet', 
                                    'Your digital student ID is ready to use!', 'welcome');
            
            echo json_encode(['success' => true, 'message' => 'Registration successful']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Registration failed']);
        }
    }

    private function logSession($user_id, $token) {
        $query = "INSERT INTO session_logs (user_id, session_token, ip_address, user_agent, device_type) 
                  VALUES (:user_id, :token, :ip, :agent, :device)";
        $stmt = $this->conn->prepare($query);
        
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $device = $this->getDeviceType($agent);
        
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':ip', $ip);
        $stmt->bindParam(':agent', $agent);
        $stmt->bindParam(':device', $device);
        $stmt->execute();
    }

    private function sendNotification($user_id, $title, $message, $type) {
        $query = "INSERT INTO notifications (user_id, title, message, type) 
                  VALUES (:user_id, :title, :message, :type)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':type', $type);
        $stmt->execute();
    }

    private function getDeviceType($user_agent) {
        if(strpos($user_agent, 'Mobile') !== false) return 'mobile';
        if(strpos($user_agent, 'Tablet') !== false) return 'tablet';
        return 'desktop';
    }
}

$auth = new AuthAPI();
$method = $_SERVER['REQUEST_METHOD'];

if($method == 'POST') {
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    if($action == 'login') {
        $auth->login();
    } elseif($action == 'register') {
        $auth->register();
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
<?php
/**
 * Wallet API
 * Handles wallet operations and transactions
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");

require_once '../config/database.php';

class WalletAPI {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function getBalance($user_id) {
        $query = "SELECT balance, currency FROM wallet WHERE user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        if($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            echo json_encode(['success' => true, 'balance' => (float)$row['balance'], 'currency' => $row['currency']]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Wallet not found']);
        }
    }

    public function addBalance() {
        $data = json_decode(file_get_contents("php://input"));
        
        if(!isset($data->user_id) || !isset($data->amount) || $data->amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid amount']);
            return;
        }
        
        $this->conn->beginTransaction();
        
        try {
            // Update wallet balance
            $query = "UPDATE wallet SET balance = balance + :amount, updated_at = NOW() WHERE user_id = :user_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':amount', $data->amount);
            $stmt->bindParam(':user_id', $data->user_id);
            $stmt->execute();
            
            // Log transaction
            $this->logTransaction($data->user_id, $data->amount, 'deposit', 'Balance added to wallet');
            
            // Send notification
            $this->sendNotification($data->user_id, 'Balance Added', 
                                    "£{$data->amount} has been added to your wallet", 'transaction');
            
            $this->conn->commit();
            echo json_encode(['success' => true, 'message' => 'Balance added successfully']);
            
        } catch(Exception $e) {
            $this->conn->rollBack();
            error_log("Add balance error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Failed to add balance']);
        }
    }

    public function makePayment() {
        $data = json_decode(file_get_contents("php://input"));
        
        if(!isset($data->user_id) || !isset($data->amount) || $data->amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid payment amount']);
            return;
        }
        
        // Check balance
        $check_query = "SELECT balance FROM wallet WHERE user_id = :user_id";
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->bindParam(':user_id', $data->user_id);
        $check_stmt->execute();
        $balance = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if($balance['balance'] >= $data->amount) {
            $this->conn->beginTransaction();
            
            try {
                $query = "UPDATE wallet SET balance = balance - :amount, updated_at = NOW() WHERE user_id = :user_id";
                $stmt = $this->conn->prepare($query);
                $stmt->bindParam(':amount', $data->amount);
                $stmt->bindParam(':user_id', $data->user_id);
                $stmt->execute();
                
                $description = isset($data->description) ? $data->description : 'Campus payment';
                $this->logTransaction($data->user_id, $data->amount, 'payment', $description);
                
                $this->conn->commit();
                echo json_encode(['success' => true, 'message' => 'Payment successful']);
                return;
            } catch(Exception $e) {
                $this->conn->rollBack();
                error_log("Payment error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Payment failed']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Insufficient balance']);
        }
    }

    public function getTransactions($user_id) {
        $query = "SELECT id, amount, transaction_type, description, location, status, created_at 
                  FROM transactions 
                  WHERE user_id = :user_id 
                  ORDER BY created_at DESC 
                  LIMIT 50";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'transactions' => $transactions]);
    }

    private function logTransaction($user_id, $amount, $type, $description) {
        $query = "INSERT INTO transactions (user_id, amount, transaction_type, description, status) 
                  VALUES (:user_id, :amount, :type, :description, 'completed')";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':amount', $amount);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':description', $description);
        $stmt->execute();
    }

    private function sendNotification($user_id, $title, $message, $type) {
        $query = "INSERT INTO notifications (user_id, title, message, type, created_at) 
                  VALUES (:user_id, :title, :message, :type, NOW())";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':message', $message);
        $stmt->bindParam(':type', $type);
        $stmt->execute();
    }
}

$wallet = new WalletAPI();
$method = $_SERVER['REQUEST_METHOD'];
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if($method == 'GET') {
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    if($action == 'balance') {
        $wallet->getBalance($user_id);
    } elseif($action == 'transactions') {
        $wallet->getTransactions($user_id);
    }
} elseif($method == 'POST') {
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    if($action == 'add') {
        $wallet->addBalance();
    } elseif($action == 'pay') {
        $wallet->makePayment();
    }
}
?>
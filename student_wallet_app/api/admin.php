<?php
/**
 * Admin API
 * Handles admin operations
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, PUT, DELETE, OPTIONS");

require_once '../config/database.php';

class AdminAPI {
    private $conn;
    private $admin_password = 'admin123'; // In production, store this securely

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
        $this->verifyAdmin();
    }

    private function verifyAdmin() {
        $headers = apache_request_headers();
        $token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : '';
        
        // Simple admin verification - in production, use proper authentication
        if($token !== $this->admin_password) {
            http_response_code(401);
            echo json_encode(['success' => false, 'message' => 'Unauthorized']);
            exit;
        }
    }

    public function getDashboard() {
        // Get total users
        $users_query = "SELECT COUNT(*) as total FROM users WHERE is_active = 1";
        $users_stmt = $this->conn->prepare($users_query);
        $users_stmt->execute();
        $total_users = $users_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get total balance
        $balance_query = "SELECT SUM(balance) as total FROM wallet";
        $balance_stmt = $this->conn->prepare($balance_query);
        $balance_stmt->execute();
        $total_balance = $balance_stmt->fetch(PDO::FETCH_ASSOC)['total'] ?: 0;
        
        // Get total transactions today
        $trans_query = "SELECT COUNT(*) as total, SUM(amount) as amount 
                        FROM transactions 
                        WHERE DATE(created_at) = CURDATE()";
        $trans_stmt = $this->conn->prepare($trans_query);
        $trans_stmt->execute();
        $today_trans = $trans_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get total events
        $events_query = "SELECT COUNT(*) as total FROM events WHERE is_active = 1";
        $events_stmt = $this->conn->prepare($events_query);
        $events_stmt->execute();
        $total_events = $events_stmt->fetch(PDO::FETCH_ASSOC)['total'];
        
        // Get recent users
        $recent_query = "SELECT id, full_name, email, student_number, created_at 
                         FROM users 
                         ORDER BY created_at DESC 
                         LIMIT 10";
        $recent_stmt = $this->conn->prepare($recent_query);
        $recent_stmt->execute();
        $recent_users = $recent_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'total_users' => $total_users,
                'total_balance' => number_format($total_balance, 2),
                'today_transactions' => $today_trans['total'] ?? 0,
                'today_volume' => number_format($today_trans['amount'] ?? 0, 2),
                'total_events' => $total_events,
                'recent_users' => $recent_users
            ]
        ]);
    }

    public function getUsers() {
        $query = "SELECT u.*, w.balance, 
                         (SELECT COUNT(*) FROM transactions WHERE user_id = u.id) as transaction_count
                  FROM users u 
                  LEFT JOIN wallet w ON u.id = w.user_id 
                  ORDER BY u.created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $users]);
    }

    public function getTransactions($limit = 100) {
        $query = "SELECT t.*, u.full_name as user_name, u.email as user_email 
                  FROM transactions t 
                  LEFT JOIN users u ON t.user_id = u.id 
                  ORDER BY t.created_at DESC 
                  LIMIT :limit";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $transactions = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $transactions]);
    }

    public function getEvents() {
        $query = "SELECT e.*, 
                         (SELECT COUNT(*) FROM event_registrations WHERE event_id = e.id) as registrations
                  FROM events e 
                  ORDER BY e.event_date DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'data' => $events]);
    }

    public function updateUserBalance() {
        $data = json_decode(file_get_contents("php://input"));
        
        $this->conn->beginTransaction();
        
        try {
            $query = "UPDATE wallet SET balance = :balance, updated_at = NOW() WHERE user_id = :user_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':balance', $data->balance);
            $stmt->bindParam(':user_id', $data->user_id);
            $stmt->execute();
            
            // Log admin action
            $this->logAdminAction($data->user_id, 'balance_update', "Balance updated to £{$data->balance}");
            
            $this->conn->commit();
            echo json_encode(['success' => true]);
        } catch(Exception $e) {
            $this->conn->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function toggleUserStatus() {
        $data = json_decode(file_get_contents("php://input"));
        
        $query = "UPDATE users SET is_active = NOT is_active WHERE id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $data->user_id);
        
        if($stmt->execute()) {
            $this->logAdminAction($data->user_id, 'status_toggle', "User status toggled");
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
    }

    public function deleteUser() {
        $data = json_decode(file_get_contents("php://input"));
        
        // Cascade delete will handle related tables
        $query = "DELETE FROM users WHERE id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $data->user_id);
        
        if($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
    }

    public function createEvent() {
        $data = json_decode(file_get_contents("php://input"));
        
        $query = "INSERT INTO events (title, description, event_date, event_time, location, max_attendees) 
                  VALUES (:title, :description, :event_date, :event_time, :location, :max_attendees)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':title', $data->title);
        $stmt->bindParam(':description', $data->description);
        $stmt->bindParam(':event_date', $data->event_date);
        $stmt->bindParam(':event_time', $data->event_time);
        $stmt->bindParam(':location', $data->location);
        $stmt->bindParam(':max_attendees', $data->max_attendees);
        
        if($stmt->execute()) {
            $event_id = $this->conn->lastInsertId();
            $this->logAdminAction(null, 'event_created', "Event created: {$data->title}");
            echo json_encode(['success' => true, 'event_id' => $event_id]);
        } else {
            echo json_encode(['success' => false]);
        }
    }

    public function updateEvent() {
        $data = json_decode(file_get_contents("php://input"));
        
        $query = "UPDATE events SET title = :title, description = :description, 
                  event_date = :event_date, event_time = :event_time, 
                  location = :location, max_attendees = :max_attendees 
                  WHERE id = :event_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':title', $data->title);
        $stmt->bindParam(':description', $data->description);
        $stmt->bindParam(':event_date', $data->event_date);
        $stmt->bindParam(':event_time', $data->event_time);
        $stmt->bindParam(':location', $data->location);
        $stmt->bindParam(':max_attendees', $data->max_attendees);
        $stmt->bindParam(':event_id', $data->event_id);
        
        if($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
    }

    public function deleteEvent() {
        $data = json_decode(file_get_contents("php://input"));
        
        $query = "DELETE FROM events WHERE id = :event_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':event_id', $data->event_id);
        
        if($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
    }

    public function generateReport() {
        $data = json_decode(file_get_contents("php://input"));
        
        $report = [];
        
        if($data->type == 'users') {
            $query = "SELECT DATE(created_at) as date, COUNT(*) as count 
                      FROM users 
                      WHERE DATE(created_at) BETWEEN :start_date AND :end_date 
                      GROUP BY DATE(created_at) 
                      ORDER BY date";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':start_date', $data->start_date);
            $stmt->bindParam(':end_date', $data->end_date);
            $stmt->execute();
            $report = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        elseif($data->type == 'transactions') {
            $query = "SELECT DATE(created_at) as date, 
                             transaction_type, 
                             COUNT(*) as count, 
                             SUM(amount) as total 
                      FROM transactions 
                      WHERE DATE(created_at) BETWEEN :start_date AND :end_date 
                      GROUP BY DATE(created_at), transaction_type 
                      ORDER BY date";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':start_date', $data->start_date);
            $stmt->bindParam(':end_date', $data->end_date);
            $stmt->execute();
            $report = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
        elseif($data->type == 'financial') {
            $query = "SELECT 
                         SUM(CASE WHEN transaction_type = 'deposit' THEN amount ELSE 0 END) as total_deposits,
                         SUM(CASE WHEN transaction_type = 'payment' THEN amount ELSE 0 END) as total_payments,
                         SUM(CASE WHEN transaction_type = 'deposit' THEN amount ELSE -amount END) as net_flow
                      FROM transactions 
                      WHERE DATE(created_at) BETWEEN :start_date AND :end_date";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':start_date', $data->start_date);
            $stmt->bindParam(':end_date', $data->end_date);
            $stmt->execute();
            $report = $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        echo json_encode(['success' => true, 'data' => $report]);
    }

    private function logAdminAction($user_id, $action, $details) {
        $query = "INSERT INTO admin_logs (admin_id, user_id, action, details, created_at) 
                  VALUES (1, :user_id, :action, :details, NOW())";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':details', $details);
        $stmt->execute();
    }
}

$admin = new AdminAPI();
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

if($method == 'GET') {
    if($action == 'dashboard') {
        $admin->getDashboard();
    } elseif($action == 'users') {
        $admin->getUsers();
    } elseif($action == 'transactions') {
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 100;
        $admin->getTransactions($limit);
    } elseif($action == 'events') {
        $admin->getEvents();
    }
} elseif($method == 'POST') {
    if($action == 'update_balance') {
        $admin->updateUserBalance();
    } elseif($action == 'toggle_status') {
        $admin->toggleUserStatus();
    } elseif($action == 'delete_user') {
        $admin->deleteUser();
    } elseif($action == 'create_event') {
        $admin->createEvent();
    } elseif($action == 'update_event') {
        $admin->updateEvent();
    } elseif($action == 'delete_event') {
        $admin->deleteEvent();
    } elseif($action == 'report') {
        $admin->generateReport();
    }
}
?>
<?php
/**
 * Student API
 * Handles student profile, attendance, library, and events
 */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, PUT, OPTIONS");

require_once '../config/database.php';

class StudentAPI {
    private $conn;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    public function getStudentInfo($user_id) {
        $query = "SELECT u.id, u.email, u.full_name, u.student_number, u.phone_number, 
                         u.course, u.department, u.year_of_study, u.profile_photo, 
                         u.is_verified, u.is_active, d.expiry_date, d.qr_code 
                  FROM users u 
                  LEFT JOIN digital_ids d ON u.id = d.user_id 
                  WHERE u.id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $student = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Generate QR code data
        $qr_data = [
            'student_id' => $student['student_number'],
            'name' => $student['full_name'],
            'university' => 'University of Wolverhampton',
            'expiry' => $student['expiry_date']
        ];
        
        $student['qr_data'] = json_encode($qr_data);
        
        echo json_encode(['success' => true, 'student' => $student]);
    }

    public function updateProfile() {
        $data = json_decode(file_get_contents("php://input"));
        
        $query = "UPDATE users SET phone_number = :phone, course = :course, 
                  department = :department, year_of_study = :year, updated_at = NOW()
                  WHERE id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':phone', $data->phone_number);
        $stmt->bindParam(':course', $data->course);
        $stmt->bindParam(':department', $data->department);
        $stmt->bindParam(':year', $data->year_of_study);
        $stmt->bindParam(':user_id', $data->user_id);
        
        if($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Profile updated']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Update failed']);
        }
    }

    public function changePassword() {
        $data = json_decode(file_get_contents("php://input"));
        
        // Verify old password
        $query = "SELECT password FROM users WHERE id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $data->user_id);
        $stmt->execute();
        $user = $stmt->fetch();
        
        if(password_verify($data->old_password, $user['password'])) {
            $new_hash = password_hash($data->new_password, PASSWORD_DEFAULT);
            $update = "UPDATE users SET password = :password, updated_at = NOW() WHERE id = :user_id";
            $update_stmt = $this->conn->prepare($update);
            $update_stmt->bindParam(':password', $new_hash);
            $update_stmt->bindParam(':user_id', $data->user_id);
            
            if($update_stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Password changed']);
                return;
            }
        }
        
        echo json_encode(['success' => false, 'message' => 'Invalid current password']);
    }

    public function getAttendance($user_id) {
        $query = "SELECT id, subject, date, time, status, location 
                  FROM attendance 
                  WHERE user_id = :user_id 
                  ORDER BY date DESC, time DESC 
                  LIMIT 30";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $attendance = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate statistics
        $present = count(array_filter($attendance, fn($a) => $a['status'] === 'present'));
        $total = count($attendance);
        $percentage = $total > 0 ? ($present / $total * 100) : 0;
        
        echo json_encode([
            'success' => true, 
            'attendance' => $attendance,
            'statistics' => [
                'present' => $present,
                'total' => $total,
                'percentage' => round($percentage, 1)
            ]
        ]);
    }

    public function markAttendance() {
        $data = json_decode(file_get_contents("php://input"));
        
        $query = "INSERT INTO attendance (user_id, subject, date, time, status, qr_code_used, location) 
                  VALUES (:user_id, :subject, CURDATE(), CURTIME(), :status, :qr_code, :location)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $data->user_id);
        $stmt->bindParam(':subject', $data->subject);
        $stmt->bindParam(':status', $data->status);
        $stmt->bindParam(':qr_code', $data->qr_code);
        $stmt->bindParam(':location', $data->location);
        
        if($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Attendance marked']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to mark attendance']);
        }
    }

    public function getLibraryBooks($user_id) {
        $query = "SELECT id, book_title, book_isbn, borrow_date, due_date, return_date, 
                         fine_amount, status 
                  FROM library_access 
                  WHERE user_id = :user_id 
                  ORDER BY due_date ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $books = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate total fines
        $total_fines = array_sum(array_column($books, 'fine_amount'));
        
        echo json_encode([
            'success' => true, 
            'books' => $books,
            'total_fines' => $total_fines
        ]);
    }

    public function getEvents() {
        $query = "SELECT id, title, description, event_date, event_time, location, 
                         max_attendees, current_attendees, is_active 
                  FROM events 
                  WHERE event_date >= CURDATE() AND is_active = 1
                  ORDER BY event_date ASC 
                  LIMIT 10";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'events' => $events]);
    }

    public function registerEvent() {
        $data = json_decode(file_get_contents("php://input"));
        
        // Check if already registered
        $check_query = "SELECT * FROM event_registrations WHERE user_id = :user_id AND event_id = :event_id";
        $check_stmt = $this->conn->prepare($check_query);
        $check_stmt->bindParam(':user_id', $data->user_id);
        $check_stmt->bindParam(':event_id', $data->event_id);
        $check_stmt->execute();
        
        if($check_stmt->rowCount() > 0) {
            echo json_encode(['success' => false, 'message' => 'Already registered']);
            return;
        }
        
        // Check event capacity
        $event_query = "SELECT max_attendees, current_attendees FROM events WHERE id = :event_id";
        $event_stmt = $this->conn->prepare($event_query);
        $event_stmt->bindParam(':event_id', $data->event_id);
        $event_stmt->execute();
        $event = $event_stmt->fetch();
        
        if($event['current_attendees'] >= $event['max_attendees']) {
            echo json_encode(['success' => false, 'message' => 'Event is full']);
            return;
        }
        
        $qr_code = bin2hex(random_bytes(16));
        
        $this->conn->beginTransaction();
        
        try {
            $query = "INSERT INTO event_registrations (user_id, event_id, qr_code) 
                      VALUES (:user_id, :event_id, :qr_code)";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':user_id', $data->user_id);
            $stmt->bindParam(':event_id', $data->event_id);
            $stmt->bindParam(':qr_code', $qr_code);
            $stmt->execute();
            
            $update_query = "UPDATE events SET current_attendees = current_attendees + 1 WHERE id = :event_id";
            $update_stmt = $this->conn->prepare($update_query);
            $update_stmt->bindParam(':event_id', $data->event_id);
            $update_stmt->execute();
            
            $this->conn->commit();
            echo json_encode(['success' => true, 'message' => 'Registered successfully', 'qr_code' => $qr_code]);
            
        } catch(Exception $e) {
            $this->conn->rollBack();
            error_log("Event registration error: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => 'Registration failed']);
        }
    }

    public function getNotifications($user_id) {
        $query = "SELECT id, title, message, type, is_read, created_at 
                  FROM notifications 
                  WHERE user_id = :user_id 
                  ORDER BY created_at DESC 
                  LIMIT 20";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'notifications' => $notifications]);
    }

    public function markNotificationRead() {
        $data = json_decode(file_get_contents("php://input"));
        
        $query = "UPDATE notifications SET is_read = 1 WHERE id = :notification_id AND user_id = :user_id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':notification_id', $data->notification_id);
        $stmt->bindParam(':user_id', $data->user_id);
        
        if($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false]);
        }
    }
}

$student = new StudentAPI();
$method = $_SERVER['REQUEST_METHOD'];
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if($method == 'GET') {
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    if($action == 'info') {
        $student->getStudentInfo($user_id);
    } elseif($action == 'attendance') {
        $student->getAttendance($user_id);
    } elseif($action == 'library') {
        $student->getLibraryBooks($user_id);
    } elseif($action == 'events') {
        $student->getEvents();
    } elseif($action == 'notifications') {
        $student->getNotifications($user_id);
    }
} elseif($method == 'POST') {
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    if($action == 'update') {
        $student->updateProfile();
    } elseif($action == 'change_password') {
        $student->changePassword();
    } elseif($action == 'attendance') {
        $student->markAttendance();
    } elseif($action == 'register_event') {
        $student->registerEvent();
    } elseif($action == 'mark_read') {
        $student->markNotificationRead();
    }
}
?>
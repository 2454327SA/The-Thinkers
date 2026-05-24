<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));

if(!empty($data->email) && !empty($data->password)) {
    
    $query = "SELECT * FROM users WHERE email = :email";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $data->email);
    $stmt->execute();
    
    if($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if(password_verify($data->password, $user['password'])) {
            // Update last login
            $updateQuery = "UPDATE users SET last_login = NOW() WHERE id = :id";
            $updateStmt = $db->prepare($updateQuery);
            $updateStmt->bindParam(':id', $user['id']);
            $updateStmt->execute();
            
            // Log activity
            $logQuery = "INSERT INTO activity_logs (user_id, action, ip_address) 
                        VALUES (:user_id, 'Login', :ip)";
            $logStmt = $db->prepare($logQuery);
            $ip = $_SERVER['REMOTE_ADDR'];
            $logStmt->bindParam(':user_id', $user['id']);
            $logStmt->bindParam(':ip', $ip);
            $logStmt->execute();
            
            // Create session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_role'] = $user['role'];
            
            // Generate token (simple for demo)
            $token = base64_encode($user['id'] . ':' . time());
            
            echo json_encode([
                "success" => true,
                "message" => "Login successful",
                "token" => $token,
                "user" => [
                    "id" => $user['id'],
                    "name" => $user['name'],
                    "email" => $user['email'],
                    "student_id" => $user['student_id'],
                    "role" => $user['role']
                ]
            ]);
        } else {
            http_response_code(401);
            echo json_encode(["error" => "Invalid password"]);
        }
    } else {
        http_response_code(401);
        echo json_encode(["error" => "User not found"]);
    }
} else {
    http_response_code(400);
    echo json_encode(["error" => "Email and password required"]);
}
?>
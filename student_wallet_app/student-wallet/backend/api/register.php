<?php
header("Content-Type: application/json; charset=UTF-8");
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$data = json_decode(file_get_contents("php://input"));

if(!empty($data->name) && !empty($data->email) && !empty($data->student_id) && !empty($data->password)) {
    
    // Check if email or student_id exists
    $checkQuery = "SELECT id FROM users WHERE email = :email OR student_id = :student_id";
    $checkStmt = $db->prepare($checkQuery);
    $checkStmt->bindParam(':email', $data->email);
    $checkStmt->bindParam(':student_id', $data->student_id);
    $checkStmt->execute();
    
    if($checkStmt->rowCount() > 0) {
        http_response_code(400);
        echo json_encode(["error" => "Email or Student ID already exists"]);
        exit();
    }
    
    // Hash password
    $hashed_password = password_hash($data->password, PASSWORD_DEFAULT);
    
    $query = "INSERT INTO users (name, email, student_id, password, role) 
              VALUES (:name, :email, :student_id, :password, 'student')";
    
    $stmt = $db->prepare($query);
    
    $stmt->bindParam(':name', $data->name);
    $stmt->bindParam(':email', $data->email);
    $stmt->bindParam(':student_id', $data->student_id);
    $stmt->bindParam(':password', $hashed_password);
    
    if($stmt->execute()) {
        http_response_code(201);
        echo json_encode(["message" => "Registration successful", "success" => true]);
    } else {
        http_response_code(500);
        echo json_encode(["error" => "Registration failed"]);
    }
} else {
    http_response_code(400);
    echo json_encode(["error" => "All fields are required"]);
}
?>
<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

// Get token from header
$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

if(!$token) {
    http_response_code(401);
    echo json_encode(["error" => "No token provided"]);
    exit();
}

// Decode token (simple for demo)
$decoded = base64_decode($token);
$parts = explode(':', $decoded);
$user_id = $parts[0];

$query = "SELECT id, name, email, student_id, role, profile_pic, phone, department, semester, created_at, last_login 
          FROM users WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $user_id);
$stmt->execute();

if($stmt->rowCount() > 0) {
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    echo json_encode(["success" => true, "user" => $user]);
} else {
    http_response_code(404);
    echo json_encode(["error" => "User not found"]);
}
?>
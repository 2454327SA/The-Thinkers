<?php
session_start();
header("Content-Type: application/json; charset=UTF-8");
require_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$headers = getallheaders();
$token = isset($headers['Authorization']) ? str_replace('Bearer ', '', $headers['Authorization']) : null;

if(!$token) {
    http_response_code(401);
    echo json_encode(["error" => "No token provided"]);
    exit();
}

$decoded = base64_decode($token);
$parts = explode(':', $decoded);
$user_id = $parts[0];
$data = json_decode(file_get_contents("php://input"));

$query = "UPDATE users SET name = :name, phone = :phone, department = :department, semester = :semester 
          WHERE id = :id";
$stmt = $db->prepare($query);
$stmt->bindParam(':name', $data->name);
$stmt->bindParam(':phone', $data->phone);
$stmt->bindParam(':department', $data->department);
$stmt->bindParam(':semester', $data->semester);
$stmt->bindParam(':id', $user_id);

if($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "Profile updated successfully"]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Update failed"]);
}
?>
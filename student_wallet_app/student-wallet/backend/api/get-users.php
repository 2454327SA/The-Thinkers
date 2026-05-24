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

$query = "SELECT id, name, email, student_id, role, department, semester, created_at, is_active 
          FROM users WHERE role = 'student' ORDER BY created_at DESC";
$stmt = $db->prepare($query);
$stmt->execute();

$users = [];
while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $users[] = $row;
}

echo json_encode(["success" => true, "users" => $users]);
?>
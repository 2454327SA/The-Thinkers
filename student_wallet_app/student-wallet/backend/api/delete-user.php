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

$data = json_decode(file_get_contents("php://input"));

$query = "DELETE FROM users WHERE id = :id AND role = 'student'";
$stmt = $db->prepare($query);
$stmt->bindParam(':id', $data->user_id);

if($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "User deleted successfully"]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Delete failed"]);
}
?>
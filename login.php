<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

require "db.php";

// Handle CORS preflight request
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Read JSON input
$json = file_get_contents("php://input");
$data = json_decode($json, true);

// Debugging: Log received data
file_put_contents("debug.log", print_r($data, true));

if (!$data || !isset($data['hospital_number']) || !isset($data['password'])) {
    echo json_encode([
        "message" => "Missing hospital number or password", 
        "received" => $data
    ]);
    exit;
}

$hospital_number = trim($data['hospital_number']);
$password = trim($data['password']);

// Fetch user from DB
$stmt = $conn->prepare("SELECT user_id, password_hash, role FROM users WHERE hospital_number = ?");
$stmt->bind_param("s", $hospital_number);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($user_id, $stored_password, $role);

if ($stmt->fetch()) {
    // Verify password
    if (password_verify($password, $stored_password)) {
        echo json_encode(["message" => "Login successful", "role" => $role, "user_id" => $user_id]);
    } else {
        echo json_encode(["message" => "Incorrect password"]);
    }
} else {
    echo json_encode(["message" => "User not found"]);
}
?>
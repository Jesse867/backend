<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
require "db.php";

// Read JSON input
$json = file_get_contents("php://input");
$data = json_decode($json, true);

// Debugging: Log received data
file_put_contents("debug.log", print_r($data, true));

if (!$data || !isset($data['email']) || !isset($data['password'])) {
    echo json_encode([
        "message" => "Missing email or password", 
        "received" => $data
    ]);
    exit;
}

$email = trim($data['email']);
$password = trim($data['password']);

// Fetch user from DB
$stmt = $conn->prepare("SELECT user_id, password_hash, role FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();
$stmt->bind_result($user_id, $stored_password, $role);

if ($stmt->fetch()) {
    // Verify password
    if (password_verify($password, $stored_password)) {
        // Check if user is staff (doctor, pharmacist, billing_officer, receptionist, or admin)
        $valid_roles = ['doctor', 'pharmacist', 'billing_officer', 'receptionist', 'admin'];
        if (in_array($role, $valid_roles)) {
            echo json_encode([
                "message" => "Login successful",
                "role" => $role,
                "user_id" => $user_id
            ]);
        } else {
            echo json_encode(["message" => "Unauthorized access"]);
        }
    } else {
        echo json_encode(["message" => "Incorrect password"]);
    }
} else {
    echo json_encode(["message" => "User not found"]);
}
?>
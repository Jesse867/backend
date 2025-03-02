<?php
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 1);
require "db.php";

$json = file_get_contents("php://input");
$data = json_decode($json, true);

if (!$data) {
    echo json_encode(["error" => "Invalid JSON input"]);
    exit;
}

// Check required fields
if (!isset($data['email']) || !isset($data['password']) || !isset($data['first_name']) || !isset($data['last_name']) || !isset($data['role']) || !isset($data['phone_number'])) {
    echo json_encode(["message" => "Missing required fields"]);
    exit;
}

$email = trim($data['email']);
$password = trim($data['password']);
$first_name = trim($data['first_name']);
$last_name = trim($data['last_name']);
$role = trim($data['role']);
$phone_number = trim($data['phone_number']);
$license_number = isset($data['license_number']) ? trim($data['license_number']) : null; // Only needed for doctors/pharmacists

// Validate role
$valid_roles = ['doctor', 'pharmacist', 'billing_officer', 'receptionist', 'admin'];
if (!in_array($role, $valid_roles)) {
    echo json_encode(["message" => "Invalid role"]);
    exit;
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["message" => "Invalid email format"]);
    exit;
}

// Check if email exists
$stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows > 0) {
    echo json_encode(["message" => "Email already registered"]);
    exit;
}

// Generate unique 10-digit hospital ID
$hospital_number = str_pad(rand(1000000000, 9999999999), 10, '0', STR_PAD_LEFT);

// Hash password
$hashed_password = password_hash($password, PASSWORD_BCRYPT);

// Insert user into `users`
$stmt = $conn->prepare("INSERT INTO users (email, password_hash, role, hospital_number) VALUES (?, ?, ?, ?)");
$stmt->bind_param("ssss", $email, $hashed_password, $role, $hospital_number);

if ($stmt->execute()) {
    $user_id = $stmt->insert_id;

    // Insert into role-specific table
    try {
        switch ($role) {
            case 'doctor':
                // Doctors need license_number
                if (!$license_number) {
                    throw new Exception("License number is required for doctors");
                }
                $stmt = $conn->prepare("INSERT INTO doctors (user_id, first_name, last_name, phone_number, email, license_number) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssss", $user_id, $first_name, $last_name, $phone_number, $email, $license_number);
                break;

            case 'pharmacist':
                // Pharmacists need license_number
                if (!$license_number) {
                    throw new Exception("License number is required for pharmacists");
                }
                $stmt = $conn->prepare("INSERT INTO pharmacists (user_id, first_name, last_name, phone_number, email, license_number) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("isssss", $user_id, $first_name, $last_name, $phone_number, $email, $license_number);
                break;

            case 'billing_officer':
                $stmt = $conn->prepare("INSERT INTO billing_officers (user_id, first_name, last_name, phone_number, email) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issss", $user_id, $first_name, $last_name, $phone_number, $email);
                break;

            case 'receptionist':
                $stmt = $conn->prepare("INSERT INTO receptionists (user_id, first_name, last_name, phone_number, email) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issss", $user_id, $first_name, $last_name, $phone_number, $email);
                break;

            case 'admin':
                $stmt = $conn->prepare("INSERT INTO admins (user_id, first_name, last_name, phone_number, email) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("issss", $user_id, $first_name, $last_name, $phone_number, $email);
                break;

            default:
                throw new Exception("Invalid role");
        }

        if ($stmt->execute()) {
            echo json_encode(["message" => "User registered successfully", "user_id" => $user_id, "role" => $role ]);
        } else {
            throw new Exception("Error inserting into role-specific table");
        }

    } catch (Exception $e) {
        error_log("Registration error: " . $e->getMessage());
        echo json_encode(["message" => "Registration failed: " . $e->getMessage()]);
    }

} else {
    echo json_encode(["message" => "Error registering user"]);
}
?>
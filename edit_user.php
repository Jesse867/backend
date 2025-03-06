<?php
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 1);
require "db.php";

// Read JSON input
$json = file_get_contents("php://input");
$data = json_decode($json, true);

if (!$data) {
    echo json_encode(["message" => "Invalid JSON input"]);
    exit;
}

// Validate required fields for the update
$requiredFields = ['userId', 'firstName', 'lastName', 'contactNumber', 'role'];
foreach ($requiredFields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        echo json_encode(["message" => "Missing or empty field: $field"]);
        exit;
    }
}

$userId = trim($data['userId']);
$firstName = trim($data['firstName']);
$lastName = trim($data['lastName']);
$contactNumber = trim($data['contactNumber']);
$role = trim($data['role']);

// Optional: Update fields in the "users" table if applicable
// (e.g., if you allow changing the role or email in the users table)
// $queryUser = "UPDATE users SET role = ? WHERE user_id = ?";
// $stmtUser = $conn->prepare($queryUser);
// $stmtUser->bind_param("si", $role, $userId);
// $stmtUser->execute();

// Update role-specific table based on the user's role
switch ($role) {
    case 'doctor':
        $query = "UPDATE doctors SET first_name = ?, last_name = ?, phone_number = ? WHERE user_id = ?";
        break;
    case 'patient':
        $query = "UPDATE patients SET first_name = ?, last_name = ?, primary_phone_number = ? WHERE user_id = ?";
        break;
    case 'billing_officer':
        $query = "UPDATE billing_officers SET first_name = ?, last_name = ?, phone_number = ? WHERE user_id = ?";
        break;
    case 'pharmacist':
        $query = "UPDATE pharmacists SET first_name = ?, last_name = ?, phone_number = ? WHERE user_id = ?";
        break;
    case 'receptionist':
        $query = "UPDATE receptionists SET first_name = ?, last_name = ?, phone_number = ? WHERE user_id = ?";
        break;
    default:
        echo json_encode(["message" => "Invalid role provided"]);
        exit;
}

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(["message" => "Prepare failed: " . $conn->error]);
    exit;
}

// Bind parameters (s: string, i: integer)
$stmt->bind_param("sssi", $firstName, $lastName, $contactNumber, $userId);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "message" => "User information updated successfully"]);
} else {
    echo json_encode(["success" => false, "message" => "Error updating user information: " . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
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

// Validate required fields
if (!isset($data['reset_token']) || empty($data['reset_token'])) {
    echo json_encode(["message" => "Reset token is required"]);
    exit;
}

if (!isset($data['new_password']) || empty($data['new_password'])) {
    echo json_encode(["message" => "New password is required"]);
    exit;
}

$resetToken = trim($data['reset_token']);
$newPassword = trim($data['new_password']);

try {
    // Check if the reset token exists and is valid
    $checkQuery = "SELECT user_id, email, role, reset_token_expires FROM users WHERE reset_token = ?";
    $stmt = $conn->prepare($checkQuery);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("s", $resetToken);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        echo json_encode(["message" => "Invalid reset token"]);
        exit;
    }

    // Fetch user details
    $stmt->bind_result($userId, $email, $role, $expiresAt);
    $stmt->fetch();
    $stmt->close();

    // Check if the token has expired
    if (strtotime($expiresAt) < time()) {
        echo json_encode(["message" => "Reset token has expired"]);
        exit;
    }

    // Check if the user is a patient
    if ($role !== 'patient') {
        echo json_encode(["message" => "Password reset is only allowed for patients"]);
        exit;
    }

    // Hash the new password
    $hashedPassword = password_hash($newPassword, PASSWORD_BCRYPT);

    // Update the user's password and invalidate the reset token
    $updateQuery = "UPDATE users 
                    SET password_hash = ?, reset_token = NULL, reset_token_expires = NULL
                    WHERE user_id = ?";
    $stmt = $conn->prepare($updateQuery);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $stmt->bind_param("si", $hashedPassword, $userId);
    $stmt->execute();

    if ($stmt->affected_rows === 1) {
        echo json_encode([
            "success" => true,
            "message" => "Password reset successful"
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Error updating password"
        ]);
    }

} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "message" => "Error: " . $e->getMessage()
    ]);
}
?>
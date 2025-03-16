<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
require "db.php";

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Read JSON input
$json = file_get_contents("php://input");
$data = json_decode($json, true);

// Debugging: Log received data
file_put_contents("debug.log", print_r($data, true));

if (!$data || !isset($data['email']) || !isset($data['password'])) {
    echo json_encode([
        "success" => false,
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
            // Close the first statement before executing another query
            $stmt->close();
            
            // Get detailed user information based on role
            $user_data = getUserDetailsByRole($conn, $user_id, $role);
            
            if ($user_data) {
                echo json_encode([
                    "success" => true,
                    "message" => "Login successful",
                    "role" => $role,
                    "user_id" => $user_id,
                    "user_data" => $user_data
                ]);
            } else {
                echo json_encode([
                    "success" => false,
                    "message" => "User profile not found"
                ]);
            }
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Unauthorized access"
            ]);
        }
    } else {
        echo json_encode([
            "success" => false,
            "message" => "Incorrect password"
        ]);
    }
} else {
    echo json_encode([
        "success" => false,
        "message" => "User not found"
    ]);
}

/**
 * Get detailed user information based on their role
 * 
 * @param mysqli $conn Database connection
 * @param int $user_id User ID
 * @param string $role User role
 * @return array|null User data or null if not found
 */
function getUserDetailsByRole($conn, $user_id, $role) {
    $userData = null;
    
    // Get basic user information
    $basicStmt = $conn->prepare("SELECT email FROM users WHERE user_id = ?");
    $basicStmt->bind_param("i", $user_id);
    $basicStmt->execute();
    $basicStmt->bind_result($email);
    $basicStmt->fetch();
    $basicStmt->close();
    
    // Initialize user data with email from users table
    $userData = [
        "user_id" => $user_id
    ];
    
    // Get role-specific information
    switch ($role) {
        case 'doctor':
            $stmt = $conn->prepare("
                SELECT 
                    doctor_id, first_name, last_name, specialization, 
                    license_number, years_of_experience, phone_number, 
                    profile_picture, email, about, created_at
                FROM doctors 
                WHERE user_id = ?
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->bind_result(
                $doctor_id, $first_name, $last_name, $specialization, 
                $license_number, $years_of_experience, $phone_number, 
                $profile_picture, $doctor_email, $about, $created_at
            );
            
            if ($stmt->fetch()) {
                $userData = array_merge($userData, [
                    "doctor_id" => $doctor_id,
                    "first_name" => $first_name,
                    "last_name" => $last_name,
                    "specialization" => $specialization,
                    "license_number" => $license_number,
                    "years_of_experience" => $years_of_experience,
                    "phone_number" => $phone_number,
                    "profile_picture" => $profile_picture,
                    "email" => $doctor_email,
                    "about" => $about,
                    "created_at" => $created_at
                ]);
            }
            $stmt->close();
            break;
            
        case 'pharmacist':
            $stmt = $conn->prepare("
                SELECT 
                    pharmacist_id, first_name, last_name, 
                    license_number, phone_number, email, created_at
                FROM pharmacists 
                WHERE user_id = ?
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->bind_result(
                $pharmacist_id, $first_name, $last_name, 
                $license_number, $phone_number, $pharmacist_email, $created_at
            );
            
            if ($stmt->fetch()) {
                $userData = array_merge($userData, [
                    "pharmacist_id" => $pharmacist_id,
                    "first_name" => $first_name,
                    "last_name" => $last_name,
                    "license_number" => $license_number,
                    "phone_number" => $phone_number,
                    "email" => $pharmacist_email,
                    "created_at" => $created_at
                ]);
            }
            $stmt->close();
            break;
            
        case 'billing_officer':
            $stmt = $conn->prepare("
                SELECT 
                    billing_officer_id, first_name, last_name, 
                    phone_number, email, created_at
                FROM billing_officers 
                WHERE user_id = ?
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->bind_result(
                $billing_officer_id, $first_name, $last_name, 
                $phone_number, $billing_officer_email, $created_at
            );
            
            if ($stmt->fetch()) {
                $userData = array_merge($userData, [
                    "billing_officer_id" => $billing_officer_id,
                    "first_name" => $first_name,
                    "last_name" => $last_name,
                    "phone_number" => $phone_number,
                    "email" => $billing_officer_email,
                    "created_at" => $created_at
                ]);
            }
            $stmt->close();
            break;
            
        case 'receptionist':
            $stmt = $conn->prepare("
                SELECT 
                    receptionist_id, first_name, last_name, 
                    phone_number, email, created_at
                FROM receptionists 
                WHERE user_id = ?
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->bind_result(
                $receptionist_id, $first_name, $last_name, 
                $phone_number, $receptionist_email, $created_at
            );
            
            if ($stmt->fetch()) {
                $userData = array_merge($userData, [
                    "receptionist_id" => $receptionist_id,
                    "first_name" => $first_name,
                    "last_name" => $last_name,
                    "phone_number" => $phone_number,
                    "email" => $receptionist_email,
                    "created_at" => $created_at
                ]);
            }
            $stmt->close();
            break;
            
        case 'admin':
            $stmt = $conn->prepare("
                SELECT 
                    admin_id, first_name, last_name, 
                    phone_number, email, created_at
                FROM admins 
                WHERE user_id = ?
            ");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->bind_result(
                $admin_id, $first_name, $last_name, 
                $phone_number, $admin_email, $created_at
            );
            
            if ($stmt->fetch()) {
                $userData = array_merge($userData, [
                    "admin_id" => $admin_id,
                    "first_name" => $first_name,
                    "last_name" => $last_name,
                    "phone_number" => $phone_number,
                    "email" => $admin_email,
                    "created_at" => $created_at
                ]);
            }
            $stmt->close();
            break;
    }
    
    return $userData;
}
?>
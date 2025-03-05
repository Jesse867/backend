<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
error_reporting(E_ALL);
ini_set('display_errors', 1);
require "db.php";

try {
    $conn->begin_transaction();

    // Handle file upload
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
        $targetDir = "assets/images/";
        $fileName = basename($_FILES['profile_picture']['name']);
        $targetPath = $targetDir . $fileName;

        // Validate file type
        $fileType = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));
        if (!in_array($fileType, ['jpg', 'jpeg', 'png', 'gif'])) {
            throw new Exception("Invalid image format. Only JPG, PNG, and GIF are allowed.");
        }

        // Validate file size (5MB max)
        if ($_FILES['profile_picture']['size'] > 5 * 1024 * 1024) {
            throw new Exception("Image size exceeds 5MB limit.");
        }

        // Move uploaded file
        if (!move_uploaded_file($_FILES['profile_picture']['tmp_name'], $targetPath)) {
            throw new Exception("Failed to upload profile picture.");
        }

        // Generate unique filename to avoid conflicts
        $uniqueFileName = uniqid() . "_" . $fileName;
        $newPath = $targetDir . $uniqueFileName;
        rename($targetPath, $newPath);
        $profile_picture = $uniqueFileName;
    }

    $json = file_get_contents("php://input");
    $data = json_decode($json, true);

    if (!$data) {
        throw new Exception("Invalid JSON input");
    }

    // Check required fields
    $requiredFields = ['email', 'password', 'first_name', 'last_name', 'role', 'phone_number'];
    if (isset($data['role']) && $data['role'] === 'doctor') {
        $requiredFields = array_merge($requiredFields, ['license_number', 'years_of_experience', 'specialization', 'about']);
    }

    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }

    $email = trim($data['email']);
    $password = trim($data['password']);
    $first_name = trim($data['first_name']);
    $last_name = trim($data['last_name']);
    $role = trim($data['role']);
    $phone_number = trim($data['phone_number']);
    $license_number = isset($data['license_number']) ? trim($data['license_number']) : null;
    $years_of_experience = isset($data['years_of_experience']) ? (int)$data['years_of_experience'] : null;
    $specialization = isset($data['specialization']) ? trim($data['specialization']) : null;
    $about = isset($data['about']) ? trim($data['about']) : null;

    // Validate role
    $valid_roles = ['doctor', 'pharmacist', 'billing_officer', 'receptionist', 'admin'];
    if (!in_array($role, $valid_roles)) {
        throw new Exception("Invalid role");
    }

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception("Invalid email format");
    }

    // Check if email exists
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        throw new Exception("Email already registered");
    }

    $stmt->close();

    // Generate unique 10-digit hospital ID
    $maxInt = 2147483647;
    $minInt = 1000000000;

    $hospital_number = mt_rand($minInt, $maxInt);

    // Check if this number already exists in the database
    $checkStmt = $conn->prepare("SELECT hospital_number FROM users WHERE hospital_number = ?");
    $checkStmt->bind_param("i", $hospital_number);
    $checkStmt->execute();

    while ($checkStmt->num_rows > 0) {
        $hospital_number = mt_rand($minInt, $maxInt);
        $checkStmt->bind_param("i", $hospital_number);
        $checkStmt->execute();
    }

    $checkStmt->close();

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);

    // Insert user into `users`
    $stmt = $conn->prepare("INSERT INTO users (email, password_hash, role, hospital_number) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssss", $email, $hashed_password, $role, $hospital_number);

    if (!$stmt->execute()) {
        throw new Exception("Error registering user");
    }

    $user_id = $stmt->insert_id;
    $stmt->close();

    // Insert into role-specific table
    switch ($role) {
        case 'doctor':
            if (!$license_number || !$years_of_experience || !$specialization || !$about) {
                throw new Exception("License number, years of experience, specialization, and about are required for doctors");
            }
            $stmt = $conn->prepare("INSERT INTO doctors (user_id, first_name, last_name, phone_number, email, license_number, profile_picture, years_of_experience, specialization, about) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->bind_param("issssssiss", $user_id, $first_name, $last_name, $phone_number, $email, $license_number, $profile_picture, $years_of_experience, $specialization, $about);
            break;

        case 'pharmacist':
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

    if (!$stmt->execute()) {
        throw new Exception("Error inserting into role-specific table");
    }

    $conn->commit();
    $stmt->close();

    // Return the profile picture URL in the response
    $imageUrl = isset($profile_picture) ? "assets/images/$profile_picture" : null;
    echo json_encode([
        "message" => "User registered successfully",
        "user_id" => $user_id,
        "role" => $role,
        "profile_picture" => $imageUrl
    ]);
} catch (Exception $e) {
    $conn->rollback();
    error_log("Registration error: " . $e->getMessage());
    echo json_encode([
        "message" => "Registration failed: " . $e->getMessage(),
        "error" => true
    ]);
} finally {
    $conn->close();
}

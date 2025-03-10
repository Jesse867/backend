<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
error_reporting(E_ALL);
ini_set('display_errors', 0);
require "db.php";

// Read and decode JSON input
$json = file_get_contents("php://input");
$data = json_decode($json, true);

if (!$data) {
    echo json_encode(["error" => "Invalid JSON input"]);
    exit;
}

// List required fields based on role
$requiredFields = [
    "common" => ["email", "password", "confirmPassword", "first_name", "last_name", "role", "phone_number"],
    "doctor" => ["license_number", "years_of_experience", "specialization", "about"],
    "pharmacist" => ["license_number"],
    "billing_officer" => [],
    "receptionist" => [],
    "admin" => []
];

// Validate common fields
foreach ($requiredFields["common"] as $field) {
    if (!isset($data[$field])) {
        echo json_encode(["error" => "Missing field: $field"]);
        exit;
    }
}

// Validate role-specific fields
if (isset($requiredFields[$data["role"]])) {
    foreach ($requiredFields[$data["role"]] as $field) {
        if (!isset($data[$field])) {
            echo json_encode(["error" => "Missing field: $field"]);
            exit;
        }
    }
}

// Check password match
if ($data["password"] !== $data["confirmPassword"]) {
    echo json_encode(["error" => "Passwords do not match"]);
    exit;
}

// Retrieve and sanitize values
$email = trim($data["email"]);
$password = trim($data["password"]);
$firstName = trim($data["first_name"]);
$lastName = trim($data["last_name"]);
$role = trim($data["role"]);
$phoneNumber = trim($data["phone_number"]);

// Password: hash it
$passwordHash = password_hash($password, PASSWORD_BCRYPT);

// Generate unique 10-digit hospital ID
$maxInt = 2147483647;
$minInt = 1000000000;
$hospital_number = random_int($minInt, $maxInt);

try {
    $checkStmt = $conn->prepare("SELECT hospital_number FROM users WHERE hospital_number = ?");
    $checkStmt->bind_param("i", $hospital_number);
    $checkStmt->execute();
    $checkStmt->store_result();

    while ($checkStmt->num_rows > 0) {
        $hospital_number = random_int($minInt, $maxInt);
        $checkStmt->bind_param("i", $hospital_number);
        $checkStmt->execute();
        $checkStmt->store_result();
    }

    $checkStmt->close();
} catch (Exception $e) {
    error_log("Error generating hospital number: " . $e->getMessage());
    echo json_encode(["error" => "Error generating hospital number"]);
    exit;
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["error" => "Invalid email format"]);
    exit;
}

// Check if email exists
try {
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo json_encode(["error" => "Email already registered"]);
        $stmt->close();
        exit;
    }

    $stmt->close();
} catch (Exception $e) {
    error_log("Error checking email: " . $e->getMessage());
    echo json_encode(["error" => "Error checking email"]);
    exit;
}

// Insert into `users`
try {
    $stmt = $conn->prepare("INSERT INTO users (email, password_hash, role, hospital_number) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("sssi", $email, $passwordHash, $role, $hospital_number);

    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        $stmt->close();

        // Insert into role-specific table
        switch ($role) {
            case 'doctor':
                $licenseNumber = trim($data["license_number"]);
                $yearsExperience = (int)$data["years_of_experience"];
                $specialization = trim($data["specialization"]);
                $about = trim($data["about"]);

                // Validate profile picture if provided
                if (isset($data["profile_picture"])) {
                    $profilePicture = trim($data["profile_picture"]);
                    // Validate URL format
                    if (!filter_var($profilePicture, FILTER_VALIDATE_URL)) {
                        throw new Exception("Invalid profile picture URL format");
                    }
                    // Check if URL points to an image
                    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                    $extension = strtolower(substr(strrchr($profilePicture, '.'), 1));
                    if (!in_array($extension, $allowedExtensions)) {
                        throw new Exception("Profile picture must be an image file");
                    }
                } else {
                    $profilePicture = "";
                }

                $stmt = $conn->prepare("
                    INSERT INTO doctors (
                        user_id, hospital_number, first_name, last_name, 
                        phone_number, email, license_number, profile_picture, 
                        years_of_experience, specialization, about
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "iissssssiss",
                    $user_id,
                    $hospital_number,
                    $firstName,
                    $lastName,
                    $phoneNumber,
                    $email,
                    $licenseNumber,
                    $profilePicture,
                    $yearsExperience,
                    $specialization,
                    $about
                );
                break;

            case 'pharmacist':
                $licenseNumber = trim($data["license_number"]);
                $stmt = $conn->prepare("
                    INSERT INTO pharmacists (
                        user_id, hospital_number, first_name, last_name, 
                        phone_number, email, license_number
                    ) VALUES (?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "iisssss",
                    $user_id,
                    $hospital_number,
                    $firstName,
                    $lastName,
                    $phoneNumber,
                    $email,
                    $licenseNumber
                );
                break;

            case 'billing_officer':
                $stmt = $conn->prepare("
                    INSERT INTO billing_officers (
                        user_id, hospital_number, first_name, last_name, 
                        phone_number, email
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "iissss",
                    $user_id,
                    $hospital_number,
                    $firstName,
                    $lastName,
                    $phoneNumber,
                    $email,
                );
                break;

            case 'receptionist':
                $stmt = $conn->prepare("
                    INSERT INTO receptionists (
                        user_id, hospital_number, first_name, last_name, 
                        phone_number, email
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "iissss",
                    $user_id,
                    $hospital_number,
                    $firstName,
                    $lastName,
                    $phoneNumber,
                    $email
                );
                break;

            case 'admin':
                $stmt = $conn->prepare("
                    INSERT INTO admins (
                        user_id, hospital_number, first_name, last_name, 
                        phone_number, email
                    ) VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->bind_param(
                    "iissss",
                    $user_id,
                    $hospital_number,
                    $firstName,
                    $lastName,
                    $phoneNumber,
                    $email
                );
                break;

            default:
                throw new Exception("Invalid role");
        }

        if ($stmt->execute()) {
            $stmt->close();
            echo json_encode(["success" => true, "message" => "Staff member added successfully"]);
        } else {
            $stmt->close();
            error_log("Error inserting staff details: " . $conn->error);
            echo json_encode([
                "error" => "Error inserting staff details",
                "message" => $conn->error
            ]);
        }
    } else {
        $stmt->close();
        error_log("Error registering staff: " . $conn->error);
        echo json_encode([
            "error" => "Error registering staff",
            "message" => $conn->error
        ]);
    }
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode([
        "error" => true,
        "message" => "Database error occurred: " . $e->getMessage()
    ]);
}

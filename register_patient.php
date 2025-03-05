<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
error_reporting(E_ALL);
ini_set('display_errors', 1);
require "db.php";
//Email configuration
require "PHPMailer/src/PHPMailer.php";
require "PHPMailer/src/SMTP.php";
require "PHPMailer/src/Exception.php";

// Read and decode JSON input
$json = file_get_contents("php://input");
$data = json_decode($json, true);

if (!$data) {
    echo json_encode(["error" => "Invalid JSON input"]);
    exit;
}

// List required fields (adjust as needed)
$required = [
    "firstName",
    "lastName",
    "dateOfBirth",
    "gender",
    "primaryPhoneNumber",
    "email",
    "residentialAddress",
    "emergencyContact",
    "maritalStatus",
    "consentForDataUsage",
    "password",
    "confirmPassword"
];

foreach ($required as $field) {
    if (!isset($data[$field])) {
        echo json_encode(["error" => "Missing field: $field"]);
        exit;
    }
}

// Check password match
if ($data["password"] !== $data["confirmPassword"]) {
    echo json_encode(["error" => "Passwords do not match"]);
    exit;
}

// Retrieve and sanitize values
$firstName   = trim($data["firstName"]);
$middleName  = isset($data["middleName"]) ? trim($data["middleName"]) : "";
$lastName    = trim($data["lastName"]);
$dateOfBirth = trim($data["dateOfBirth"]); // Format: YYYY-MM-DD
$gender      = trim($data["gender"]);
$photoUpload = isset($data["photoUpload"]) ? trim($data["photoUpload"]) : "";
$primaryPhoneNumber   = trim($data["primaryPhoneNumber"]);
$alternatePhoneNumber = isset($data["alternatePhoneNumber"]) ? trim($data["alternatePhoneNumber"]) : "";
$email       = trim($data["email"]);

// Residential Address (nested object)
$resAddress  = $data["residentialAddress"];
$street      = trim($resAddress["street"]);
$city        = trim($resAddress["city"]);
$state       = trim($resAddress["state"]);
$country     = trim($resAddress["country"]);

// Emergency Contact (nested object)
$emergency   = $data["emergencyContact"];
$emergencyName         = trim($emergency["name"]);
$emergencyRelationship = trim($emergency["relationship"]);
$emergencyPhone        = trim($emergency["phoneNumber"]);

// Medical Information (optional)
$bloodGroup             = isset($data["bloodGroup"]) ? trim($data["bloodGroup"]) : "";
$knownAllergies         = isset($data["knownAllergies"]) ? trim($data["knownAllergies"]) : "";
$preExistingConditions  = isset($data["preExistingConditions"]) ? trim($data["preExistingConditions"]) : "";
$primaryPhysician       = isset($data["primaryPhysician"]) ? trim($data["primaryPhysician"]) : "";

// Health Insurance (nested object, optional)
$healthInsurance = isset($data["healthInsurance"]) ? $data["healthInsurance"] : [];
$insuranceNumber   = isset($healthInsurance["insuranceNumber"]) ? trim($healthInsurance["insuranceNumber"]) : "";
$insuranceProvider = isset($healthInsurance["provider"]) ? trim($healthInsurance["provider"]) : "";

// Other fields
$maritalStatus = trim($data["maritalStatus"]);
$occupation    = isset($data["occupation"]) ? trim($data["occupation"]) : "";
$consentForDataUsage = $data["consentForDataUsage"] ? 1 : 0;

// Password: hash it
$password = trim($data["password"]);
$passwordHash = password_hash($password, PASSWORD_BCRYPT);

// Generate unique 10-digit hospital ID
$maxInt = 2147483647; // Maximum value for 32-bit signed integer
$minInt = 1000000000; // Minimum 10-digit number

// Generate a random number within the valid range
$hospital_number = random_int($minInt, $maxInt);

// Check if this number already exists in the database
try {
    $checkStmt = $conn->prepare("SELECT hospital_number FROM users WHERE hospital_number = ?");
    $checkStmt->bind_param("i", $hospital_number);
    $checkStmt->execute();
    $checkStmt->store_result();

    // If it exists, generate a new number
    while ($checkStmt->num_rows > 0) {
        $hospital_number = random_int($minInt, $maxInt);
        $checkStmt->bind_param("i", $hospital_number);
        $checkStmt->execute();
        $checkStmt->store_result();
    }

    // Close the statement
    $checkStmt->close();
} catch (Exception $e) {
    error_log("Error generating hospital number: " . $e->getMessage());
    echo json_encode(["error" => "Error generating hospital number: " . $e->getMessage()]);
    exit;
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(["message" => "Invalid email format"]);
    exit;
}

// Check if email exists
try {
    $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        echo json_encode(["message" => "Email already registered"]);
        $stmt->close();
        exit;
    }

    $stmt->close();
} catch (Exception $e) {
    error_log("Error checking email: " . $e->getMessage());
    echo json_encode(["error" => "Error checking email: " . $e->getMessage()]);
    exit;
}

// Insert into `users`
try {
    $stmt = $conn->prepare("INSERT INTO users (email, password_hash, role, hospital_number) VALUES (?, ?, 'patient', ?)");
    $stmt->bind_param("sss", $email, $passwordHash, $hospital_number);

    if ($stmt->execute()) {
        $user_id = $stmt->insert_id;
        $stmt->close();

        // Insert into `patients`
        $stmt = $conn->prepare("
INSERT INTO patients (
    user_id, hospital_number, first_name, middle_name, last_name, 
    date_of_birth, gender, photo_upload, primary_phone_number, 
    alternate_phone_number, street, city, state, country, 
    emergency_contact_name, emergency_contact_relationship, 
    emergency_contact_phone, blood_group, known_allergies, 
    pre_existing_conditions, primary_physician, 
    insurance_number, insurance_provider, 
    marital_status, occupation, consent_for_data_usage
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");
        $stmt->bind_param(
            "issssssssssssssssssssssssi",
            $user_id,
            $hospital_number,
            $firstName,
            $middleName,
            $lastName,
            $dateOfBirth,
            $gender,
            $photoUpload,
            $primaryPhoneNumber,
            $alternatePhoneNumber,
            $street,
            $city,
            $state,
            $country,
            $emergencyName,
            $emergencyRelationship,
            $emergencyPhone,
            $bloodGroup,
            $knownAllergies,
            $preExistingConditions,
            $primaryPhysician,
            $insuranceNumber,
            $insuranceProvider,
            $maritalStatus,
            $occupation,
            $consentForDataUsage
        );

        if ($stmt->execute()) {
            $stmt->close();

            // Send email with hospital number and password
            try {
                $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'adejsamuel@gmail.com';
                $mail->Password = 'iyng nqfs zlpj ugah';
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;

                $mail->setFrom('adejsamuel@gmail.com', 'Patient Registration');
                $mail->addAddress($email);

                // email image implementation
                $imagePath = 'assets/image/logo-full-light.png';
                $image_cid = 'logo';
                $altText = 'Carpulse Logo';
                $mail->addEmbeddedImage($imagePath, $image_cid, $altText);

                $mail->isHTML(true);
                $mail->Subject = 'Registration Confirmation';
                $mail->Body = "
                    <img src='cid:$image_cid' alt='$altText' style='max-width: 150px; height: auto;'>
                    <br>
                    <h2>Thank you for registering with Carepulse!</h2>
                    <p>Your hospital number: $hospital_number</p>
                    <p>Your password: $password</p>
                    <p>Please keep this information safe for future reference.</p>
                ";

                $mail->send();
                echo json_encode([
                    "message" => "Patient registered successfully. Confirmation email sent!",
                    "hospital_number" => $hospital_number
                ]);
            } catch (Exception $e) {
                error_log("Email sending failed: " . $e->getMessage());
                echo json_encode([
                    "message" => "Registration successful, but email failed to send",
                    "error" => $e->getMessage()
                ]);
            }
        } else {
            $stmt->close();
            error_log("Error inserting patient details: " . $conn->error);
            echo json_encode([
                "message" => "Error inserting patient details",
                "error" => $conn->error
            ]);
        }
    } else {
        $stmt->close();
        error_log("Error registering patient: " . $conn->error);
        echo json_encode([
            "message" => "Error registering patient",
            "error" => $conn->error
        ]);
    }
} catch (Exception $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode([
        "error" => "Database error occurred: " . $e->getMessage()
    ]);
}

<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
error_reporting(E_ALL);
ini_set('display_errors', 1);
require "db.php";

// Include PHPMailer classes
require "PHPMailer/src/PHPMailer.php";
require "PHPMailer/src/SMTP.php";
require "PHPMailer/src/Exception.php";

// Initialize variables
$patient_id = null;
$doctor_id = null;

// Read JSON input
$json = file_get_contents("php://input");
$data = json_decode($json, true);

if (!$data) {
    echo json_encode(["message" => "Invalid JSON input"]);
    exit;
}

// Validate required fields from the form
$requiredFields = ['patientName', 'doctorName', 'appointmentDate', 'appointmentTime', 'reasonForVisit', 'contactEmail'];
foreach ($requiredFields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        echo json_encode(["message" => "Missing or empty field: $field"]);
        exit;
    }
}

$patientName = trim($data['patientName']);
$doctorName = trim($data['doctorName']);
$appointmentDate = trim($data['appointmentDate']); // expected as ISO string from Next.js (YYYY-MM-DD)
$appointmentTime = trim($data['appointmentTime']); // e.g. "09:00 AM"
$reasonForVisit = trim($data['reasonForVisit']);
$contactEmail = trim($data['contactEmail']);

// Combine appointmentDate and appointmentTime into a DATETIME string.
$appointmentDatetime = date("Y-m-d H:i:s", strtotime("$appointmentDate $appointmentTime"));

// Function to validate and get ID based on name
function validateAndGetId($conn, $name, $isPatient = true) {
    $name = trim($name);
    $nameParts = explode(' ', $name);
    $numParts = count($nameParts);
    
    if ($isPatient) {
        if ($numParts < 2 || $numParts > 3) {
            return null;
        }
        $firstName = trim(strtolower($nameParts[0]));
        $lastName = trim(strtolower(end($nameParts)));
        $middleName = $numParts === 3 ? trim(strtolower($nameParts[1])) : null;
        
        $query = "SELECT patient_id FROM patients 
                  WHERE LOWER(first_name) = ? AND LOWER(last_name) = ?";
        if ($middleName) {
            $query .= " AND LOWER(middle_name) = ?";
        }
        
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        
        if ($middleName) {
            $stmt->bind_param("sss", $firstName, $lastName, $middleName);
        } else {
            $stmt->bind_param("ss", $firstName, $lastName);
        }
        
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $stmt->bind_result($patient_id);
            $stmt->fetch();
            $stmt->close();
            return $patient_id;
        }
    } else {
        $nameParts = explode(' ', $name);
        if (count($nameParts) !== 2) {
            return null;
        }
        $firstName = trim(strtolower($nameParts[0]));
        $lastName = trim(strtolower($nameParts[1]));
        
        $query = "SELECT doctor_id FROM doctors 
                  WHERE LOWER(first_name) = ? AND LOWER(last_name) = ?";
        $stmt = $conn->prepare($query);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("ss", $firstName, $lastName);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows > 0) {
            $stmt->bind_result($doctor_id);
            $stmt->fetch();
            $stmt->close();
            return $doctor_id;
        }
    }
    
    return null;
}

// Function to send appointment confirmation email
function sendAppointmentConfirmation($conn, $appointmentId, $contactEmail, $appointmentDatetime, $reasonForVisit) {
    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'adejsamuel@gmail.com';
        $mail->Password = 'iyng nqfs zlpj ugah';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('your_email@gmail.com', 'Appointment Confirmation');
        $mail->addAddress($contactEmail);

        // email image implementation
        $imagePath = 'assets/image/logo-full-light.png';
        $image_cid = 'logo';
        $altText = 'Carpulse Logo';
        $mail->addEmbeddedImage($imagePath, $image_cid, $altText);

        $mail->isHTML(true);
        $mail->Subject = 'Registration Confirmation';
        $mail->Body = "
            <img src='cid:$image_cid' alt='$altText' style='max-width: 150px; height: auto;'>
            <h2>Appointment Confirmation<h2>
            <br>
            <p>Your appointment has been successfully booked.</p>
            <p>Appointment ID: $appointmentId</p>
            <p>Date and Time: $appointmentDatetime</p>
            <p>Reason for Visit: $reasonForVisit</p>
            <p>Status: pending</p>
            <p>Thank you for choosing our services!</p>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

try {
    // Validate and get patient_id
    $patient_id = validateAndGetId($conn, $patientName, true);
    if (!$patient_id) {
        echo json_encode(["message" => "Patient not found"]);
        exit;
    }

    // Validate and get doctor_id
    $doctor_id = validateAndGetId($conn, $doctorName, false);
    if (!$doctor_id) {
        echo json_encode(["message" => "Doctor not found"]);
        exit;
    }

    // Insert the appointment record into the database with status set to 'pending'
    $query = "INSERT INTO appointments (
        patient_id, doctor_id, appointment_datetime, reason_for_visit, contact_email, status
    ) VALUES (?, ?, ?, ?, ?, 'pending')";
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo json_encode(["message" => "Prepare failed: " . $conn->error]);
        exit;
    }
    $stmt->bind_param("iisss", $patient_id, $doctor_id, $appointmentDatetime, $reasonForVisit, $contactEmail);

    if ($stmt->execute()) {
        // Send appointment confirmation email
        $success = sendAppointmentConfirmation($conn, $stmt->insert_id, $contactEmail, $appointmentDatetime, $reasonForVisit);
        
        if ($success) {
            echo json_encode([
                "success" => true,
                "message" => "Appointment booked successfully. Confirmation email sent!",
                "appointmentId" => $stmt->insert_id,
                "status" => "pending"
            ]);
        } else {
            echo json_encode([
                "success" => true,
                "message" => "Appointment booked successfully, but email failed to send.",
                "appointmentId" => $stmt->insert_id,
                "status" => "pending"
            ]);
        }
    } else {
        echo json_encode(["success" => false, "message" => "Error booking appointment: " . $stmt->error]);
    }
    $stmt->close();
    $conn->close();
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    echo json_encode(["success" => false, "message" => "Error: " . $e->getMessage()]);
}
?>
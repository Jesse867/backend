<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, PUT, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
error_reporting(E_ALL);
ini_set('display_errors', 1);
require "db.php";

// Include PHPMailer classes
require "PHPMailer/src/PHPMailer.php";
require "PHPMailer/src/SMTP.php";
require "PHPMailer/src/Exception.php";

// Read JSON input
$json = file_get_contents("php://input");
$data = json_decode($json, true);

if (!$data) {
    echo json_encode(["message" => "Invalid JSON input"]);
    exit;
}

// Validate required fields
$requiredFields = ['appointmentId', 'userId', 'role'];
foreach ($requiredFields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        echo json_encode(["message" => "Missing or empty field: $field"]);
        exit;
    }
}

$appointmentId = intval($data['appointmentId']);
$userId = intval($data['userId']);
$role = trim($data['role']);

// Verify user role
if ($role !== 'receptionist' && $role !== 'doctor') {
    echo json_encode(["message" => "Unauthorized: Only receptionists and doctors can confirm appointments"]);
    exit;
}

// First, retrieve the appointment details
$stmt = $conn->prepare("SELECT appointment_id, status, doctor_id, contact_email, appointment_datetime, reason_for_visit FROM appointments WHERE appointment_id = ?");
$stmt->bind_param("i", $appointmentId);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows == 0) {
    echo json_encode(["message" => "Appointment not found"]);
    exit;
}
$stmt->bind_result($dbAppointmentId, $status, $doctorId, $contactEmail, $appointmentDatetime, $reasonForVisit);
$stmt->fetch();
$stmt->close();

// Check if the appointment is already confirmed, completed, or cancelled
if ($status === "confirmed" || $status === "rejected" || $status === "cancelled") {
    echo json_encode(["message" => "Cannot confirm an already confirmed, rejected, or cancelled appointment"]);
    exit;
}

// Additional validation for doctors
if ($role === 'doctor') {
    // Get the user_id from doctors table based on doctor_id
    $checkDoctorStmt = $conn->prepare("SELECT user_id FROM doctors WHERE doctor_id = ?");
    $checkDoctorStmt->bind_param("i", $doctorId);
    $checkDoctorStmt->execute();
    $checkDoctorStmt->store_result();
    
    if ($checkDoctorStmt->num_rows == 0) {
        echo json_encode(["message" => "Doctor not found for this appointment"]);
        exit;
    }
    
    $checkDoctorStmt->bind_result($doctorUserId);
    $checkDoctorStmt->fetch();
    $checkDoctorStmt->close();
    
    // Compare with logged in user
    if ($doctorUserId !== $userId) {
        echo json_encode(["message" => "Doctor can only confirm their own appointments"]);
        exit;
    }
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
        $altText = 'Your Logo';
        $mail->addEmbeddedImage($imagePath, $image_cid, $altText);

        $mail->isHTML(true);
        $mail->Subject = 'Appointment Confirmation';
        $mail->Body = "
    <div style='max-width: 800px; margin: 0 auto; font-family: Arial, sans-serif; color: #333333;'>
        <!-- Header Section -->
        <div style='text-align: center; padding: 20px 0;'>
            <img src='cid:$image_cid' alt='$altText' style='max-width: 150px; height: auto; margin-bottom: 15px;'>
            <h1 style='color: #2c3e50; font-size: 24px; margin: 0;'>Appointment Confirmation</h1>
            <p style='color: #7f8c8d; font-size: 14px;'>Thank you for choosing our services!</p>
        </div>

        <!-- Content Section -->
        <div style='background-color: #f9f9f9; padding: 30px; border-radius: 8px; margin: 20px 0;'>
            <div style='max-width: 600px; margin: 0 auto;'>
                <p style='color: #34495e; font-size: 16px; margin-bottom: 15px;'>
                    Your appointment has been successfully confirmed. Below are the details of your appointment:
                </p>

                <!-- Appointment Details -->
                <div style='background-color: #ffffff; padding: 25px; border-radius: 6px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);'>
                    <div style='margin-bottom: 15px;'>
                        <strong style='color: #2c3e50; font-size: 14px; display: block; margin-bottom: 5px;'>Appointment ID:</strong>
                        <span style='color: #7f8c8d; font-size: 14px;'>$appointmentId</span>
                    </div>
                    <div style='margin-bottom: 15px;'>
                        <strong style='color: #2c3e50; font-size: 14px; display: block; margin-bottom: 5px;'>Date and Time:</strong>
                        <span style='color: #7f8c8d; font-size: 14px;'>$appointmentDatetime</span>
                    </div>
                    <div style='margin-bottom: 15px;'>
                        <strong style='color: #2c3e50; font-size: 14px; display: block; margin-bottom: 5px;'>Reason for Visit:</strong>
                        <span style='color: #7f8c8d; font-size: 14px;'>$reasonForVisit</span>
                    </div>
                    <div style='margin-bottom: 15px;'>
                        <strong style='color: #2c3e50; font-size: 14px; display: block; margin-bottom: 5px;'>Status:</strong>
                        <span style='color: #27ae60; font-size: 14px; font-weight: bold;'>Confirmed</span>
                    </div>
                </div>

            </div>
        </div>

        <!-- Footer Section -->
        <div style='text-align: center; padding: 20px 0; margin-top: 30px; border-top: 1px solid #e7e7e7;'>
            <p style='color: #7f8c8d; font-size: 12px; margin: 0;'>
                © 2024 CarePulse. All rights reserved.<br>
                Contact us: hellocarepaulse@carepaulse.com | Tel: 0802 161 7030
            </p>
        </div>
    </div>
";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

// Update the appointment status to 'confirmed'
$updateStmt = $conn->prepare("UPDATE appointments SET status = 'confirmed' WHERE appointment_id = ?");
$updateStmt->bind_param("i", $appointmentId);
if ($updateStmt->execute()) {
    // Send confirmation email
    $success = sendAppointmentConfirmation($conn, $appointmentId, $contactEmail, $appointmentDatetime, $reasonForVisit);
    
    if ($success) {
        echo json_encode([
            "success" => true,
            "message" => "Appointment confirmed successfully. Confirmation email sent!",
            "appointmentId" => $appointmentId,
            "status" => "confirmed"
        ]);
    } else {
        echo json_encode([
            "success" => true,
            "message" => "Appointment confirmed successfully, but email failed to send.",
            "appointmentId" => $appointmentId,
            "status" => "confirmed"
        ]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Failed to confirm appointment: " . $updateStmt->error]);
}
$updateStmt->close();

$conn->close();
?>
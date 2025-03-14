<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, PUT, OPTIONS");
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
$requiredFields = ['appointmentId', 'newAppointmentDatetime', 'userId', 'role'];
foreach ($requiredFields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        echo json_encode(["message" => "Missing or empty field: $field"]);
        exit;
    }
}

$appointmentId = intval($data['appointmentId']);
$newDatetime = trim($data['newAppointmentDatetime']);
$userId = intval($data['userId']);
$role = trim($data['role']);


// Verify user role
if (!in_array($role, ['doctor', 'receptionist'])) {
    echo json_encode(["message" => "Unauthorized: Only doctors and receptionists can reschedule appointments"]);
    exit;
}

// Additional validation for doctors
if ($role === 'doctor') {
    // Get the doctor_id from the appointment
    $getDoctorStmt = $conn->prepare("SELECT doctor_id FROM appointments WHERE appointment_id = ?");
    $getDoctorStmt->bind_param("i", $appointmentId);
    $getDoctorStmt->execute();
    $getDoctorStmt->store_result();
    
    if ($getDoctorStmt->num_rows == 0) {
        echo json_encode(["message" => "Appointment not found"]);
        exit;
    }
    
    $getDoctorStmt->bind_result($appointmentDoctorId);
    $getDoctorStmt->fetch();
    $getDoctorStmt->close();
    
    // Get the user_id from doctors table based on doctor_id
    $checkDoctorStmt = $conn->prepare("SELECT user_id FROM doctors WHERE doctor_id = ?");
    $checkDoctorStmt->bind_param("i", $appointmentDoctorId);
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
        echo json_encode(["message" => "Doctor can only reschedule their own appointments"]);
        exit;
    }
}

// First, retrieve the appointment details
$stmt = $conn->prepare("SELECT appointment_id, patient_id, status, appointment_datetime FROM appointments WHERE appointment_id = ?");
$stmt->bind_param("i", $appointmentId);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows == 0) {
    echo json_encode(["message" => "Appointment not found"]);
    exit;
}
$stmt->bind_result($dbAppointmentId, $patientId, $status, $currentDatetime);
$stmt->fetch();
$stmt->close();

// Validate new datetime format
$dt = DateTime::createFromFormat("Y-m-d H:i:s", $newDatetime);
if (!$dt) {
    echo json_encode(["message" => "Invalid date format. Expected: YYYY-MM-DD HH:MM:SS"]);
    exit;
}

// Check if the new datetime is in the future
$now = new DateTime();
if ($dt->format("Y-m-d H:i:s") <= $now->format("Y-m-d H:i:s")) {
    echo json_encode(["message" => "New appointment time must be in the future"]);
    exit;
}

// Define minimum and maximum rescheduling times
$minRescheduleTime = $now->modify("+24 hours")->format("Y-m-d H:i:s");
$maxRescheduleTime = $now->modify("+30 days")->format("Y-m-d H:i:s");

if ($dt->format("Y-m-d H:i:s") < $minRescheduleTime) {
    echo json_encode(["message" => "Cannot reschedule within 24 hours of the current time"]);
    exit;
}
if ($dt->format("Y-m-d H:i:s") > $maxRescheduleTime) {
    echo json_encode(["message" => "Cannot reschedule more than 30 days in advance"]);
    exit;
}

// Check if the appointment is already cancelled
if ($status === "cancelled") {
    echo json_encode(["message" => "Cannot reschedule a cancelled appointment"]);
    exit;
}

// Check if the appointment is already completed
if ($status === "completed") {
    echo json_encode(["message" => "Cannot reschedule a completed appointment"]);
    exit;
}

// Check for conflicting appointments
$checkConflictStmt = $conn->prepare("SELECT appointment_id FROM appointments WHERE patient_id = ? AND appointment_datetime = ? AND appointment_id != ?");
$checkConflictStmt->bind_param("isi", $patientId, $newDatetime, $appointmentId);
$checkConflictStmt->execute();
$checkConflictStmt->store_result();

if ($checkConflictStmt->num_rows > 0) {
    echo json_encode(["message" => "New appointment time conflicts with another appointment"]);
    exit;
}
$checkConflictStmt->close();

// Check if the doctor is already booked for the new time
$checkDoctorStmt = $conn->prepare("SELECT appointment_id FROM appointments WHERE doctor_id = (SELECT doctor_id FROM appointments WHERE appointment_id = ?) AND appointment_datetime = ? AND appointment_id != ?");
$checkDoctorStmt->bind_param("isi", $appointmentId, $newDatetime, $appointmentId);
$checkDoctorStmt->execute();
$checkDoctorStmt->store_result();

if ($checkDoctorStmt->num_rows > 0) {
    echo json_encode(["message" => "Doctor is already booked for this time"]);
    exit;
}
$checkDoctorStmt->close();

// Update the appointment
$updateStmt = $conn->prepare("UPDATE appointments SET appointment_datetime = ? WHERE appointment_id = ?");
$updateStmt->bind_param("si", $newDatetime, $appointmentId);
if ($updateStmt->execute()) {
    // Fetch the updated appointment details
    $fetchStmt = $conn->prepare("SELECT appointment_id, appointment_datetime, reason_for_visit, contact_email, status, patient_id, doctor_id FROM appointments WHERE appointment_id = ?");
    $fetchStmt->bind_param("i", $appointmentId);
    $fetchStmt->execute();
    $fetchStmt->store_result();
    if ($fetchStmt->num_rows > 0) {
        // Bind all 7 columns from the SELECT statement
        $fetchStmt->bind_result($appointmentId, $appointmentDatetime, $reasonForVisit, $contactEmail, $status, $patientId, $doctorId);
        $fetchStmt->fetch();
        $fetchStmt->close();

        echo json_encode([
            "success" => true,
            "message" => "Appointment rescheduled successfully",
            "appointment" => [
                "appointment_id" => $appointmentId,
                "appointment_datetime" => $appointmentDatetime,
                "reason_for_visit" => $reasonForVisit,
                "contact_email" => $contactEmail,
                "status" => $status,
                "patient_id" => $patientId,
                "doctor_id" => $doctorId
            ]
        ]);
    } else {
        echo json_encode(["success" => false, "message" => "Appointment not found after update"]);
    }
} else {
    echo json_encode(["success" => false, "message" => "Failed to reschedule appointment: " . $updateStmt->error]);
}
$updateStmt->close();
$conn->close();
?>
<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
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
if (!in_array($role, ['doctor', 'receptionist'])) {
    echo json_encode(["message" => "Unauthorized: Only doctors and receptionists can cancel appointments"]);
    exit;
}

// First, retrieve the appointment details
$stmt = $conn->prepare("SELECT appointment_id, patient_id, status, doctor_id FROM appointments WHERE appointment_id = ?");
$stmt->bind_param("i", $appointmentId);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows == 0) {
    echo json_encode(["message" => "Appointment not found"]);
    exit;
}

$stmt->bind_result($dbAppointmentId, $patientId, $status, $doctorId);
$stmt->fetch();
$stmt->close();

// Check if the appointment is already cancelled or completed
if ($status === "cancelled") {
    echo json_encode(["message" => "Appointment already cancelled"]);
    exit;
}
if ($status === "completed") {
    echo json_encode(["message" => "Cannot cancel a completed appointment"]);
    exit;
}

// Additional validation: If user is doctor, ensure they're cancelling their own appointment
// Additional validation: If user is doctor, ensure they're cancelling their own appointment
if ($role === 'doctor') {
    // Get the doctor's user_id from doctors table based on doctor_id
    $checkDoctorStmt = $conn->prepare("SELECT user_id FROM doctors WHERE doctor_id = ?");
    $checkDoctorStmt->bind_param("i", $doctorId);
    $checkDoctorStmt->execute();
    $checkDoctorStmt->store_result();
    
    if ($checkDoctorStmt->num_rows == 0) {
        echo json_encode(["message" => "Doctor not found"]);
        exit;
    }
    
    $checkDoctorStmt->bind_result($doctorUserId);
    $checkDoctorStmt->fetch();
    $checkDoctorStmt->close();
    
    // Compare the logged in user's ID with the doctor's user_id
    if ($doctorUserId !== $userId) {
        echo json_encode(["message" => "Doctor can only cancel their own appointments"]);
        exit;
    }
}

// Update the appointment status to 'cancelled'
$updateStmt = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE appointment_id = ?");
$updateStmt->bind_param("i", $appointmentId);
if ($updateStmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Appointment cancelled successfully",
        "appointmentId" => $appointmentId,
        "status" => "cancelled"
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Failed to cancel appointment: " . $updateStmt->error
    ]);
}
$updateStmt->close();

$conn->close();
?>
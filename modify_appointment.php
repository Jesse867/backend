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

// Validate required fields
$requiredFields = ['appointmentId', 'patientId', 'action'];
foreach ($requiredFields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        echo json_encode(["message" => "Missing or empty field: $field"]);
        exit;
    }
}

$appointmentId = intval($data['appointmentId']);
$patientId = intval($data['patientId']);
$action = trim($data['action']);

// First, retrieve the appointment to verify it exists and belongs to the patient.
$stmt = $conn->prepare("SELECT patient_id, status FROM appointments WHERE appointment_id = ?");
$stmt->bind_param("i", $appointmentId);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows == 0) {
    echo json_encode(["message" => "Appointment not found"]);
    exit;
}
$stmt->bind_result($dbPatientId, $status);
$stmt->fetch();
$stmt->close();

// Verify that the appointment belongs to the requesting patient.
if ($dbPatientId !== $patientId) {
    echo json_encode(["message" => "Unauthorized: This appointment does not belong to you"]);
    exit;
}

if ($action === "cancel") {
    // Update the appointment status to 'cancelled'
    $updateStmt = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE appointment_id = ?");
    $updateStmt->bind_param("i", $appointmentId);
    if ($updateStmt->execute()) {
        echo json_encode(["success" => true, "message" => "Appointment cancelled successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to cancel appointment: " . $updateStmt->error]);
    }
    $updateStmt->close();
} elseif ($action === "reschedule") {
    // For rescheduling, newAppointmentDatetime is required.
    if (!isset($data['newAppointmentDatetime']) || empty($data['newAppointmentDatetime'])) {
        echo json_encode(["message" => "Missing or empty field: newAppointmentDatetime"]);
        exit;
    }
    $newDatetime = trim($data['newAppointmentDatetime']);
    
    // Validate that the new datetime is in the correct format (YYYY-MM-DD HH:MM:SS)
    $dt = DateTime::createFromFormat("Y-m-d H:i:s", $newDatetime);
    if (!$dt) {
        echo json_encode(["message" => "Invalid date format. Expected: YYYY-MM-DD HH:MM:SS"]);
        exit;
    }
    
    // Optionally, check if the appointment is already completed (and therefore cannot be rescheduled)
    if ($status === "completed") {
        echo json_encode(["message" => "Cannot reschedule a completed appointment"]);
        exit;
    }
    
    // If the appointment was cancelled, you might want to reactivate it (e.g., set status to 'pending').
    $newStatus = ($status === "cancelled") ? "pending" : $status;
    
    $updateStmt = $conn->prepare("UPDATE appointments SET appointment_datetime = ?, status = ? WHERE appointment_id = ?");
    $updateStmt->bind_param("ssi", $newDatetime, $newStatus, $appointmentId);
    if ($updateStmt->execute()) {
        echo json_encode(["success" => true, "message" => "Appointment rescheduled successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => "Failed to reschedule appointment: " . $updateStmt->error]);
    }
    $updateStmt->close();
} else {
    echo json_encode(["message" => "Invalid action. Allowed actions: cancel, reschedule"]);
}

$conn->close();
?>

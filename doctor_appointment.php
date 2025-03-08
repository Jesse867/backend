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
$requiredFields = ['appointmentId', 'doctorId', 'action'];
foreach ($requiredFields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        echo json_encode(["message" => "Missing or empty field: $field"]);
        exit;
    }
}

$appointmentId = intval($data['appointmentId']);
$doctorId      = intval($data['doctorId']);
$action        = strtolower(trim($data['action']));

// Ensure action is either "approve" or "reject"
if (!in_array($action, ["approve", "reject"])) {
    echo json_encode(["message" => "Invalid action. Allowed actions: approve, reject"]);
    exit;
}

// Retrieve the appointment and verify it exists
$stmt = $conn->prepare("SELECT doctor_id, status FROM appointments WHERE appointment_id = ?");
$stmt->bind_param("i", $appointmentId);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows == 0) {
    echo json_encode(["message" => "Appointment not found"]);
    exit;
}
$stmt->bind_result($dbDoctorId, $currentStatus);
$stmt->fetch();
$stmt->close();

// Check that the doctor performing the action is the one assigned to this appointment
if ($dbDoctorId !== $doctorId) {
    echo json_encode(["message" => "Unauthorized: You are not assigned to this appointment"]);
    exit;
}

// Optional: Ensure the appointment is still pending
if ($currentStatus !== "pending") {
    echo json_encode(["message" => "Appointment is not pending (current status: $currentStatus)"]);
    exit;
}

// Set new status based on action
if ($action === "approve") {
    $newStatus = "confirmed";
} else { // action === "reject"
    $newStatus = "cancelled";  // using "cancelled" to indicate a rejection by the doctor
}

// Update the appointment status
$stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE appointment_id = ?");
$stmt->bind_param("si", $newStatus, $appointmentId);
if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Appointment $action successfully",
        "newStatus" => $newStatus
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "Error updating appointment status: " . $stmt->error
    ]);
}
$stmt->close();
$conn->close();
?>

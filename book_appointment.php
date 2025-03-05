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

// Validate required fields from the form
$requiredFields = ['patientName', 'doctorName', 'appointmentDate', 'appointmentTime', 'reasonForVisit', 'contactNumber'];
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
$contactNumber = trim($data['contactNumber']);

// Combine appointmentDate and appointmentTime into a DATETIME string.
// You may need to adjust the time format to match MySQL's expected format.
$appointmentDatetime = date("Y-m-d H:i:s", strtotime("$appointmentDate $appointmentTime"));

// Lookup patient_id based on patient name (assuming names are unique)
// In a real system, you'd have more robust logic (or use IDs from the frontend)
$stmt = $conn->prepare("SELECT patient_id FROM patients WHERE CONCAT(first_name, ' ', last_name) = ?");
$stmt->bind_param("s", $patientName);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows == 0) {
    echo json_encode(["message" => "Patient not found"]);
    exit;
}
$stmt->bind_result($patient_id);
$stmt->fetch();
$stmt->close();

// Lookup doctor_id based on doctor name (assuming names are unique)
$stmt = $conn->prepare("SELECT doctor_id FROM doctors WHERE CONCAT('Doc', '', first_name, ' ', last_name) = ?");
$stmt->bind_param("s", $doctorName);
$stmt->execute();
$stmt->store_result();
if ($stmt->num_rows == 0) {
    echo json_encode(["message" => "Doctor not found"]);
    exit;
}
$stmt->bind_result($doctor_id);
$stmt->fetch();
$stmt->close();

// Insert the appointment record into the database
$query = "INSERT INTO appointments (
    patient_id, doctor_id, appointment_datetime, reason_for_visit, contact_number
) VALUES (?, ?, ?, ?, ?)";
$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(["message" => "Prepare failed: " . $conn->error]);
    exit;
}
$stmt->bind_param("iisss", $patient_id, $doctor_id, $appointmentDatetime, $reasonForVisit, $contactNumber);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true,
        "message" => "Appointment booked successfully",
        "appointmentId" => $stmt->insert_id
    ]);
} else {
    echo json_encode(["success" => false, "message" => "Error booking appointment: " . $stmt->error]);
}
$stmt->close();
$conn->close();
?>

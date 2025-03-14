<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
error_reporting(E_ALL);
ini_set('display_errors', 1);
require "db.php";

// Get the patient ID from the query parameters
$patientId = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : null;

if (!$patientId) {
    echo json_encode(["success" => false, "message" => "Patient ID is required"]);
    exit;
}

// SQL query to get appointments for the specific patient
$query = "
    SELECT 
        a.appointment_id, 
        a.appointment_datetime, 
        a.reason_for_visit, 
        a.contact_email, 
        a.status, 
        p.patient_id, 
        CONCAT(p.first_name, ' ', p.last_name) AS patient_name, 
        p.primary_phone_number AS patient_contact,
        d.doctor_id,
        CONCAT(d.first_name, ' ', d.last_name) AS doctor_name,
        d.specialization AS doctor_specialization
    FROM 
        appointments a
    JOIN 
        patients p ON a.patient_id = p.patient_id
    JOIN 
        doctors d ON a.doctor_id = d.doctor_id
    WHERE 
        p.patient_id = ?
";

$stmt = $conn->prepare($query);
$stmt->bind_param("i", $patientId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $appointments = [];
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
    echo json_encode(["success" => true, "appointments" => $appointments]);
} else {
    echo json_encode(["success" => false, "message" => "No appointments found for this patient"]);
}

$stmt->close();
$conn->close();

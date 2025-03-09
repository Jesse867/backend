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

// Define required fields based on the schema
$requiredFields = [
    'patientId', 'visitDate', 'doctor', 'field', 'temperature', 'weight', 'heartRate', 
    'bloodPressure', 'symptoms', 'allergies', 'diagnosis', 'labTests', 'labTestResults', 
    'doctorNotes', 'nursingNotes'
];

// Validate that all required fields are provided and non-empty
foreach ($requiredFields as $field) {
    if (!isset($data[$field]) || $data[$field] === "") {
        echo json_encode(["message" => "Missing or empty field: $field"]);
        exit;
    }
}

// Assign variables (adjust data types as needed)
$patientId       = intval($data['patientId']);
$visitDate       = trim($data['visitDate']);  // Expected format: YYYY-MM-DD
$doctor          = trim($data['doctor']);
$fieldValue      = trim($data['field']);
$temperature     = floatval($data['temperature']);
$weight          = floatval($data['weight']);
$heartRate       = intval($data['heartRate']);
$bloodPressure   = trim($data['bloodPressure']);
$symptoms        = trim($data['symptoms']);
$allergies       = trim($data['allergies']);
$diagnosis       = trim($data['diagnosis']);
$labTests        = trim($data['labTests']);
$labTestResults  = trim($data['labTestResults']);
$doctorNotes     = trim($data['doctorNotes']);
$nursingNotes    = trim($data['nursingNotes']);

// Build the INSERT query
$query = "INSERT INTO medical_records (
    patient_id, visit_date, doctor, field, temperature, weight, heart_rate, blood_pressure, 
    symptoms, allergies, diagnosis, lab_tests, lab_test_results, doctor_notes, nursing_notes
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(["message" => "Prepare failed: " . $conn->error]);
    exit;
}

// Bind parameters
// "i" for patient_id, "s" for strings, "d" for decimals, "i" for integers.
// In order: patient_id (i), visit_date (s), doctor (s), field (s), temperature (d), weight (d),
// heart_rate (i), blood_pressure (s), symptoms (s), allergies (s), diagnosis (s), lab_tests (s),
// lab_test_results (s), doctor_notes (s), nursing_notes (s)
$bindString = "isssddissssssss";
$stmt->bind_param(
    $bindString,
    $patientId,
    $visitDate,
    $doctor,
    $fieldValue,
    $temperature,
    $weight,
    $heartRate,
    $bloodPressure,
    $symptoms,
    $allergies,
    $diagnosis,
    $labTests,
    $labTestResults,
    $doctorNotes,
    $nursingNotes
);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true, 
        "message" => "Medical record added successfully", 
        "recordId" => $stmt->insert_id
    ]);
} else {
    echo json_encode([
        "success" => false, 
        "message" => "Error adding medical record: " . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>

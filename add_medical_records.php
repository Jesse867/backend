<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
error_reporting(E_ALL);
ini_set('display_errors', 1);
require "db.php";

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Ensure this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(["success" => false, "message" => "Only POST method is allowed"]);
    exit;
}

// Read JSON input
$json = file_get_contents("php://input");
$data = json_decode($json, true);
if (!$data) {
    echo json_encode(["success" => false, "message" => "Invalid JSON input"]);
    exit;
}

// Define required fields based on the schema
$requiredFields = [
    'hospitalNumber', 'visitDate', 'doctor', 'field', 'temperature', 'weight', 
    'symptoms', 'allergies', 'diagnosis', 'labTests', 'labTestResults', 
    'doctorNotes', 'medications'
];

// Validate that all required fields are provided and non-empty
foreach ($requiredFields as $field) {
    if (!isset($data[$field]) || $data[$field] === "") {
        echo json_encode(["success" => false, "message" => "Missing or empty field: $field"]);
        exit;
    }
}

// Validate medications array
if (!is_array($data['medications'])) {
    echo json_encode(["success" => false, "message" => "Medications must be an array"]);
    exit;
}

// Assign variables (adjust data types as needed)
$hospitalNumber  = intval($data['hospitalNumber']);  // Convert to integer for bigint
$visitDate       = trim($data['visitDate']);  // Expected format: YYYY-MM-DD
$doctor          = trim($data['doctor']);
$fieldValue      = trim($data['field']);
$temperature     = floatval($data['temperature']);
$weight          = floatval($data['weight']);

// Optional fields - set to NULL if not provided or empty
$heartRate       = (isset($data['heartRate']) && $data['heartRate'] !== "") ? intval($data['heartRate']) : NULL;
$bloodPressure   = (isset($data['bloodPressure']) && $data['bloodPressure'] !== "") ? trim($data['bloodPressure']) : NULL;

// Required fields
$symptoms        = trim($data['symptoms']);
$allergies       = trim($data['allergies']);
$diagnosis       = trim($data['diagnosis']);
$labTests        = trim($data['labTests']);
$labTestResults  = trim($data['labTestResults']);
$doctorNotes     = trim($data['doctorNotes']);

// Validate date format
if (!preg_match("/^\d{4}-\d{2}-\d{2}$/", $visitDate)) {
    echo json_encode(["success" => false, "message" => "Invalid date format. Use YYYY-MM-DD"]);
    exit;
}

// Get patient_id from hospital_number
$patientQuery = "SELECT patient_id FROM patients WHERE hospital_number = ?";
$patientStmt = $conn->prepare($patientQuery);
if (!$patientStmt) {
    echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
    exit;
}

$patientStmt->bind_param("i", $hospitalNumber);
$patientStmt->execute();
$patientResult = $patientStmt->get_result();

if ($patientResult->num_rows === 0) {
    echo json_encode(["success" => false, "message" => "Invalid hospital number. Patient not found."]);
    exit;
}

$patientRow = $patientResult->fetch_assoc();
$patientId = $patientRow['patient_id'];
$patientStmt->close();

// Validate doctor name and field against doctors table - using case-insensitive comparison
$checkDoctorQuery = "SELECT specialization FROM doctors WHERE LOWER(CONCAT(first_name, ' ', last_name)) = LOWER(?)";
$checkStmt = $conn->prepare($checkDoctorQuery);
if (!$checkStmt) {
    echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
    exit;
}

$checkStmt->bind_param("s", $doctor);
$checkStmt->execute();
$result = $checkStmt->get_result();
$row = $result->fetch_assoc();

if (!$row) {
    echo json_encode(["success" => false, "message" => "Invalid doctor name"]);
    exit;
}

// Validate that the field matches the doctor's specialization (case-insensitive comparison)
if (strtolower($row['specialization']) !== strtolower($fieldValue)) {
    echo json_encode(["success" => false, "message" => "Invalid field. The field must match the doctor's specialization."]);
    exit;
}
$checkStmt->close();

// Build the INSERT query for medical_records
$query = "INSERT INTO medical_records (
    patient_id, visit_date, doctor, field, temperature, weight, heart_rate, blood_pressure, 
    symptoms, allergies, diagnosis, lab_tests, lab_test_results, doctor_notes
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(["success" => false, "message" => "Prepare failed: " . $conn->error]);
    exit;
}

// Bind parameters for medical_records
$stmt->bind_param(
    "isssddisssssss",
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
    $doctorNotes
);

// Start transaction for atomicity
$conn->begin_transaction();

try {
    // Insert medical record
    if (!$stmt->execute()) {
        throw new Exception("Error adding medical record: " . $stmt->error);
    }
    
    $recordId = $stmt->insert_id;
    
    // Insert medications
    $medications = $data['medications'];
    if (!empty($medications)) {
        $medStmt = $conn->prepare("INSERT INTO medications (record_id, name, dosage, frequency) VALUES (?, ?, ?, ?)");
        if (!$medStmt) {
            throw new Exception("Prepare medications failed: " . $conn->error);
        }
        
        foreach ($medications as $medication) {
            // Validate medication data
            if (!isset($medication['name']) || !isset($medication['dosage']) || !isset($medication['frequency'])) {
                throw new Exception("Medication data incomplete. Each medication must have name, dosage, and frequency.");
            }
            
            $medName = trim($medication['name']);
            $medDosage = trim($medication['dosage']);
            $medFrequency = trim($medication['frequency']);
            
            if (empty($medName) || empty($medDosage) || empty($medFrequency)) {
                throw new Exception("Medication fields cannot be empty");
            }
            
            $medStmt->bind_param("isss", $recordId, $medName, $medDosage, $medFrequency);
            if (!$medStmt->execute()) {
                throw new Exception("Error adding medication: " . $medStmt->error);
            }
        }
        $medStmt->close();
    }
    
    // Commit the transaction
    $conn->commit();
    
    echo json_encode([
        "success" => true,
        "message" => "Medical record and medications added successfully",
        "recordId" => $recordId
    ]);
    
} catch (Exception $e) {
    // Roll back the transaction on error
    $conn->rollback();
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

// Clean up
$stmt->close();
$conn->close();
?>
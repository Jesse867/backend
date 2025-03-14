<?php
// Set headers
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
    http_response_code(405);
    echo json_encode([
        "success" => false,
        "message" => "Method Not Allowed",
        "errors" => ["Only POST method is allowed"]
    ]);
    exit;
}

try {
    // Read JSON input
    $json = file_get_contents("php://input");
    if ($json === false) {
        throw new Exception("Failed to read input");
    }
    $data = json_decode($json, true);
    if (!$data) {
        throw new Exception("Invalid JSON input");
    }

    // Required fields for authorization and update
    $requiredFields = ['editorRole', 'editorId', 'recordId', 'fields'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            throw new Exception("Missing or empty field: $field");
        }
    }

    $editorRole   = trim($data['editorRole']);
    $editorId     = trim($data['editorId']);
    $recordId     = intval($data['recordId']);
    $fieldsToUpdate = $data['fields'];

    // --- Permission Checks ---
    if ($editorRole !== "doctor") {
        http_response_code(403);
        echo json_encode([
            "success" => false,
            "message" => "Only doctors are authorized to update medical records",
            "errors" => ["Unauthorized access"]
        ]);
        exit;
    }    

    // Validate and fetch doctor details
    $getDoctorQuery = "SELECT d.*, u.role FROM doctors d 
                      JOIN users u ON d.user_id = u.user_id 
                      WHERE d.user_id = ?";
    $getDoctorStmt = $conn->prepare($getDoctorQuery);
    if (!$getDoctorStmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $getDoctorStmt->bind_param("i", $editorId);
    $getDoctorStmt->execute();
    $doctorResult = $getDoctorStmt->get_result();

    if ($doctorResult->num_rows === 0) {
        throw new Exception("Doctor not found or unauthorized");
    }

    $doctor = $doctorResult->fetch_assoc();
    $doctorName = trim($doctor['first_name'] . ' ' . $doctor['last_name']);

    // Verify record exists and belongs to this doctor
    $checkQuery = "SELECT record_id, doctor FROM medical_records WHERE record_id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    if (!$checkStmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    $checkStmt->bind_param("i", $recordId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Medical record not found");
    }

    $record = $result->fetch_assoc();

    // Get the doctor's user_id from medical_records
    $doctorUserIdQuery = "SELECT user_id FROM doctors WHERE first_name = ? AND last_name = ?";
    $doctorUserIdStmt = $conn->prepare($doctorUserIdQuery);
    $doctorUserIdStmt->bind_param("ss", $doctor['first_name'], $doctor['last_name']);
    $doctorUserIdStmt->execute();
    $doctorUserIdResult = $doctorUserIdStmt->get_result();

    if ($doctorUserIdResult->num_rows === 0) {
        throw new Exception("Doctor not found in database");
    }

    $doctorUserId = $doctorUserIdResult->fetch_assoc()['user_id'];

    // Verify ownership using user_id
    if ($doctorUserId != $editorId) {
        throw new Exception("You are not authorized to update this medical record");
    }


    // Define allowed fields for update
    $allowedFields = [
        "visitDate",
        "field",
        "temperature",
        "weight",
        "heartRate",
        "bloodPressure",
        "symptoms",
        "allergies",
        "diagnosis",
        "labTests",
        "labTestResults",
        "doctorNotes",
        "medications"
    ];

    // Prepare update fields
    $updateFields = [];
    foreach ($fieldsToUpdate as $key => $value) {
        if (in_array($key, $allowedFields)) {
            // Convert camelCase to snake_case
            $column = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $key));

            switch ($column) {
                case 'temperature':
                case 'weight':
                case 'heart_rate':
                    $value = floatval($value);
                    break;
                case 'blood_pressure':
                    $value = trim($value);
                    if (!preg_match('/^\d{2,3}\/\d{2,3}$/', $value)) {
                        throw new Exception("Invalid blood pressure format (e.g., 120/80)");
                    }
                    break;
                case 'medications':
                    if (!is_array($value)) {
                        throw new Exception("Medications must be an array");
                    }
                    break;
                default:
                    $value = trim($value);
            }

            $updateFields[$column] = $value;
        }
    }

    if (empty($updateFields)) {
        throw new Exception("No valid fields provided for update");
    }

    // Start transaction
    $conn->begin_transaction();

    try {
        // Build dynamic UPDATE query for medical_records
        $setParts = [];
        $bindTypes = "";
        $bindValues = [];

        foreach ($updateFields as $column => $value) {
            if ($column === 'medications') {
                continue; // Handle medications separately
            }

            $setParts[] = "$column = ?";
            $bindTypes .= (is_string($value) ? "s" : "d");
            $bindValues[] = $value;
        }

        if (!empty($setParts)) {
            $setClause = implode(", ", $setParts);
            $query = "UPDATE medical_records SET $setClause WHERE record_id = ?";
            $bindTypes .= "i";
            $bindValues[] = $recordId;

            $stmt = $conn->prepare($query);
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param($bindTypes, ...$bindValues);
            if (!$stmt->execute()) {
                throw new Exception("Error updating medical record: " . $stmt->error);
            }
        }

        // Handle medications
        if (isset($updateFields['medications'])) {
            // Delete existing medications
            $deleteMedicationsQuery = "DELETE FROM medications WHERE record_id = ?";
            $deleteStmt = $conn->prepare($deleteMedicationsQuery);
            $deleteStmt->bind_param("i", $recordId);
            $deleteStmt->execute();
        
            // Insert new medications
            if (!empty($updateFields['medications'])) {
                $medications = $updateFields['medications'];
                $insertMedicationsQuery = "INSERT INTO medications (record_id, name, dosage, frequency) VALUES (?, ?, ?, ?)";
                $insertStmt = $conn->prepare($insertMedicationsQuery);
        
                foreach ($medications as $medication) {
                    if (!isset($medication['name'], $medication['dosage'], $medication['frequency'])) {
                        throw new Exception("Invalid medication format");
                    }
        
                    // Assign trimmed values to variables first
                    $medName = trim($medication['name']);
                    $medDosage = trim($medication['dosage']);
                    $medFrequency = trim($medication['frequency']);
        
                    $insertStmt->bind_param(
                        "ssss",
                        $recordId,
                        $medName,
                        $medDosage,
                        $medFrequency
                    );
                    $insertStmt->execute();
                }
            }
        }


        // Commit transaction
        $conn->commit();

        http_response_code(200);
        echo json_encode([
            "success" => true,
            "message" => "Medical record updated successfully",
            "recordId" => $recordId
        ]);
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        http_response_code(500);
        echo json_encode([
            "success" => false,
            "message" => "Error updating medical record",
            "errors" => [$e->getMessage()]
        ]);
    } finally {
        // Clean up
        if (isset($stmt)) {
            $stmt->close();
        }
        $conn->close();
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => "Invalid request",
        "errors" => [$e->getMessage()]
    ]);
}

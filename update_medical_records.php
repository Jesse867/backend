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

// Check required fields
if (!isset($data['recordId']) || empty($data['recordId'])) {
    echo json_encode(["message" => "Missing or empty field: recordId"]);
    exit;
}
if (!isset($data['fields']) || empty($data['fields'])) {
    echo json_encode(["message" => "Missing or empty field: fields"]);
    exit;
}

$recordId = intval($data['recordId']);
$fieldsToUpdate = $data['fields'];

// Define allowed fields for update (exclude non-updatable columns like record_id or created_at)
$allowedFields = [
    "visitDate", "doctor", "field", "temperature", "weight", "heartRate",
    "bloodPressure", "symptoms", "allergies", "diagnosis", "labTests",
    "labTestResults", "doctorNotes", "nursingNotes"
];

// Prepare an array to hold valid update fields after filtering
$updateFields = [];
foreach ($fieldsToUpdate as $key => $value) {
    if (in_array($key, $allowedFields)) {
        // Convert camelCase to snake_case
        $column = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $key));
        $updateFields[$column] = $value;
    }
}

if (empty($updateFields)) {
    echo json_encode(["message" => "No valid fields provided for update"]);
    exit;
}

// Build the dynamic UPDATE query
$setParts = [];
$bindTypes = "";
$bindValues = [];
foreach ($updateFields as $column => $value) {
    $setParts[] = "$column = ?";
    // For simplicity, assume all values as strings; adjust if specific columns require different types.
    $bindTypes .= "s";
    $bindValues[] = $value;
}
$setClause = implode(", ", $setParts);
$query = "UPDATE medical_records SET $setClause WHERE record_id = ?";
$bindTypes .= "i";
$bindValues[] = $recordId;

$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(["message" => "Prepare failed: " . $conn->error]);
    exit;
}

$stmt->bind_param($bindTypes, ...$bindValues);

if ($stmt->execute()) {
    echo json_encode([
        "success" => true, 
        "message" => "Medical record updated successfully"
    ]);
} else {
    echo json_encode([
        "success" => false, 
        "message" => "Error updating medical record: " . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>

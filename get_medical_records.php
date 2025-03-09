<?php
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 1);
require "db.php";

// Validate required fields
if (!isset($_GET['patientId']) || empty($_GET['patientId'])) {
    echo json_encode(["message" => "Missing or empty field: patientId"]);
    exit;
}

$patientId = trim($_GET['patientId']);

// Query to get medical records for a specific patient
$query = "SELECT * FROM medical_records WHERE patient_id = ?";
$stmt = $conn->prepare($query);
if (!$stmt) {
    echo json_encode(["message" => "Prepare failed: " . $conn->error]);
    exit;
}
$stmt->bind_param("i", $patientId);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $medicalRecords = [];
    while ($row = $result->fetch_assoc()) {
        $medicalRecords[] = $row;
    }
    echo json_encode(["success" => true, "medicalRecords" => $medicalRecords]);
} else {
    echo json_encode(["success" => false, "message" => "No medical records found"]);
}

$stmt->close();
$conn->close();
?>
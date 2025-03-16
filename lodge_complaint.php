<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
error_reporting(E_ALL);
ini_set('display_errors', 1);
require "db.php";

try {
    // Check if the request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(["success" => false, "message" => "Method not allowed"]);
        exit;
    }

    // Validate required fields
    $requiredFields = ['patientName', 'complaintType', 'subject', 'description', 'incidentDate'];
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            http_response_code(400);
            echo json_encode(["success" => false, "message" => "Missing or empty field: $field"]);
            exit;
        }
    }

    $patientName = trim(strtolower($_POST['patientName']));
    $complaintType = trim($_POST['complaintType']);
    $subject = trim($_POST['subject']);
    $description = trim($_POST['description']);
    $incidentDate = trim($_POST['incidentDate']);

    // Split patient name into parts
    $nameParts = explode(' ', $patientName);
    if (count($nameParts) < 2) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Please enter a valid full name"]);
        exit;
    }

    // Create possible name combinations
    $possibleNames = [];
    $firstName = $nameParts[0];
    $lastName = end($nameParts);
    
    // Add full name with all parts
    $possibleNames[] = $patientName;

    // Add name without middle name(s)
    $possibleNames[] = "$firstName $lastName";

    // Convert possible names array to comma-separated string
    $nameList = implode(', ', $possibleNames);

    // Query the database for any matching name combination
    $stmt = $conn->prepare("
        SELECT patient_id, first_name, middle_name, last_name 
        FROM patients 
        WHERE 
            (LOWER(first_name) = ? AND LOWER(last_name) = ?) 
            OR 
            (LOWER(CONCAT(first_name, ' ', COALESCE(middle_name, ''), ' ', last_name)) IN (?))
    ");
    $stmt->bind_param("sss", $firstName, $lastName, $nameList);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Patient not found"]);
        exit;
    }

    $stmt->bind_result($patientId, $dbFirstName, $dbMiddleName, $dbLastName);
    $stmt->fetch();
    $stmt->close();

    // Insert complaint into database
    $stmt = $conn->prepare(" 
        INSERT INTO complaints (
            patient_id, 
            subject, 
            incident_date, 
            description, 
            complaint_type, 
            status
        ) VALUES (?, ?, ?, ?, ?, 'unreplied')
    ");
    $stmt->bind_param("issss", $patientId, $subject, $incidentDate, $description, $complaintType);

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Failed to lodge complaint: " . $stmt->error]);
        exit;
    }

    $complaintId = $stmt->insert_id;
    $stmt->close();

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "Complaint lodged successfully",
        "complaintId" => $complaintId,
        "patient" => [
            "patientId" => $patientId,
            "name" => "$dbFirstName $dbMiddleName $dbLastName"
        ]
    ]);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "An error occurred while processing your request"
    ]);
}
$conn->close();
?>
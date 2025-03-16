<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
error_reporting(E_ALL);
ini_set('display_errors', 1);
require "db.php";

try {
    // Check if the request method is GET
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        throw new Exception("Method not allowed");
    }

    // Get patient_id from query parameters
    $patientId = isset($_GET['patientId']) ? intval($_GET['patientId']) : null;
    if (!$patientId) {
        throw new Exception("Patient ID is required");
    }

    // Query the database for complaints and related patient information
    $stmt = $conn->prepare("
        SELECT 
            c.complaint_id AS complaintId,
            c.subject,
            c.incident_date AS incidentDate,
            c.description,
            c.complaint_type AS complaintType,
            c.status,
            c.response AS complaintResponse,
            p.first_name AS patientFirstName,
            p.middle_name AS patientMiddleName,
            p.last_name AS patientLastName
        FROM complaints c
        JOIN patients p ON c.patient_id = p.patient_id
        WHERE c.patient_id = ?
        ORDER BY c.incident_date DESC
    ");
    $stmt->bind_param("i", $patientId);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        echo json_encode([
            "success" => true,
            "message" => "No complaints found for this patient",
            "complaints" => []
        ]);
        exit;
    }

    // Fetch all complaints
    $complaints = [];
    $stmt->bind_result(
        $complaintId,
        $subject,
        $incidentDate,
        $description,
        $complaintType,
        $status,
        $complaintResponse,
        $patientFirstName,
        $patientMiddleName,
        $patientLastName
    );

    while ($stmt->fetch()) {
        $complaint = [
            "complaintId" => $complaintId,
            "subject" => $subject,
            "incidentDate" => $incidentDate,
            "description" => $description,
            "complaintType" => $complaintType,
            "status" => $status,
            "response" => $complaintResponse,
            "patient" => [
                "firstName" => $patientFirstName,
                "middleName" => $patientMiddleName,
                "lastName" => $patientLastName
            ]
        ];
        $complaints[] = $complaint;
    }

    echo json_encode([
        "success" => true,
        "message" => "Complaints retrieved successfully",
        "complaints" => $complaints
    ]);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
$conn->close();
?>
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

    // Query the database for all complaints and their attachments
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
            p.last_name AS patientLastName,
            a.attachment_id AS attachmentId,
            a.name AS attachmentName,
            a.type AS attachmentType
        FROM complaints c
        JOIN patients p ON c.patient_id = p.patient_id
        LEFT JOIN complaint_attachments a ON c.complaint_id = a.complaint_id
        ORDER BY c.incident_date DESC
    ");
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        echo json_encode([
            "success" => true,
            "message" => "No complaints found in the database",
            "complaints" => []
        ]);
        exit;
    }

    // Fetch all complaints and their attachments
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
        $patientLastName,
        $attachmentId,
        $attachmentName,
        $attachmentType
    );

    while ($stmt->fetch()) {
        // Create complaint object if not exists
        if (!isset($complaints[$complaintId])) {
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
                ],
                "attachments" => []
            ];
            $complaints[$complaintId] = $complaint;
        }

        // Add attachment if exists
        if ($attachmentId) {
            $complaints[$complaintId]['attachments'][] = [
                "attachmentId" => $attachmentId,
                "name" => $attachmentName,
                "type" => $attachmentType
            ];
        }
    }

    // Convert associative array to indexed array
    $complaints = array_values($complaints);

    echo json_encode([
        "success" => true,
        "message" => "All complaints retrieved successfully",
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
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

    // Get user role from frontend local storage (sent in the request)
    $userRole = isset($_POST['userRole']) ? trim($_POST['userRole']) : '';

    if (empty($userRole)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "User role is required"]);
        exit;
    }

    // Validate user role
    if (!is_string($userRole)) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Invalid user role"]);
        exit;
    }

    // Authorize based on user role
    if ($userRole !== 'receptionist') {
        http_response_code(403);
        echo json_encode(["success" => false, "message" => "Unauthorized - Only receptionists can respond to complaints"]);
        exit;
    }

    // Check for required parameters
    if (!isset($_POST['complaintId']) || !isset($_POST['response'])) {
        http_response_code(400);
        echo json_encode(["success" => false, "message" => "Missing required parameters"]);
        exit;
    }

    $complaintId = intval($_POST['complaintId']);
    $response = trim($_POST['response']);

    // Validate complaintId exists
    $stmt = $conn->prepare("SELECT complaint_id FROM complaints WHERE complaint_id = ?");
    $stmt->bind_param("i", $complaintId);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        http_response_code(404);
        echo json_encode(["success" => false, "message" => "Complaint not found"]);
        exit;
    }

    // Update the complaint with the response and change status to 'replied'
    $stmt = $conn->prepare("
        UPDATE complaints 
        SET response = ?, status = 'replied', updated_at = NOW()
        WHERE complaint_id = ?
    ");
    $stmt->bind_param("si", $response, $complaintId);

    if (!$stmt->execute()) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Failed to save response: " . $stmt->error]);
        exit;
    }

    // Fetch the updated complaint details
    $stmt = $conn->prepare("SELECT * FROM complaints WHERE complaint_id = ?");
    $stmt->bind_param("i", $complaintId);
    $stmt->execute();
    $stmt->bind_result(
        $complaintId,
        $patientId,
        $subject,
        $status,
        $incidentDate,
        $description,
        $response,
        $complaintType,
        $createdAt,
        $updatedAt
    );
    $stmt->fetch();
    $stmt->close();

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "Response saved successfully",
        "complaint" => [
            "complaintId" => $complaintId,
            "patientId" => $patientId,
            "subject" => $subject,
            "status" => $status,
            "incidentDate" => $incidentDate,
            "description" => $description,
            "response" => $response,
            "complaintType" => $complaintType,
            "createdAt" => $createdAt,
            "updatedAt" => $updatedAt
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

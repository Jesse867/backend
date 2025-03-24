<?php
// CORS Headers
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Content-Length");

// Handle OPTIONS requests
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    http_response_code(200);
    die();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

require "db.php";

try {
    // Check if the request method is PUT
    if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
        throw new Exception("Method not allowed");
    }

    // Get the request data
    $data = json_decode(file_get_contents("php://input"), true);
    
    // Validate required parameters
    $requiredFields = ['receipt_id', 'status', 'user_id', 'role'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            throw new Exception("Missing or empty field: $field");
        }
    }

    $receiptId = (int)$data['receipt_id'];
    $newStatus = $data['status'];
    $userId = (int)$data['user_id'];
    $role = trim($data['role']);

    // Verify user role
    if ($role !== 'billing_officer') {
        throw new Exception("Unauthorized: Only billing officers can update receipt status");
    }

    // Check if the user exists and has the correct role
    $checkUserQuery = "SELECT user_id, role FROM users WHERE user_id = ?";
    $checkUserStmt = $conn->prepare($checkUserQuery);
    $checkUserStmt->bind_param("i", $userId);
    $checkUserStmt->execute();
    $userResult = $checkUserStmt->get_result();

    if ($userResult->num_rows === 0) {
        throw new Exception("User not found");
    }

    $userDetails = $userResult->fetch_assoc();
    if ($userDetails['role'] !== 'billing_officer') {
        throw new Exception("Unauthorized: User does not have permission to update receipt status");
    }

    // Get current receipt status
    $checkQuery = "SELECT status FROM receipts WHERE receipt_id = ?";
    $checkStmt = $conn->prepare($checkQuery);
    $checkStmt->bind_param("i", $receiptId);
    $checkStmt->execute();
    $result = $checkStmt->get_result();

    if ($result->num_rows === 0) {
        throw new Exception("Receipt not found");
    }

    $currentStatus = $result->fetch_assoc()['status'];

    // Prevent changing status if it's already cancelled
    if ($currentStatus === 'cancelled' && $newStatus !== 'cancelled') {
        throw new Exception("Cannot modify cancelled receipt");
    }

    // Prevent changing from paid to partially paid
    if ($currentStatus === 'paid' && $newStatus === 'partially paid') {
        throw new Exception("Cannot change receipt status from 'paid' to 'partially paid'");
    }

    // Query to update receipt status
    $query = "UPDATE receipts 
              SET status = ?, 
                  balance_amount = CASE WHEN ? = 'cancelled' THEN 0 ELSE balance_amount END
              WHERE receipt_id = ?";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // Bind parameters
    $stmt->bind_param("sii", $newStatus, $newStatus, $receiptId);

    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode([
            "success" => true,
            "message" => "Receipt status updated successfully",
            "data" => [
                "receipt_id" => $receiptId,
                "new_status" => $newStatus
            ]
        ]);
    } else {
        throw new Exception("No changes made");
    }
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
} finally {
    if ($checkUserStmt) {
        $checkUserStmt->close();
    }
    if ($checkStmt) {
        $checkStmt->close();
    }
    if ($stmt) {
        $stmt->close();
    }
    if ($conn) {
        $conn->close();
    }
}
?>
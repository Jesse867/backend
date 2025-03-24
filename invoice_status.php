<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: PUT, GET, POST, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Content-Length");

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
    $requiredFields = ['invoice_id', 'status', 'user_id', 'role'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            throw new Exception("Missing or empty field: $field");
        }
    }

    $invoiceId = (int)$data['invoice_id'];
    $newStatus = $data['status'];
    $userId = (int)$data['user_id'];
    $role = trim($data['role']);

    // Validate role
    if ($role !== 'billing_officer') {
        throw new Exception("Unauthorized: Only billing officers can update invoice status");
    }

    // Validate status
    $allowedStatuses = ['paid', 'pending', 'cancelled'];
    if (!in_array($newStatus, $allowedStatuses)) {
        throw new Exception("Invalid status. Allowed statuses: " . implode(', ', $allowedStatuses));
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
        throw new Exception("Unauthorized: User does not have permission to update invoice status");
    }

    // Check if the invoice exists
    $checkInvoiceQuery = "SELECT status FROM invoices WHERE invoice_id = ?";
    $checkInvoiceStmt = $conn->prepare($checkInvoiceQuery);
    $checkInvoiceStmt->bind_param("i", $invoiceId);
    $checkInvoiceStmt->execute();
    $invoiceResult = $checkInvoiceStmt->get_result();

    if ($invoiceResult->num_rows === 0) {
        throw new Exception("Invoice not found");
    }

    $currentStatus = $invoiceResult->fetch_assoc()['status'];

    // Prevent changing status if it's already cancelled
    if ($currentStatus === 'cancelled' && $newStatus !== 'cancelled') {
        throw new Exception("Cannot modify cancelled invoice");
    }

    // Query to update invoice status
    $query = "UPDATE invoices 
              SET status = ?
              WHERE invoice_id = ?";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    // Bind parameters
    $stmt->bind_param("si", $newStatus, $invoiceId);

    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode([
            "success" => true,
            "message" => "Invoice status updated successfully",
            "data" => [
                "invoice_id" => $invoiceId,
                "new_status" => $newStatus
            ]
        ]);
        exit; // Add exit to prevent further execution
    } else {
        throw new Exception("Invoice not found or no changes made");
    }
} catch (Exception $e) {
    http_response_code(400);
    $errorResponse = [
        "success" => false,
        "message" => "Unauthorized: User does not have permission to update invoice status"
    ];

    if (strpos($e->getMessage(), "Unauthorized") === false) {
        $errorResponse["message"] = "Error updating invoice status: " . $e->getMessage();
    }

    echo json_encode($errorResponse);
    exit;
} finally {
    if ($checkUserStmt) {
        $checkUserStmt->close();
    }
    if ($checkInvoiceStmt) {
        $checkInvoiceStmt->close();
    }
    if ($stmt) {
        $stmt->close();
    }
    if ($conn) {
        $conn->close();
    }
}

<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
error_reporting(E_ALL);
ini_set('display_errors', 1);
require "db.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);
    
    // Validate required fields
    if (!isset($data['hospital_number']) || 
        !isset($data['total_amount']) ||
        !isset($data['payment_method']) ||
        !isset($data['items'])) {
        
        http_response_code(400);
        echo json_encode(array("message" => "Missing required fields"));
        exit;
    }

    // Set default status to 'paid' if not provided
    $status = isset($data['status']) ? $data['status'] : 'paid';
    
    // Validate status if provided
    if (isset($data['status'])) {
        $allowedStatus = array('paid', 'partially paid', 'cancelled');
        if (!in_array($data['status'], $allowedStatus)) {
            http_response_code(400);
            echo json_encode(array("message" => "Invalid status. Allowed values: paid, partially paid, cancelled"));
            exit;
        }
    }

    // Validate payment method
    $allowedPaymentMethods = array('cash', 'card', 'bank-transfer');
    if (!in_array($data['payment_method'], $allowedPaymentMethods)) {
        http_response_code(400);
        echo json_encode(array("message" => "Invalid payment method. Allowed values: cash, card, bank-transfer"));
        exit;
    }

    // Generate unique receipt number
    $receiptNumber = generateReceiptNumber($conn);

    // Assign balance_amount with a default value
    $balanceAmount = isset($data['balance_amount']) ? $data['balance_amount'] : 0.00;

    try {
        // Insert main receipt
        $sql = "INSERT INTO receipts (receipt_number, date, total_amount, status, balance_amount, payment_method, hospital_number)
                VALUES ('$receiptNumber', NOW(), {$data['total_amount']}, '$status', $balanceAmount, '{$data['payment_method']}', {$data['hospital_number']})";

        if (!$conn->query($sql)) {
            http_response_code(500);
            echo json_encode(array("message" => "Error creating receipt: " . $conn->error));
            exit;
        }

        // Get the auto-generated receipt_id
        $receiptId = $conn->insert_id;

        // Insert receipt items
        foreach ($data['items'] as $item) {
            $sql_item = "INSERT INTO receipt_items (receipt_id, description, amount)
                         VALUES ($receiptId, '{$item['description']}', {$item['amount']})";
            
            if (!$conn->query($sql_item)) {
                http_response_code(500);
                echo json_encode(array("message" => "Error creating receipt item: " . $conn->error));
                exit;
            }
        }

        http_response_code(201);
        $response = array(
            "message" => "Receipt created successfully",
            "receipt_id" => $receiptId,
            "data" => array(
                "receipt_number" => $receiptNumber,
                "status" => $status,
                "total_amount" => $data['total_amount'],
                "balance_amount" => $balanceAmount,
                "payment_method" => $data['payment_method'],
                "hospital_number" => $data['hospital_number'],
                "items" => $data['items']
            )
        );
        echo json_encode($response);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array("message" => "Error: " . $e->getMessage()));
    } finally {
        $conn->close();
    }
}

// Function to generate unique receipt number
function generateReceiptNumber($conn) {
    $prefix = 'RCPT-' . date('Ym');
    $lastReceipt = $conn->query("SELECT MAX(receipt_number) as last FROM receipts WHERE receipt_number LIKE '$prefix%'");
    $lastReceipt = $lastReceipt->fetch_assoc();
    
    if ($lastReceipt['last']) {
        $lastNumber = substr($lastReceipt['last'], -4);
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    // Ensure uniqueness by checking if the generated number already exists
    while ($conn->query("SELECT EXISTS(SELECT 1 FROM receipts WHERE receipt_number = '$prefix" . sprintf('%04d', $newNumber) . "')")->fetch_row()[0]) {
        $newNumber++;
    }
    
    return $prefix . sprintf('%04d', $newNumber);
}
?>
<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
error_reporting(E_ALL);
ini_set('display_errors', 1);
require "db.php";

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get the hospital_number from the URL parameters
    if (!isset($_GET['hospital_number'])) {
        http_response_code(400);
        echo json_encode(array("message" => "Missing required parameter: hospital_number"));
        exit;
    }

    $data['hospital_number'] = $_GET['hospital_number'];

    try {
        // SQL query to fetch patient details, receipts, and receipt items
        $sql = "SELECT 
                    p.patient_id,
                    p.first_name,
                    p.middle_name,
                    p.last_name,
                    p.date_of_birth,
                    p.street,
                    p.city,
                    p.state,
                    p.country,
                    r.receipt_id,
                    r.receipt_number,
                    r.date AS receipt_date,
                    r.status,
                    r.total_amount,
                    r.balance_amount,
                    r.payment_method,
                    ri.item_id,
                    ri.description AS item_description,
                    ri.amount AS item_amount
                FROM patients p
                LEFT JOIN receipts r ON p.hospital_number = r.hospital_number
                LEFT JOIN receipt_items ri ON r.receipt_id = ri.receipt_id
                WHERE p.hospital_number = ?";
        
        // Prepare and bind parameters
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $data['hospital_number']);
        
        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(array("message" => "Error fetching receipts: " . $stmt->error));
            exit;
        }
        
        // Get the result
        $result = $stmt->get_result();
        
        // Fetch all rows
        $receipts = array();
        $patient = array();
        $currentReceiptId = null;

        while ($row = $result->fetch_assoc()) {
            // Build patient details
            if (empty($patient)) {
                $patient = array(
                    "patient_id" => $row['patient_id'],
                    "name" => $row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name'],
                    "date_of_birth" => $row['date_of_birth'],
                    "address" => $row['street'] . ', ' . $row['city'] . ', ' . $row['state'] . ', ' . $row['country'],
                    "hospital_number" => $data['hospital_number']
                );
            }
            
            // Check if current row is for a new receipt
            if ($row['receipt_id'] !== $currentReceiptId) {
                // Add existing receipt to the array if it's not the first
                if (!empty($currentReceiptId)) {
                    $receipts[] = $currentReceipt;
                }
                
                // Initialize new receipt
                $currentReceipt = array(
                    "receipt_id" => $row['receipt_id'],
                    "receipt_number" => $row['receipt_number'],
                    "receipt_date" => $row['receipt_date'],
                    "status" => $row['status'],
                    "total_amount" => $row['total_amount'],
                    "balance_amount" => $row['balance_amount'],
                    "payment_method" => $row['payment_method'],
                    "items" => array()
                );
                
                $currentReceiptId = $row['receipt_id'];
            }
            
            // Add item to the current receipt
            if (!empty($row['item_id'])) {
                $currentReceipt['items'][] = array(
                    "item_id" => $row['item_id'],
                    "description" => $row['item_description'],
                    "amount" => $row['item_amount']
                );
            }
        }

        // Add the last receipt to the array
        if (!empty($currentReceiptId)) {
            $receipts[] = $currentReceipt;
        }
        
        http_response_code(200);
        $response = array(
            "message" => "Patient receipts retrieved successfully",
            "patient" => $patient,
            "receipts" => $receipts
        );
        echo json_encode($response);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(array("message" => "Error: " . $e->getMessage()));
    } finally {
        $conn->close();
    }
}
?>
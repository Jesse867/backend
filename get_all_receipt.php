<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

error_reporting(E_ALL);
ini_set('display_errors', 1);

require "db.php";

try {
    // Query to get all receipts, their associated items, and patient information
    $query = "SELECT 
        r.receipt_id,
        r.receipt_number,
        r.date,
        r.total_amount,
        r.status,
        r.balance_amount,
        r.payment_method,
        r.hospital_number,
        r.created_at,
        ri.item_id,
        ri.description,
        ri.amount,
        ri.created_at AS item_created_at,
        p.patient_id,
        p.first_name,
        p.middle_name,
        p.last_name
    FROM receipts r
    LEFT JOIN receipt_items ri ON r.receipt_id = ri.receipt_id
    LEFT JOIN patients p ON r.hospital_number = p.hospital_number
    ORDER BY r.receipt_id ASC, ri.item_id ASC";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $receipts = [];
        $previousReceiptId = null;

        while ($row = $result->fetch_assoc()) {
            if ($previousReceiptId !== $row['receipt_id']) {
                // New receipt
                $receipt = [
                    "receipt_id" => $row['receipt_id'],
                    "receipt_number" => $row['receipt_number'],
                    "date" => $row['date'],
                    "total_amount" => number_format((float)$row['total_amount'], 2, '.', ''),
                    "status" => $row['status'],
                    "balance_amount" => number_format((float)$row['balance_amount'], 2, '.', ''),
                    "payment_method" => $row['payment_method'],
                    "hospital_number" => (int)$row['hospital_number'],
                    "created_at" => $row['created_at'],
                    "patient_name" => "",
                    "items" => []
                ];

                // Set patient name if available
                if (!empty($row['first_name']) || !empty($row['last_name'])) {
                    $patientName = trim(
                        (!empty($row['first_name']) ? $row['first_name'] : '') . 
                        (!empty($row['middle_name']) ? " {$row['middle_name']}" : '') . 
                        (!empty($row['last_name']) ? " {$row['last_name']}" : '')
                    );
                    $receipt['patient_name'] = $patientName;
                } else {
                    $receipt['patient_name'] = "Patient Information Not Available";
                }

                $receipts[] = $receipt;
                $previousReceiptId = $row['receipt_id'];
            }

            // Add item to the current receipt
            if (!empty($row['item_id'])) {
                $receipts[count($receipts) - 1]['items'][] = [
                    "item_id" => $row['item_id'],
                    "description" => $row['description'],
                    "amount" => number_format((float)$row['amount'], 2, '.', ''),
                    "created_at" => $row['item_created_at']
                ];
            }
        }

        echo json_encode([
            "success" => true,
            "data" => [
                "receipts" => $receipts,
                "totalRecords" => count($receipts)
            ]
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "No receipts found"
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error fetching receipts: " . $e->getMessage()
    ]);
} finally {
    if ($stmt) {
        $stmt->close();
    }
    if ($conn) {
        $conn->close();
    }
}
?>
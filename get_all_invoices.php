<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

error_reporting(E_ALL);
ini_set('display_errors', 1);

require "db.php";

try {
    // Query to get all invoices, their associated items, and patient information
    $query = "SELECT 
        i.invoice_id,
        i.invoice_number,
        i.date,
        i.status,
        i.total_amount,
        i.hospital_number,
        i.created_at,
        ii.id AS item_id,
        ii.description,
        ii.amount,
        p.patient_id,
        p.first_name,
        p.middle_name,
        p.last_name
    FROM invoices i
    LEFT JOIN invoice_items ii ON i.invoice_id = ii.invoice_id
    LEFT JOIN patients p ON i.hospital_number = p.hospital_number
    ORDER BY i.invoice_id ASC, ii.id ASC";

    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $invoices = [];
        $previousInvoiceId = null;

        while ($row = $result->fetch_assoc()) {
            if ($previousInvoiceId !== $row['invoice_id']) {
                // New invoice
                $invoice = [
                    "invoice_id" => $row['invoice_id'],
                    "invoice_number" => $row['invoice_number'],
                    "date" => $row['date'],
                    "status" => $row['status'],
                    "total_amount" => number_format((float)$row['total_amount'], 2, '.', ''),
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
                    $invoice['patient_name'] = $patientName;
                } else {
                    $invoice['patient_name'] = "Patient Information Not Available";
                }

                $invoices[] = $invoice;
                $previousInvoiceId = $row['invoice_id'];
            }

            // Add item to the current invoice
            if (!empty($row['item_id'])) {
                $invoices[count($invoices) - 1]['items'][] = [
                    "item_id" => $row['item_id'],
                    "description" => $row['description'],
                    "amount" => number_format((float)$row['amount'], 2, '.', '')
                ];
            }
        }

        echo json_encode([
            "success" => true,
            "data" => [
                "invoices" => $invoices,
                "totalRecords" => count($invoices)
            ]
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "message" => "No invoices found"
        ]);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error fetching invoices: " . $e->getMessage()
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
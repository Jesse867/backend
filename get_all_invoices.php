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
        // SQL query to fetch patient details and their invoices
        $sql = "SELECT 
                    p.patient_id,
                    p.first_name,
                    p.middle_name,
                    p.last_name,
                    p.hospital_number,
                    i.invoice_id,
                    i.invoice_number,
                    i.date AS invoice_date,
                    i.status,
                    i.total_amount
                FROM patients p
                LEFT JOIN invoices i ON p.hospital_number = i.hospital_number
                WHERE p.hospital_number = ?";
        
        // Prepare and bind parameters
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("s", $data['hospital_number']);
        
        if (!$stmt->execute()) {
            http_response_code(500);
            echo json_encode(array("message" => "Error fetching invoices: " . $stmt->error));
            exit;
        }
        
        // Get the result
        $result = $stmt->get_result();
        
        // Fetch all rows
        $invoices = array();
        $patient = array();

        while ($row = $result->fetch_assoc()) {
            // Build patient details
            if (empty($patient)) {
                $patient = array(
                    "patient_id" => $row['patient_id'],
                    "name" => $row['first_name'] . ' ' . $row['middle_name'] . ' ' . $row['last_name'],
                    "hospital_number" => $row['hospital_number']
                );
            }
            
            // Add invoice to the array
            $invoices[] = array(
                "invoice_id" => $row['invoice_id'],
                "invoice_number" => $row['invoice_number'],
                "invoice_date" => $row['invoice_date'],
                "status" => $row['status'],
                "total_amount" => $row['total_amount']
            );
        }
        
        http_response_code(200);
        $response = array(
            "message" => "Patient invoices retrieved successfully",
            "patient" => $patient,
            "invoices" => $invoices
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

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
        !isset($data['items'])) {
        
        http_response_code(400);
        echo json_encode(array("message" => "Missing required fields"));
        exit;
    }

    // Set default status to 'pending' if not provided
    $status = isset($data['status']) ? $data['status'] : 'pending';
    
    // Validate status if provided
    if (isset($data['status'])) {
        $allowedStatus = array('paid', 'pending', 'cancelled');
        if (!in_array($data['status'], $allowedStatus)) {
            http_response_code(400);
            echo json_encode(array("message" => "Invalid status. Allowed values: paid, pending, cancelled, partially paid"));
            exit;
        }
    }

    // Generate unique invoice number
    $invoiceNumber = generateInvoiceNumber($conn);

    try {
        // Insert main invoice
        $sql = "INSERT INTO invoices (invoice_number, date, status, total_amount, hospital_number)
                VALUES ('$invoiceNumber', NOW(), '$status', {$data['total_amount']}, {$data['hospital_number']})";

        if (!$conn->query($sql)) {
            http_response_code(500);
            echo json_encode(array("message" => "Error creating invoice: " . $conn->error));
            exit;
        }

        // Get the auto-generated invoice_id
        $invoiceId = $conn->insert_id;

        // Insert invoice items
        foreach ($data['items'] as $item) {
            $sql_item = "INSERT INTO invoice_items (invoice_id, description, amount)
                         VALUES ($invoiceId, '{$item['description']}', {$item['amount']})";
            
            if (!$conn->query($sql_item)) {
                http_response_code(500);
                echo json_encode(array("message" => "Error creating invoice item: " . $conn->error));
                exit;
            }
        }

        http_response_code(201);
        $response = array(
            "message" => "Invoice created successfully",
            "invoice_id" => $invoiceId,
            "data" => array(
                "invoice_number" => $invoiceNumber,
                "status" => $status,
                "total_amount" => $data['total_amount'],
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

// Function to generate unique invoice number
function generateInvoiceNumber($conn) {
    $prefix = 'INV-' . date('Ym');
    $lastInvoice = $conn->query("SELECT MAX(invoice_number) as last FROM invoices WHERE invoice_number LIKE '$prefix%'");
    $lastInvoice = $lastInvoice->fetch_assoc();
    
    if ($lastInvoice['last']) {
        $lastNumber = substr($lastInvoice['last'], -4);
        $newNumber = $lastNumber + 1;
    } else {
        $newNumber = 1;
    }
    
    // Ensure uniqueness by checking if the generated number already exists
    while ($conn->query("SELECT EXISTS(SELECT 1 FROM invoices WHERE invoice_number = '$prefix" . sprintf('%04d', $newNumber) . "')")->fetch_row()[0]) {
        $newNumber++;
    }
    
    return $prefix . sprintf('%04d', $newNumber);
}
?>

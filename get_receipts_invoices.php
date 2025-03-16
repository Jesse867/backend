<?php
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 1);
require "db.php";

// Get all receipts
$receipts = [];
$receiptQuery = "SELECT * FROM receipts";
$resultReceipts = $conn->query($receiptQuery);
if ($resultReceipts) {
    while ($row = $resultReceipts->fetch_assoc()) {
        $receipts[] = $row;
    }
} else {
    echo json_encode([
        "success" => false,
        "message" => "Error retrieving receipts: " . $conn->error
    ]);
    exit;
}

// Get all invoices
$invoices = [];
$invoiceQuery = "SELECT * FROM invoices";
$resultInvoices = $conn->query($invoiceQuery);
if ($resultInvoices) {
    while ($row = $resultInvoices->fetch_assoc()) {
        $invoices[] = $row;
    }
} else {
    echo json_encode([
        "success" => false,
        "message" => "Error retrieving invoices: " . $conn->error
    ]);
    exit;
}

// Return combined JSON response
echo json_encode([
    "success"  => true,
    "receipts" => $receipts,
    "invoices" => $invoices
]);

$conn->close();
?>

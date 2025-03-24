<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

require "db.php"; // Ensure this file contains your database connection

try {
    // SQL query to calculate total revenue from paid receipts
    $query = "SELECT SUM(total_amount) AS total_revenue FROM receipts WHERE status = 'paid'";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $totalRevenue = number_format($row['total_revenue'], 2, '.', ''); // Format to 2 decimal places
        
        echo json_encode([
            "success" => true,
            "total_revenue" => $totalRevenue
        ]);
    } else {
        throw new Exception("No revenue data found");
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error calculating total revenue: " . $e->getMessage()
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
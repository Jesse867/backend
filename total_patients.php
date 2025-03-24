<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

require "db.php"; // Make sure this file contains your database connection

try {
    // SQL query to count total patients
    $query = "SELECT COUNT(*) AS total FROM patients";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode([
            "success" => true,
            "total_patients" => $row['total']
        ]);
    } else {
        throw new Exception("No patients found in the database");
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "message" => "Error counting patients: " . $e->getMessage()
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
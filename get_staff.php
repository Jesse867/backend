<?php
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 1);
require "db.php";

// Query to get all staff members (assuming 'role' column differentiates staff from patients)
$query = "SELECT * FROM users WHERE role != 'patient'";

$result = $conn->query($query);

if ($result->num_rows > 0) {
    $staff = [];
    while ($row = $result->fetch_assoc()) {
        $staff[] = $row;
    }
    echo json_encode(["success" => true, "staff" => $staff]);
} else {
    echo json_encode(["success" => false, "message" => "No staff members found"]);
}

$conn->close();
?>
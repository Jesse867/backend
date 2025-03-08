<?php
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 1);
require "db.php";

// Query to get all doctors (assuming 'role' column differentiates doctors)
$query = "SELECT * FROM users WHERE role = 'doctor'";

$result = $conn->query($query);

if ($result->num_rows > 0) {
    $doctors = [];
    while ($row = $result->fetch_assoc()) {
        $doctors[] = $row;
    }
    echo json_encode(["success" => true, "doctors" => $doctors]);
} else {
    echo json_encode(["success" => false, "message" => "No doctors found"]);
}

$conn->close();
?>
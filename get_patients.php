<?php
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 1);
require "db.php";

// Query to get all patients
$query = "SELECT * FROM patients";

$result = $conn->query($query);

if ($result->num_rows > 0) {
    $patients = [];
    while ($row = $result->fetch_assoc()) {
        $patients[] = $row;
    }
    echo json_encode(["success" => true, "patients" => $patients]);
} else {
    echo json_encode(["success" => false, "message" => "No patients found"]);
}

$conn->close();
?>
use this 

<?php
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 1);
require "db.php";

// Query to get all appointments along with patient details
$query = "
    SELECT 
        a.appointment_id, 
        a.appointment_datetime, 
        a.reason_for_visit, 
        a.contact_number AS appointment_contact, 
        p.patient_id, 
        CONCAT(p.first_name, ' ', p.last_name) AS patient_name, 
        p.primary_phone_number AS patient_contact
    FROM 
        appointments a
    JOIN 
        patients p ON a.patient_id = p.patient_id
";

$result = $conn->query($query);

if (!$result) {
    echo json_encode(["success" => false, "message" => "Query Error: " . $conn->error]);
    exit;
}

if ($result->num_rows > 0) {
    $appointments = [];
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
    echo json_encode(["success" => true, "appointments" => $appointments]);
} else {
    echo json_encode(["success" => false, "message" => "No appointments found"]);
}

$conn->close();
?>
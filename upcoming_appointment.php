<?php
    header("Content-Type: application/json");
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    require "db.php";
    
    // Get the patient ID from the query parameters
    $patientId = isset($_GET['patient_id']) ? intval($_GET['patient_id']) : null;
    
    if (!$patientId) {
        echo json_encode(["success" => false, "message" => "Patient ID is required"]);
        exit;
    }
    
    // SQL query to get upcoming confirmed appointments for the specific patient
    $query = "
        SELECT 
            a.appointment_datetime, 
            CONCAT(d.first_name, ' ', d.last_name) AS doctor_name,
            a.appointment_id
        FROM 
            appointments a
        JOIN 
            doctors d ON a.doctor_id = d.doctor_id
        WHERE 
            a.patient_id = ?
            AND a.status = 'confirmed'
            AND a.appointment_datetime > NOW()
        ORDER BY
            a.appointment_datetime ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $patientId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $appointments = [];
        while ($row = $result->fetch_assoc()) {
            $appointments[] = [
                "appointment_id" => $row['appointment_id'],
                "appointment_datetime" => $row['appointment_datetime'],
                "doctor_name" => $row['doctor_name']
            ];
        }
        echo json_encode(["success" => true, "appointments" => $appointments]);
    } else {
        echo json_encode(["success" => false, "message" => "No upcoming confirmed appointments found for this patient"]);
    }
    
    $stmt->close();
    $conn->close();
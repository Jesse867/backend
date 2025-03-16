
<?php
    header("Content-Type: application/json");
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: GET, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    require "db.php";
    
    // Query to get all medical records, patient details, and medications
    $query = "SELECT 
        mr.*,
        p.first_name,
        p.last_name,
        p.date_of_birth,
        p.gender,
        p.primary_phone_number,
        p.hospital_number,
        m.medication_id,
        m.name AS medication_name,
        m.dosage,
        m.frequency,
        m.created_at
    FROM medical_records mr
    JOIN patients p ON mr.patient_id = p.patient_id
    LEFT JOIN medications m ON mr.record_id = m.record_id
    ORDER BY mr.record_id, m.created_at DESC";
    
    $stmt = $conn->prepare($query);
    if (!$stmt) {
        echo json_encode(["message" => "Prepare failed: " . $conn->error]);
        exit;
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $medicalRecords = [];
    
        while ($row = $result->fetch_assoc()) {
            $medicalRecord = [
                "medicalRecord" => [
                    "record_id" => $row['record_id'],
                    "patient_id" => $row['patient_id'],
                    "visit_date" => $row['visit_date'],
                    "doctor" => $row['doctor'],
                    "field" => $row['field'],
                    "temperature" => $row['temperature'],
                    "weight" => $row['weight'],
                    "heart_rate" => (int)$row['heart_rate'],
                    "blood_pressure" => $row['blood_pressure'],
                    "symptoms" => $row['symptoms'],
                    "allergies" => $row['allergies'],
                    "diagnosis" => $row['diagnosis'],
                    "lab_tests" => $row['lab_tests'],
                    "lab_test_results" => $row['lab_test_results'],
                    "doctor_notes" => $row['doctor_notes'],
                    "first_name" => $row['first_name'],
                    "last_name" => $row['last_name'],
                    "date_of_birth" => $row['date_of_birth'],
                    "gender" => $row['gender'],
                    "primary_phone_number" => $row['primary_phone_number'],
                    "hospital_number" => (int)$row['hospital_number']
                ],
                "patientInfo" => [
                    "name" => $row['first_name'] . " " . $row['last_name'],
                    "dateOfBirth" => $row['date_of_birth'],
                    "gender" => $row['gender'],
                    "hospitalNumber" => (int)$row['hospital_number'],
                    "phoneNumber" => $row['primary_phone_number']
                ],
                "medications" => []
            ];
    
            if (!empty($row['medication_id'])) {
                $medicalRecord['medications'][] = [
                    "medicationId" => $row['medication_id'],
                    "name" => $row['medication_name'],
                    "dosage" => $row['dosage'],
                    "frequency" => $row['frequency']
                ];
            }
    
            $medicalRecords[] = $medicalRecord;
        }
    
        echo json_encode(["success" => true, "medicalRecords" => $medicalRecords]);
    } else {
        echo json_encode(["success" => false, "message" => "No medical records found"]);
    }
    
    $stmt->close();
    $conn->close();
    ?>

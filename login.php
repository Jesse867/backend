<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

require "db.php";

try {
    // Handle CORS preflight request
    if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
        http_response_code(200);
        exit;
    }

    // Read JSON input
    $json = file_get_contents("php://input");
    $data = json_decode($json, true);

    if (!$data || !isset($data['hospital_number']) || !isset($data['password'])) {
        echo json_encode([
            "message" => "Missing hospital number or password",
            "received" => $data,
            "success" => false
        ]);
        exit;
    }

    $hospital_number = trim($data['hospital_number']);
    $password = trim($data['password']);

    // Debug: Print received data
    error_log("Login Request Data: " . print_r($data, true));

    // Fetch user and patient details from DB
    $stmt = $conn->prepare("SELECT 
            u.user_id, u.password_hash, u.role, u.email, u.hospital_number,
            p.patient_id, p.first_name, p.middle_name, p.last_name,
            p.date_of_birth, p.gender, p.photo_upload,
            p.primary_phone_number, p.alternate_phone_number,
            p.street,
            p.city, p.state,
            p.country,
            p.emergency_contact_name, p.emergency_contact_relationship,
            p.emergency_contact_phone,
            p.blood_group, p.known_allergies, p.pre_existing_conditions,
            p.primary_physician, p.insurance_number, p.insurance_provider,
            p.marital_status, p.occupation, p.consent_for_data_usage
        FROM users u
        LEFT JOIN patients p ON u.hospital_number = p.hospital_number
        WHERE u.hospital_number = ?"
    );

    $stmt->bind_param("s", $hospital_number);
    $stmt->execute();

    $result = $stmt->get_result();

    // Debug: Check if query returned any results
    error_log("Query Result Count: " . $result->num_rows);

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();

        // Debug: Print the fetched row
        error_log("Fetched User Data: " . print_r($row, true));

        // Verify password
        if (password_verify($password, $row['password_hash'])) {
            $response = [
                "message" => "Login successful",
                "success" => true,
                "role" => $row['role'],
                "email" => $row['email'],
                "hospitalNumber" => $row["hospital_number"],
                "patientId" => $row["patient_id"],
                "patient" => [
                    "firstName" => $row['first_name'],
                    "middleName" => $row['middle_name'],
                    "lastName" => $row['last_name'],
                    "dateOfBirth" => $row['date_of_birth'],
                    "gender" => $row['gender'],
                    "photoUpload" => $row['photo_upload'],
                    "primaryPhoneNumber" => $row['primary_phone_number'],
                    "alternatePhoneNumber" => $row['alternate_phone_number'],
                    "residentialAddress" => [
                        "street" => $row['street'],
                        "city" => $row['city'],
                        "state" => $row['state'],
                        "country" => $row['country']
                    ],
                    "emergencyContact" => [
                        "name" => $row['emergency_contact_name'],
                        "relationship" => $row['emergency_contact_relationship'],
                        "phoneNumber" => $row['emergency_contact_phone']
                    ],
                    "bloodGroup" => $row['blood_group'],
                    "knownAllergies" => $row['known_allergies'],
                    "preExistingConditions" => $row['pre_existing_conditions'],
                    "primaryPhysician" => $row['primary_physician'],
                    "insuranceNumber" => $row['insurance_number'],
                    "provider" => $row['insurance_provider'],
                    "maritalStatus" => $row['marital_status'],
                    "occupation" => $row['occupation'],
                    "consentForDataUsage" => (bool)$row['consent_for_data_usage']
                ]
            ];

            echo json_encode($response);
        } else {
            echo json_encode([
                "message" => "Incorrect password",
                "success" => false
            ]);
        }
    } else {
        echo json_encode([
            "message" => "User not found",
            "success" => false
        ]);
    }

    $stmt->close();
} catch (Exception $e) {
    echo json_encode([
        "message" => "An error occurred: " . $e->getMessage(),
        "success" => false
    ]);
}
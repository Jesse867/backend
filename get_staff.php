<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
error_reporting(E_ALL);
ini_set('display_errors', 1);
require "db.php";

// Query to get all staff members with their specific details
$query = "SELECT 
    u.user_id,
    u.email,
    u.role,
    CASE 
        WHEN u.role = 'doctor' THEN d.first_name
        WHEN u.role = 'pharmacist' THEN p.first_name
        WHEN u.role = 'receptionist' THEN r.first_name
        WHEN u.role = 'billing_officer' THEN bo.first_name
        ELSE a.first_name
    END AS firstName,
    CASE 
        WHEN u.role = 'doctor' THEN d.last_name
        WHEN u.role = 'pharmacist' THEN p.last_name
        WHEN u.role = 'receptionist' THEN r.last_name
        WHEN u.role = 'billing_officer' THEN bo.last_name
        ELSE a.last_name
    END AS lastName,
    CASE 
        WHEN u.role = 'doctor' THEN d.phone_number
        WHEN u.role = 'pharmacist' THEN p.phone_number
        WHEN u.role = 'receptionist' THEN r.phone_number
        WHEN u.role = 'billing_officer' THEN bo.phone_number
        ELSE a.phone_number
    END AS phoneNumber,
    CASE 
        WHEN u.role = 'doctor' THEN d.license_number
        WHEN u.role = 'pharmacist' THEN p.license_number
        ELSE NULL
    END AS licenseNumber,
    CASE 
        WHEN u.role = 'doctor' THEN d.profile_picture
        ELSE NULL
    END AS profilePicture,
    CASE 
        WHEN u.role = 'doctor' THEN d.specialization
        ELSE NULL
    END AS specialization,
    CASE 
        WHEN u.role = 'doctor' THEN d.years_of_experience
        ELSE NULL
    END AS yearsExperience,
    CASE 
        WHEN u.role = 'doctor' THEN d.about
        ELSE NULL
    END AS about
FROM users u
LEFT JOIN (
    SELECT user_id, first_name, last_name, phone_number, license_number, profile_picture, specialization, years_of_experience, about 
    FROM doctors
) d ON u.user_id = d.user_id
LEFT JOIN (
    SELECT user_id, first_name, last_name, phone_number, license_number 
    FROM pharmacists
) p ON u.user_id = p.user_id
LEFT JOIN (
    SELECT user_id, first_name, last_name, phone_number 
    FROM admins
) a ON u.user_id = a.user_id
LEFT JOIN (
    SELECT user_id, first_name, last_name, phone_number 
    FROM receptionists
) r ON u.user_id = r.user_id
LEFT JOIN (
    SELECT user_id, first_name, last_name, phone_number 
    FROM billing_officers
) bo ON u.user_id = bo.user_id
WHERE u.role IN ('admin', 'receptionist', 'billing_officer', 'pharmacist', 'doctor')";

$result = $conn->query($query);

if ($result->num_rows > 0) {
    $staff = [];
    while ($row = $result->fetch_assoc()) {
        // Conditionally unset fields based on role
        if ($row['role'] === 'doctor') {
            // Keep all fields for doctors
        } elseif ($row['role'] === 'pharmacist') {
            // Only keep licenseNumber for pharmacists
            unset($row['profilePicture']);
            unset($row['specialization']);
            unset($row['yearsExperience']);
            unset($row['about']);
        } else {
            // For other roles, only keep generic information
            unset($row['licenseNumber']);
            unset($row['profilePicture']);
            unset($row['specialization']);
            unset($row['yearsExperience']);
            unset($row['about']);
        }

        $staff[] = $row;
    }
    echo json_encode([
        "success" => true,
        "staff" => $staff
    ]);
} else {
    echo json_encode([
        "success" => false,
        "message" => "No staff members found"
    ]);
}

$conn->close();
?>
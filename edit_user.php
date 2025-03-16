<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
error_reporting(E_ALL);
ini_set('display_errors', 1);
require "db.php";

// Initialize response array
$response = [
    "success" => false,
    "message" => "Unknown error occurred"
];

try {
    // Check request method
    $requestMethod = $_SERVER["REQUEST_METHOD"];

    // Handle preflight OPTIONS request
    if ($requestMethod === "OPTIONS") {
        http_response_code(200);
        echo json_encode(["success" => true]);
        exit;
    }

    // Ensure the endpoint only accepts POST or PUT requests
    if ($requestMethod !== "POST" && $requestMethod !== "PUT") {
        throw new Exception("Method not allowed. This endpoint only accepts POST or PUT requests.");
    }

    // Read JSON input
    $json = file_get_contents("php://input");
    $data = json_decode($json, true);
    if (!$data) {
        throw new Exception("Invalid JSON input");
    }

    // Required fields for our API
    $requiredFields = ['editorRole', 'editorId', 'targetUserId', 'targetRole', 'fields'];
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            throw new Exception("Missing or empty field: $field");
        }
    }

    $editorRole   = trim($data['editorRole']);
    $editorId     = trim($data['editorId']);
    $targetUserId = trim($data['targetUserId']);
    $targetRole   = trim($data['targetRole']);
    $fieldsToUpdate = $data['fields'];

    // --- Permission Checks ---
    if ($targetRole !== "patient") {
        if ($editorRole !== "admin") {
            throw new Exception("Only admins can edit details for non-patient roles");
        }
    } else {
        if ($editorRole === "patient") {
            if ($editorId !== $targetUserId) {
                throw new Exception("Patients can only edit their own details");
            }
            $allowedPatientFields = [
                "firstName",
                "middleName",
                "lastName",
                "dateOfBirth",
                "gender",
                "occupation",
                "photoUpload",
                "primaryPhoneNumber",
                "alternatePhoneNumber",
                "street",
                "city",
                "state",
                "country",
                "emergencyContactName",
                "emergencyContactRelationship",
                "emergencyContactPhone",
                "password"
            ];

            foreach ($fieldsToUpdate as $key => $value) {
                if ($key !== "email" && !in_array($key, $allowedPatientFields)) {
                    unset($fieldsToUpdate[$key]);
                }
            }
        } elseif ($editorRole === "receptionist") {
            // No additional filtering needed
        } else {
            throw new Exception("You are not authorized to edit patient details");
        }
    }

    // --- Separate Fields for Users vs. Role-Specific Table ---
    $flattenedFields = [];
    foreach ($fieldsToUpdate as $key => $value) {
        if (is_array($value)) {
            foreach ($value as $subKey => $subValue) {
                $flattenedFields[$subKey] = $subValue;
            }
        } else {
            $flattenedFields[$key] = $value;
        }
    }
    $fieldsToUpdate = $flattenedFields;

    $userFields = [];
    $roleFields = [];

    foreach ($fieldsToUpdate as $field => $value) {
        if ($field === "email" || $field === "password") {
            $userFields[$field] = $value;
        } else {
            $roleFields[$field] = $value;
        }
    }

    // --- Update the Users Table ---
    if (!empty($userFields)) {
        $setParts = [];
        $bindTypes = "";
        $bindValues = [];
        foreach ($userFields as $field => $value) {
            if ($field === "email") {
                $setParts[] = "email = ?";
                $bindTypes .= "s";
                $bindValues[] = $value;
            } elseif ($field === "password") {
                $hashed = password_hash($value, PASSWORD_BCRYPT);
                $setParts[] = "password_hash = ?";
                $bindTypes .= "s";
                $bindValues[] = $hashed;
            }
        }
        $setClause = implode(", ", $setParts);
        $queryUsers = "UPDATE users SET $setClause WHERE user_id = ?";
        $bindTypes .= "i";
        $bindValues[] = $targetUserId;
        $stmt = $conn->prepare($queryUsers);
        if (!$stmt) {
            throw new Exception("Users table prepare failed: " . $conn->error);
        }
        $stmt->bind_param($bindTypes, ...$bindValues);
        if (!$stmt->execute()) {
            throw new Exception("Error updating users table: " . $stmt->error);
        }
        $stmt->close();
    }

    // --- Determine Target Role-Specific Table ---
    $tableName = "";
    switch ($targetRole) {
        case "patient":
            $tableName = "patients";
            break;
        case "doctor":
            $tableName = "doctors";
            break;
        case "pharmacist":
            $tableName = "pharmacists";
            break;
        case "billing_officer":
            $tableName = "billing_officers";
            break;
        case "receptionist":
            $tableName = "receptionists";
            break;
        case "admin":
            $tableName = "admins";
            break;
        default:
            throw new Exception("Invalid target role");
    }

    // --- Update the Role-Specific Table ---
    if (!empty($roleFields)) {
        $setParts = [];
        $bindTypes = "";
        $bindValues = [];
        foreach ($roleFields as $field => $value) {
            // Handle specific field mappings
            if ($field === "yearsExperience") {
                $column = "years_of_experience";
            } else {
                // For non-patients, "contactNumber" should be mapped to "phone_number"
                if ($field === "contactNumber" && $targetRole !== "patient") {
                    $column = "phone_number";
                }
                // For patients, map "primaryPhoneNumber" and "alternatePhoneNumber"
                elseif ($targetRole === "patient") {
                    if ($field === "primaryPhoneNumber") {
                        $column = "primary_phone_number";
                    } elseif ($field === "alternatePhoneNumber") {
                        $column = "alternate_phone_number";
                    } else {
                        // Convert camelCase to snake_case
                        $column = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $field));
                    }
                } else {
                    // For other roles, convert camelCase to snake_case
                    $column = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $field));
                }
            }
            $setParts[] = "$column = ?";
            $bindTypes .= "s"; // assuming all are strings; adjust if needed
            $bindValues[] = $value;
        }
        $setClause = implode(", ", $setParts);
        $queryRole = "UPDATE $tableName SET $setClause WHERE user_id = ?";
        $bindTypes .= "i";
        $bindValues[] = $targetUserId;
        $stmt = $conn->prepare($queryRole);
        if (!$stmt) {
            throw new Exception("Role table prepare failed: " . $conn->error);
        }
        $stmt->bind_param($bindTypes, ...$bindValues);
        if (!$stmt->execute()) {
            throw new Exception("Error updating $tableName table: " . $stmt->error);
        }
        $stmt->close();
    }


    $response = [
        "success" => true,
        "message" => "User details updated successfully"
    ];
} catch (Exception $e) {
    $response["message"] = $e->getMessage();
}

// Close database connection
$conn->close();

// Output JSON response
echo json_encode($response);

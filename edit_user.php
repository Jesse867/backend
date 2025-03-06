<?php
header("Content-Type: application/json");
error_reporting(E_ALL);
ini_set('display_errors', 1);
require "db.php";

// Read JSON input
$json = file_get_contents("php://input");
$data = json_decode($json, true);
if (!$data) {
    echo json_encode(["message" => "Invalid JSON input"]);
    exit;
}

// Required fields for our API
$requiredFields = ['editorRole', 'editorId', 'targetUserId', 'targetRole', 'fields'];
foreach ($requiredFields as $field) {
    if (!isset($data[$field]) || empty($data[$field])) {
        echo json_encode(["message" => "Missing or empty field: $field"]);
        exit;
    }
}

$editorRole   = trim($data['editorRole']);    // e.g., "admin", "patient", "receptionist"
$editorId     = trim($data['editorId']);        // editor's user id
$targetUserId = trim($data['targetUserId']);      // user id to update
$targetRole   = trim($data['targetRole']);        // role of the user being updated
$fieldsToUpdate = $data['fields'];                // associative array of field => value

// --- Permission Checks ---
if ($targetRole !== "patient") {
    // For non-patient records, only admins can edit details.
    if ($editorRole !== "admin") {
        echo json_encode(["message" => "Only admins can edit details for non-patient roles"]);
        exit;
    }
} else {
    // For patient records:
    if ($editorRole === "patient") {
        // Patients can only update their own details.
        if ($editorId !== $targetUserId) {
            echo json_encode(["message" => "Patients can only edit their own details"]);
            exit;
        }
        // Allowed fields for patient self-update (note: email is stored in users table)
        $allowedPatientFields = [
            "firstName", "middleName", "lastName", "dateOfBirth", "gender", "occupation", 
            "photoUpload", "primaryPhoneNumber", "alternatePhoneNumber", "street", "city", "state", "country",
            "emergencyContactName", "emergencyContactRelationship", "emergencyContactPhone", 
            "password"
        ];
        
        // Remove any field not allowed (except "email" which we'll always update in users)
        foreach ($fieldsToUpdate as $key => $value) {
            if ($key !== "email" && !in_array($key, $allowedPatientFields)) {
                unset($fieldsToUpdate[$key]);
            }
        }
    } elseif ($editorRole === "receptionist") {
        // Receptionists can edit all patient details.
        // No filtering is required here, but you might want to enforce specific rules.
    } else {
        echo json_encode(["message" => "You are not authorized to edit patient details"]);
        exit;
    }
}

// --- Separate Fields for Users vs. Role-Specific Table ---
// In our design, the email and password are stored in the central users table.
// All other fields are stored in the role-specific table.
// We'll first flatten any nested objects. For example, if "residentialAddress" is an object,
// we'll extract its keys (e.g., street, city, state, country) into the flat $fieldsToUpdate array.
$flattenedFields = [];
foreach ($fieldsToUpdate as $key => $value) {
    if (is_array($value)) {
        // For nested objects, merge their keys into our flattenedFields.
        // You can also choose to prepend the parent key if needed.
        foreach ($value as $subKey => $subValue) {
            // Here we assume that the column names in the database for address are "street", "city", etc.
            $flattenedFields[$subKey] = $subValue;
        }
    } else {
        $flattenedFields[$key] = $value;
    }
}
$fieldsToUpdate = $flattenedFields;

// Now, separate the fields intended for the users table (email and password) 
// from the fields intended for the role-specific table.
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
            // Hash the password before updating
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
        echo json_encode(["message" => "Users table prepare failed: " . $conn->error]);
        exit;
    }
    $stmt->bind_param($bindTypes, ...$bindValues);
    if (!$stmt->execute()) {
        echo json_encode(["message" => "Error updating users table: " . $stmt->error]);
        exit;
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
        echo json_encode(["message" => "Invalid target role"]);
        exit;
}

// --- Update the Role-Specific Table ---
if (!empty($roleFields)) {
    $setParts = [];
    $bindTypes = "";
    $bindValues = [];
    foreach ($roleFields as $field => $value) {
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
        echo json_encode(["message" => "Role table prepare failed: " . $conn->error]);
        exit;
    }
    $stmt->bind_param($bindTypes, ...$bindValues);
    if (!$stmt->execute()) {
        echo json_encode(["message" => "Error updating $tableName table: " . $stmt->error]);
        exit;
    }
    $stmt->close();
}

echo json_encode(["success" => true, "message" => "User details updated successfully"]);
$conn->close();
?>
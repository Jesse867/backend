<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
error_reporting(E_ALL);
ini_set('display_errors', 1);
require "db.php";

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Method not allowed");
    }

    if (!isset($_FILES['attachments'])) {
        throw new Exception("No files uploaded");
    }

    // Check if it's a single file or multiple files
    if (!is_array($_FILES['attachments']['name'])) {
        // Handle single file upload
        $files = array(
            'name' => array($_FILES['attachments']['name']),
            'type' => array($_FILES['attachments']['type']),
            'tmp_name' => array($_FILES['attachments']['tmp_name']),
            'error' => array($_FILES['attachments']['error']),
            'size' => array($_FILES['attachments']['size'])
        );
    } else {
        $files = $_FILES['attachments'];
    }

    $complaintId = isset($_POST['complaintId']) ? intval($_POST['complaintId']) : null;
    if (!$complaintId) {
        throw new Exception("Complaint ID is required");
    }

    $uploadDir = "uploads/complaints/$complaintId/";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    $successfulUploads = 0;
    $failedUploads = 0;
    $attachmentsData = array();

    foreach ($files['name'] as $index => $fileName) {
        $fileTmpName = $files['tmp_name'][$index];
        $fileType = $files['type'][$index];
        $fileSize = $files['size'][$index];

        // Generate unique filename to avoid conflicts
        $uniqueFileName = uniqid() . '_' . $fileName;

        // Validate file type
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf', 'text/plain'];
        if (!in_array($fileType, $allowedTypes)) {
            $failedUploads++;
            continue;
        }

        // Validate file extension (additional security)
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf', 'txt'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        if (!in_array($fileExtension, $allowedExtensions)) {
            $failedUploads++;
            continue;
        }

        // Validate file size (max 5MB)
        $maxFileSize = 20 * 1024 * 1024;
        if ($fileSize > $maxFileSize) {
            $failedUploads++;
            continue;
        }

        // Move the uploaded file
        $filePath = $uploadDir . $uniqueFileName;
        if (move_uploaded_file($fileTmpName, $filePath)) {
            // Insert into complaint_attachments
            $attachmentStmt = $conn->prepare("
                INSERT INTO complaint_attachments (
                    complaint_id, 
                    name, 
                    type,
                    size
                ) VALUES (?, ?, ?, ?)
            ");
            $attachmentStmt->bind_param("isss", $complaintId, $uniqueFileName, $fileType, $fileSize);

            if ($attachmentStmt->execute()) {
                $successfulUploads++;
                // Collect attachment details
                $attachmentId = $attachmentStmt->insert_id;
                $attachmentsData[] = array(
                    'attachment_id' => $attachmentId,
                    'name' => $uniqueFileName,
                    'type' => $fileType,
                    'size' => $fileSize
                );
            } else {
                $failedUploads++;
                error_log("Failed to save attachment: " . $attachmentStmt->error);
            }
            $attachmentStmt->close();
        } else {
            $failedUploads++;
            error_log("Failed to move uploaded file");
        }
    }

    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "File upload completed",
        "statistics" => [
            "successful" => $successfulUploads,
            "failed" => $failedUploads
        ],
        "attachments" => $attachmentsData
    ]);
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
$conn->close();
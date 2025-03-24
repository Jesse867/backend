<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

require "db.php"; // Ensure this file contains your database connection

try {
    // Check if the request method is POST
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception("Only POST requests are allowed");
    }

    // Check if file was uploaded
    if (!isset($_FILES['photoUpload']) || $_FILES['photoUpload']['error'] === UPLOAD_ERR_NO_FILE) {
        throw new Exception("No file uploaded");
    }

    // Get the uploaded file
    $file = $_FILES['photoUpload'];
    $fileName = $file['name'];
    $fileTmpPath = $file['tmp_name'];
    $fileSize = $file['size'];
    $fileType = $file['type'];

    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
    if (!in_array($fileType, $allowedTypes)) {
        throw new Exception("Invalid file type. Only JPG, PNG, and GIF are allowed");
    }

    // Validate file size (max 2MB)
    $maxFileSize = 2 * 1024 * 1024; // 2MB
    if ($fileSize > $maxFileSize) {
        throw new Exception("File size exceeds maximum allowed size of 2MB");
    }

    // Sanitize the file name
    $fileName = uniqid() . '_' . sanitizeFileName($fileName);

    // Upload directory
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Save the file
    $filePath = $uploadDir . $fileName;
    if (!move_uploaded_file($fileTmpPath, $filePath)) {
        throw new Exception("Failed to upload file");
    }

    // Generate the file URL
    $fileUrl = "http://" . $_SERVER['HTTP_HOST'] . str_replace($_SERVER['DOCUMENT_ROOT'], '', $filePath);

    echo json_encode([
        "success" => true,
        "message" => "File uploaded successfully",
        "fileUrl" => $fileUrl
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        "success" => false,
        "error" => $e->getMessage()
    ]);
}

// Helper function to sanitize file names
function sanitizeFileName($name) {
    return preg_replace('/[^a-zA-Z0-9._-]/', '', $name);
}
?>
<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Error reporting for debugging (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check if the request is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        "success" => false,
        "error" => "Only POST requests are allowed"
    ]);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['photoUpload']) || $_FILES['photoUpload']['error'] !== UPLOAD_ERR_OK) {
    $errorMessage = "No file uploaded or upload error occurred";
    if (isset($_FILES['photoUpload'])) {
        switch ($_FILES['photoUpload']['error']) {
            case UPLOAD_ERR_INI_SIZE:
                $errorMessage = "The uploaded file exceeds the upload_max_filesize directive in php.ini";
                break;
            case UPLOAD_ERR_FORM_SIZE:
                $errorMessage = "The uploaded file exceeds the MAX_FILE_SIZE directive in the HTML form";
                break;
            case UPLOAD_ERR_PARTIAL:
                $errorMessage = "The uploaded file was only partially uploaded";
                break;
            case UPLOAD_ERR_NO_FILE:
                $errorMessage = "No file was uploaded";
                break;
            case UPLOAD_ERR_NO_TMP_DIR:
                $errorMessage = "Missing a temporary folder";
                break;
            case UPLOAD_ERR_CANT_WRITE:
                $errorMessage = "Failed to write file to disk";
                break;
            case UPLOAD_ERR_EXTENSION:
                $errorMessage = "A PHP extension stopped the file upload";
                break;
        }
    }
    
    echo json_encode([
        "success" => false,
        "error" => $errorMessage
    ]);
    exit;
}

try {
    // Define upload directory
    $targetDir = "assets/images/";
    
    // Create directory if it doesn't exist
    if (!file_exists($targetDir)) {
        mkdir($targetDir, 0777, true);
    }
    
    // Get file information
    $file = $_FILES['photoUpload'];
    $fileName = basename($file['name']);
    $fileType = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    
    // Validate file type
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
    if (!in_array($fileType, $allowedTypes)) {
        echo json_encode([
            "success" => false,
            "error" => "Invalid file format. Only JPG, JPEG, PNG, and GIF are allowed."
        ]);
        exit;
    }
    
    // Validate file size (5MB max)
    $maxSize = 5 * 1024 * 1024; // 5MB in bytes
    if ($file['size'] > $maxSize) {
        echo json_encode([
            "success" => false,
            "error" => "File size exceeds the 5MB limit."
        ]);
        exit;
    }
    
    // Generate unique filename to avoid conflicts
    $uniqueFileName = uniqid() . "_" . $fileName;
    $targetPath = $targetDir . $uniqueFileName;
    
    // Move uploaded file to target directory
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Generate file URL
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];
        $baseUrl = $protocol . '://' . $host . '/hospital_api/';
        $fileUrl = $baseUrl . $targetPath;
        
        echo json_encode([
            "success" => true,
            "message" => "File uploaded successfully",
            "fileName" => $uniqueFileName,
            "fileUrl" => $fileUrl
        ]);
    } else {
        echo json_encode([
            "success" => false,
            "error" => "Failed to move uploaded file to target directory"
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        "success" => false,
        "error" => "An error occurred: " . $e->getMessage()
    ]);
}
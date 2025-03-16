<?php
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

    $complaintId = isset($_POST['complaintId']) ? intval($_POST['complaintId']) : null;
    $attachmentId = isset($_POST['attachmentId']) ? intval($_POST['attachmentId']) : null;

    if (!$complaintId) {
        throw new Exception("Complaint ID is required");
    }

    // Check if single file download or multiple files
    if ($attachmentId) {
        $stmt = $conn->prepare("SELECT * FROM complaint_attachments WHERE complaint_id = ? AND attachment_id = ?");
        $stmt->bind_param("ii", $complaintId, $attachmentId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("Attachment not found");
        }

        $attachment = $result->fetch_assoc();
        $filePath = "uploads/complaints/$complaintId/" . $attachment['name'];

        if (!file_exists($filePath)) {
            throw new Exception("File does not exist");
        }

        // Set headers for file download
        $mimeType = mime_content_type($filePath);
        header("Content-Description: File Transfer");
        header("Content-Type: $mimeType");
        header("Content-Disposition: attachment; filename*=UTF-8''" . urlencode(basename($filePath)));
        header("Content-Length: " . filesize($filePath));
        header("Content-Transfer-Encoding: binary");
        header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
        header("Pragma: public");

        // Flush any existing output buffer
        ob_clean();
        flush();

        // Read file in binary mode
        readfile($filePath, false);
        exit;
    } else {
        // Multiple files download
        $stmt = $conn->prepare("SELECT * FROM complaint_attachments WHERE complaint_id = ?");
        $stmt->bind_param("i", $complaintId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception("No attachments found");
        }

        $attachments = $result->fetch_all(MYSQLI_ASSOC);

        if (count($attachments) === 1) {
            // If only one file, redirect to single file download
            $attachment = $attachments[0];
            $filePath = "uploads/complaints/$complaintId/" . $attachment['name'];
            if (!file_exists($filePath)) {
                throw new Exception("File does not exist");
            }

            $mimeType = mime_content_type($filePath);
            header("Content-Description: File Transfer");
            header("Content-Type: $mimeType");
            header("Content-Disposition: attachment; filename*=UTF-8''" . urlencode(basename($filePath)));
            header("Content-Length: " . filesize($filePath));
            header("Content-Transfer-Encoding: binary");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Pragma: public");

            // Flush any existing output buffer
            ob_clean();
            flush();

            // Read file in binary mode
            readfile($filePath, false);
            exit;
        } else {
            // Create zip archive for multiple files
            $zip = new ZipArchive();
            $zipFileName = "complaint_attachments_" . date('Y-m-d_H-i-s') . ".zip";

            if ($zip->open($zipFileName, ZipArchive::CREATE) !== TRUE) {
                throw new Exception("Cannot create zip file");
            }

            foreach ($attachments as $attachment) {
                $file = "uploads/complaints/$complaintId/" . $attachment['name'];
                if (file_exists($file)) {
                    $zip->addFile($file, $attachment['name']);
                }
            }

            $zip->close();

            // Send zip file to client
            header("Content-Type: application/zip");
            header("Content-Disposition: attachment; filename*=UTF-8''" . urlencode(basename($zipFileName)));
            header("Content-Length: " . filesize($zipFileName));
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Pragma: public");

            // Flush any existing output buffer
            ob_clean();
            flush();

            // Read file in binary mode
            readfile($zipFileName, false);
            unlink($zipFileName); // Delete the zip file after sending
            exit;
        }
    }
} catch (Exception $e) {
    // Clear any existing headers
    header_remove();
    
    error_log("Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
$conn->close();
?>
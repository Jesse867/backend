<?php
    header("Content-Type: application/json");
    header("Access-Control-Allow-Origin: *");
    header("Access-Control-Allow-Methods: POST, OPTIONS");
    header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    
    require "db.php";
    
    // Include PHPMailer classes
    require "PHPMailer/src/PHPMailer.php";
    require "PHPMailer/src/SMTP.php";
    require "PHPMailer/src/Exception.php";
    
    // Read JSON input
    $json = file_get_contents("php://input");
    $data = json_decode($json, true);
    
    if (!$data) {
        echo json_encode(["message" => "Invalid JSON input"]);
        exit;
    }
    
    // Validate required fields
    if (!isset($data['email']) || empty($data['email'])) {
        echo json_encode(["message" => "Email is required"]);
        exit;
    }
    
    $email = trim($data['email']);
    
    try {
        // Check if email exists in the database and is a patient
        $checkQuery = "SELECT user_id, email, role FROM users WHERE email = ? AND role = 'patient'";
        $stmt = $conn->prepare($checkQuery);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        
        if ($stmt->num_rows === 0) {
            echo json_encode(["message" => "Email not registered to a patient"]);
            exit;
        }
        
        // Get user details
        $stmt->bind_result($userId, $email, $role);
        $stmt->fetch();
        
        // Generate reset token and store it
        $resetToken = bin2hex(random_bytes(16));
        $expiresAt = date("Y-m-d H:i:s", strtotime("+30 minutes"));
        
        $updateQuery = "UPDATE users 
                        SET reset_token = ?, reset_token_expires = ?
                        WHERE user_id = ?";
        $stmt = $conn->prepare($updateQuery);
        if (!$stmt) {
            throw new Exception("Prepare failed: " . $conn->error);
        }
        $stmt->bind_param("sss", $resetToken, $expiresAt, $userId);
        $stmt->execute();
        
        // Send password reset email
        $resetLink = "http://localhost:3000/new-password?token=$resetToken";
        $success = sendPasswordResetEmail($email, $resetLink);
        
        if ($success) {
            echo json_encode([
                "success" => true,
                "message" => "Password reset email sent successfully"
            ]);
        } else {
            echo json_encode([
                "success" => false,
                "message" => "Email failed to send. Please try again."
            ]);
        }
        
    } catch (Exception $e) {
        error_log("Error: " . $e->getMessage());
        echo json_encode([
            "success" => false,
            "message" => "Error: " . $e->getMessage()
        ]);
    }
    
    // Function to send password reset email
    function sendPasswordResetEmail($email, $resetLink) {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'adejsamuel@gmail.com';
            $mail->Password = 'iyng nqfs zlpj ugah';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;
    
            $mail->setFrom('your_email@gmail.com', 'Password Reset');
            $mail->addAddress($email);
    
            $mail->isHTML(true);
            $mail->Subject = 'Password Reset Request';
            $mail->Body = "
                <div style='max-width: 800px; margin: 0 auto; font-family: Arial, sans-serif; color: #333333;'>
                    <h2>Password Reset</h2>
                    <p>We received a request to reset your password. Click the link below to reset your password:</p>
                    <a href='$resetLink' style='display: inline-block; padding: 10px 20px; background-color: #007bff; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0;'>
                        Reset Password
                    </a>
                    <p>If you did not request this password reset, you can safely ignore this email.</p>
                </div>
            ";
    
            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }
    ?>
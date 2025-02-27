<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

function sendEmail($to, $hospital_number, $password) {
    $mail = new PHPMailer(true);

    try {
        // MailChimp (Mandrill) SMTP settings
        $mail->isSMTP();
        $mail->Host = 'smtp.mandrillapp.com'; // Mandrill SMTP server
        $mail->SMTPAuth = true;
        $mail->Username = 'chideraokatta962@gmail.com'; // Your MailChimp email
        $mail->Password = '11bf4ca5bcd25d367fe087943e9a7503-us15'; // Mandrill API Key
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        // Email settings
        $mail->setFrom('your_email@example.com', 'Hospital Admin');
        $mail->addAddress($to);
        $mail->Subject = 'Your Hospital Credentials';

        // Email message
        $message = "
        <h2>Welcome to Our Patient Management System</h2>
        <p>Dear User,</p>
        <p>Your account has been successfully created. Below are your login credentials:</p>
        <ul>
            <li><strong>Hospital Number:</strong> $hospital_number</li>
            <li><strong>Password:</strong> $password</li>
        </ul>
        <p>You can use these details to log in to your account.</p>
        <p>Best regards,<br>Hospital Management</p>";

        $mail->isHTML(true);
        $mail->Body = $message;

        // Send email
        if ($mail->send()) {
            return true;
        } else {
            return false;
        }
    } catch (Exception $e) {
        return false;
    }
}
?>

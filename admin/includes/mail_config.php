<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

function sendAdminVerificationEmail($email, $first_name, $verification_token) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'mail.bfinitiatives.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'info@bfinitiatives.com';
        $mail->Password = 'K5Y)T{gvZ-NS';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('noreply@bfinitiatives.com', 'BFI Admin Portal');
        $mail->addAddress($email, $first_name);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Verify Your BFI Admin Portal Account';
        
        $verification_url = "https://bfinitiatives.com/admin/admin-verify.php?token=" . $verification_token;
        
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2>Welcome to BFI Admin Portal!</h2>
                <p>Dear {$first_name},</p>
                <p>Thank you for registering as an administrator. To complete your registration, please verify your email address by clicking the button below:</p>
                <p style='text-align: center;'>
                    <a href='{$verification_url}' 
                       style='display: inline-block; padding: 12px 24px; background-color: #3498db; 
                              color: white; text-decoration: none; border-radius: 5px;'>
                        Verify Email Address
                    </a>
                </p>
                <p>Or copy and paste this link into your browser:</p>
                <p>{$verification_url}</p>
                <p>This verification link will expire in 24 hours.</p>
                <p>If you didn't request this registration, please ignore this email.</p>
                <br>
                <p>Best regards,</p>
                <p>The BFI Admin Team</p>
            </div>
        ";
        
        $mail->AltBody = "
            Welcome to BFI Admin Portal!
            
            Dear {$first_name},
            
            Thank you for registering as an administrator. To complete your registration, please verify your email address by visiting this link:
            
            {$verification_url}
            
            This verification link will expire in 24 hours.
            
            If you didn't request this registration, please ignore this email.
            
            Best regards,
            The BFI Admin Team
        ";

        $mail->send();
        error_log("Email sent successfully to: $email");
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}
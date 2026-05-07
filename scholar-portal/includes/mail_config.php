<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php'; // You'll need to install PHPMailer via Composer

function sendVerificationEmail($userEmail, $firstName, $verificationToken) {
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'mail.bfinitiatives.com'; // Replace with your SMTP host
        $mail->SMTPAuth = true;
        $mail->Username = 'info@bfinitiatives.com'; // Replace with your email
        $mail->Password = 'K5Y)T{gvZ-NS'; // Replace with your password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('noreply@bfinitiatives.com', 'BFI Scholar Portal');
        $mail->addAddress($userEmail, $firstName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Verify Your BFI Scholar Portal Account';
        
        $verificationLink = "https://bfinitiatives.com/scholar-portal/verify-email.php?token=" . $verificationToken;
        
        $mail->Body = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
                <h2>Welcome to Bright Future Initiative!</h2>
                <p>Dear {$firstName},</p>
                <p>Thank you for registering with Bright Future Initiative. To complete your registration, please verify your email address by clicking the button below:</p>
                <p style='text-align: center;'>
                    <a href='{$verificationLink}' 
                       style='display: inline-block; padding: 12px 24px; background-color: #3498db; 
                              color: white; text-decoration: none; border-radius: 5px;'>
                        Verify Email Address
                    </a>
                </p>
                <p>Or copy and paste this link into your browser:</p>
                <p>{$verificationLink}</p>
                <p>This verification link will expire in 24 hours.</p>
                <p>If you didn't create an account, please ignore this email.</p>
                <br>
                <p>Best regards,</p>
                <p>The BFI Team</p>
            </div>
        ";
        
        $mail->AltBody = "
            Welcome to BFI Scholar Portal!
            
            Dear {$firstName},
            
            Thank you for registering with BFI Scholar Portal. To complete your registration, please verify your email address by visiting this link:
            
            {$verificationLink}
            
            This verification link will expire in 24 hours.
            
            If you didn't create an account, please ignore this email.
            
            Best regards,
            The BFI Team
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        return false;
    }
}
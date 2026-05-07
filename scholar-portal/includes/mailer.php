<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Make sure to update this path to match your vendor location
require '/home/bfinitia/public_html/scholar-portal/vendor/autoload.php';

/**
 * Send email using PHPMailer with your BFI email configuration
 * 
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $messageBody Email body (HTML)
 * @param string $recipientName Recipient name
 * @return array Status array with success flag and message
 */
function sendEmail($to, $subject, $messageBody, $recipientName = '') {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->SMTPDebug = 0; // Set to 3 for debugging
        $mail->isSMTP();
        $mail->Host = 'mail.bfinitiatives.com'; // Replace with your SMTP host
        $mail->SMTPAuth = true;
        $mail->Username = 'info@bfinitiatives.com'; // Replace with your email
        $mail->Password = 'K5Y)T{gvZ-NS'; // Replace with your password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Additional settings to help with delivery
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        // Set timeout
        $mail->Timeout = 30;

        // Recipients
        $mail->setFrom('info@bfinitiatives.com', 'BFI Mentorship Portal');
        $mail->addAddress($to, $recipientName);
        $mail->addReplyTo('info@bfinitiatives.com', 'BFI Mentorship Portal');

        // Add CCs for all status emails
        $mail->addCC('lanreylan@gmail.com');
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $messageBody;
        $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $messageBody));

        // Send the email
        $mail->send();
        error_log("Email sent successfully to: $to");
        
        return [
            'success' => true,
            'message' => 'Email sent successfully'
        ];
    } catch (Exception $e) {
        error_log("Email sending failed. Error: {$mail->ErrorInfo}");
        
        return [
            'success' => false,
            'message' => "Email could not be sent. Error: {$mail->ErrorInfo}"
        ];
    }
}

/**
 * Send document upload notification to admins
 * 
 * @param string $scholarName Scholar's full name
 * @param string $scholarEmail Scholar's email
 * @param string $documentType Document type uploaded
 * @param string $fileName Original file name
 * @param int $documentId Document ID in database
 * @return array Status array with success flag and message
 */
function sendDocumentUploadNotificationToAdmin($scholarName, $scholarEmail, $documentType, $fileName, $documentId) {
    // Format the document type for display
    $displayDocType = '';
    switch($documentType) {
        case 'cv': $displayDocType = 'CV/Resume'; break;
        case 'statement': $displayDocType = 'Personal Statement'; break;
        case 'research': $displayDocType = 'Research Proposal'; break;
        case 'recommendation': $displayDocType = 'Recommendation Letter'; break;
        case 'language': $displayDocType = 'Language Test'; break;
        default: $displayDocType = ucfirst($documentType);
    }
    
    // Create email subject
    $subject = "New Document Upload - " . $displayDocType . " from " . $scholarName;
    
    // Create email body
    $emailBody = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>New Document Upload</title>
    <style type="text/css">
        body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        table { border-collapse: collapse; }
        img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        .wrapper { width: 100%; background-color: #f4f6f8; padding: 20px 0; }
        .content { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
    </style>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f6f8;">
    <table role="presentation" class="wrapper" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td align="center">
                <table role="presentation" class="content" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width: 600px; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #4361ee 0%, #48cae4 100%); padding: 30px 20px; text-align: center;">
                            <h1 style="margin: 0; font-size: 24px; color: #ffffff; font-weight: 600;">
                                📄 New Document Upload
                            </h1>
                            <p style="margin: 10px 0 0; color: rgba(255,255,255,0.9); font-size: 14px;">
                                A scholar has uploaded a new document for review
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Body -->
                    <tr>
                        <td style="padding: 30px 20px;">
                            <p style="margin: 0 0 15px; font-size: 15px; color: #333333;">
                                Dear Admin,
                            </p>
                            <p style="margin: 0 0 20px; font-size: 14px; color: #555555; line-height: 1.6;">
                                A new document has been uploaded to the Scholar Portal and requires your review.
                            </p>
                            
                            <!-- Scholar Info Card -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 20px 0;">
                                <tr>
                                    <td style="background: #f8fafc; border-left: 4px solid #4361ee; border-radius: 8px; padding: 20px;">
                                        <p style="margin: 0 0 8px; font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                                            Scholar Information
                                        </p>
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td style="padding: 8px 0; color: #64748b; font-size: 13px;">Scholar Name:</td>
                                                <td align="right" style="padding: 8px 0; color: #1e293b; font-size: 13px; font-weight: 600;">' . htmlspecialchars($scholarName) . '</td>
                                            </tr>
                                            <tr>
                                                <td style="padding: 8px 0; color: #64748b; font-size: 13px;">Email:</td>
                                                <td align="right" style="padding: 8px 0; color: #1e293b; font-size: 13px; font-weight: 600;">' . htmlspecialchars($scholarEmail) . '</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Document Info -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 20px 0; background: #f1f5f9; border-radius: 8px; padding: 15px;">
                                <tr>
                                    <td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td style="color: #64748b; font-size: 13px; font-weight: 500;">Document Type</td>
                                                <td align="right" style="color: #1e293b; font-size: 13px; font-weight: 600;">' . htmlspecialchars($displayDocType) . '</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td style="color: #64748b; font-size: 13px; font-weight: 500;">File Name</td>
                                                <td align="right" style="color: #1e293b; font-size: 13px; font-weight: 600;">' . htmlspecialchars($fileName) . '</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td style="color: #64748b; font-size: 13px; font-weight: 500;">Upload Date</td>
                                                <td align="right" style="color: #1e293b; font-size: 13px; font-weight: 600;">' . date('F j, Y \a\t h:i A') . '</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Action Required Box -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 20px 0;">
                                <tr>
                                    <td style="background: #fff8e1; border-left: 4px solid #f59e0b; border-radius: 8px; padding: 20px;">
                                        <p style="margin: 0 0 10px; color: #92400e; font-weight: 600; font-size: 13px;">
                                            ⚠️ Action Required
                                        </p>
                                        <p style="margin: 0; color: #78350f; font-size: 13px; line-height: 1.6;">
                                            Please review this document at your earliest convenience. The scholar is waiting for your feedback.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Button -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 25px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="https://bfinitiatives.com/scholar-portal/admin-review-document.php?id=' . $documentId . '" 
                                           style="display: inline-block; background: linear-gradient(135deg, #4361ee 0%, #48cae4 100%); color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: 600; font-size: 14px;">
                                            Review Document Now
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="margin: 25px 0 0; font-size: 13px; color: #64748b; line-height: 1.6;">
                                You can also access the document review page from your admin dashboard under "Review Documents" section.
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0;">
                            <p style="margin: 0 0 5px; font-size: 13px; color: #1e293b; font-weight: 600;">
                                Bright Future Initiatives
                            </p>
                            <p style="margin: 0 0 5px; font-size: 12px; color: #64748b;">
                                Scholar Portal Admin System
                            </p>
                            <p style="margin: 15px 0 0; font-size: 12px; color: #64748b;">
                                <a href="https://bfinitiatives.com/scholar-portal/admin-dashboard.php" style="color: #4361ee; text-decoration: none; margin: 0 8px;">Admin Dashboard</a>
                                <span style="color: #cbd5e1;">|</span>
                                <a href="https://bfinitiatives.com/scholar-portal/admin-document-review.php" style="color: #4361ee; text-decoration: none; margin: 0 8px;">Document Review</a>
                            </p>
                            <p style="margin: 10px 0 0; font-size: 11px; color: #94a3b8;">
                                © ' . date('Y') . ' Bright Future Initiatives. All rights reserved.
                            </p>
                        </td>
                    </tr>
                    
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';

    // Create plain text version
    $plainTextBody = "New Document Upload Notification\n\n";
    $plainTextBody .= "Dear Admin,\n\n";
    $plainTextBody .= "A new document has been uploaded to the Scholar Portal and requires your review.\n\n";
    $plainTextBody .= "Scholar Information:\n";
    $plainTextBody .= "- Name: " . $scholarName . "\n";
    $plainTextBody .= "- Email: " . $scholarEmail . "\n\n";
    $plainTextBody .= "Document Details:\n";
    $plainTextBody .= "- Type: " . $displayDocType . "\n";
    $plainTextBody .= "- File Name: " . $fileName . "\n";
    $plainTextBody .= "- Upload Date: " . date('F j, Y \a\t h:i A') . "\n\n";
    $plainTextBody .= "Please review this document at your earliest convenience.\n\n";
    $plainTextBody .= "Review the document here:\n";
    $plainTextBody .= "https://bfinitiatives.com/scholar-portal/admin-review-document.php?id=" . $documentId . "\n\n";
    $plainTextBody .= "Best regards,\n";
    $plainTextBody .= "BFI Scholar Portal System";

    // Send the email using PHPMailer with improved settings
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = 'mail.bfinitiatives.com'; // Replace with your SMTP host
        $mail->SMTPAuth = true;
        $mail->Username = 'info@bfinitiatives.com'; // Replace with your email
        $mail->Password = 'K5Y)T{gvZ-NS'; // Replace with your password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        // Additional settings
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        $mail->Timeout = 30;

        // Anti-spam headers
        $mail->XMailer = ' ';
        $mail->Priority = 2; // High priority for admin notifications
        
        // Proper headers
        $mail->addCustomHeader('X-Entity-Ref-ID', 'BFI-DOC-UPLOAD-' . time());
        $mail->addCustomHeader('List-Unsubscribe', '<mailto:info@bfinitiatives.com?subject=unsubscribe>');
        
        // Recipients
        $mail->setFrom('info@bfinitiatives.com', 'BFI Scholar Portal');
        
        // Add all admin emails as recipients
        $mail->addAddress('lanreylan@gmail.com');
        $mail->addAddress('habeebadesola1@gmail.com');
        $mail->addAddress('manbau10@gmail.com');
        
        $mail->addReplyTo($scholarEmail, $scholarName);

        // Content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->Subject = $subject;
        $mail->Body = $emailBody;
        $mail->AltBody = $plainTextBody;

        // Send
        $mail->send();
        error_log("Document upload notification sent successfully to admins for document ID: $documentId");
        
        return [
            'success' => true,
            'message' => 'Admin notification sent successfully'
        ];
    } catch (Exception $e) {
        error_log("Document upload notification failed. Error: {$mail->ErrorInfo}");
        
        return [
            'success' => false,
            'message' => "Email could not be sent. Error: {$mail->ErrorInfo}"
        ];
    }
}

/**
 * Send document upload confirmation to scholar
 * 
 * @param string $scholarEmail Scholar's email
 * @param string $scholarName Scholar's first name
 * @param string $documentType Document type uploaded
 * @param string $fileName Original file name
 * @return array Status array with success flag and message
 */
function sendDocumentUploadConfirmationToScholar($scholarEmail, $scholarName, $documentType, $fileName) {
    // Format the document type for display
    $displayDocType = '';
    switch($documentType) {
        case 'cv': $displayDocType = 'CV/Resume'; break;
        case 'statement': $displayDocType = 'Personal Statement'; break;
        case 'research': $displayDocType = 'Research Proposal'; break;
        case 'recommendation': $displayDocType = 'Recommendation Letter'; break;
        case 'language': $displayDocType = 'Language Test'; break;
        default: $displayDocType = ucfirst($documentType);
    }
    
    // Create email subject
    $subject = "Document Upload Successful - " . $displayDocType;
    
    // Create email body
    $emailBody = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Document Upload Confirmation</title>
    <style type="text/css">
        body { margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        table { border-collapse: collapse; }
        img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        .wrapper { width: 100%; background-color: #f4f6f8; padding: 20px 0; }
        .content { max-width: 600px; margin: 0 auto; background-color: #ffffff; }
    </style>
</head>
<body style="margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f6f8;">
    <table role="presentation" class="wrapper" width="100%" cellpadding="0" cellspacing="0" border="0">
        <tr>
            <td align="center">
                <table role="presentation" class="content" width="600" cellpadding="0" cellspacing="0" border="0" style="max-width: 600px; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">
                    
                    <!-- Header -->
                    <tr>
                        <td style="background: linear-gradient(135deg, #4361ee 0%, #48cae4 100%); padding: 30px 20px; text-align: center;">
                            <h1 style="margin: 0; font-size: 24px; color: #ffffff; font-weight: 600;">
                                ✅ Document Upload Successful
                            </h1>
                            <p style="margin: 10px 0 0; color: rgba(255,255,255,0.9); font-size: 14px;">
                                Your document has been submitted for review
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Body -->
                    <tr>
                        <td style="padding: 30px 20px;">
                            <p style="margin: 0 0 15px; font-size: 15px; color: #333333;">
                                Dear <strong>' . htmlspecialchars($scholarName) . '</strong>,
                            </p>
                            <p style="margin: 0 0 20px; font-size: 14px; color: #555555; line-height: 1.6;">
                                We have successfully received your document upload. Your document has been sent to our review team for evaluation.
                            </p>
                            
                            <!-- Success Card -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 20px 0;">
                                <tr>
                                    <td style="background: #e6f9f0; border-left: 4px solid #10b981; border-radius: 8px; padding: 20px;">
                                        <p style="margin: 0 0 8px; font-size: 16px; color: #065f46; font-weight: 600;">
                                            ✓ Upload Confirmed
                                        </p>
                                        <p style="margin: 0; color: #047857; font-size: 13px; line-height: 1.6;">
                                            Your document has been successfully uploaded and is now pending review by our admissions team.
                                        </p>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Document Info -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 20px 0; background: #f1f5f9; border-radius: 8px; padding: 15px;">
                                <tr>
                                    <td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td style="color: #64748b; font-size: 13px; font-weight: 500;">Document Type</td>
                                                <td align="right" style="color: #1e293b; font-size: 13px; font-weight: 600;">' . htmlspecialchars($displayDocType) . '</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td style="color: #64748b; font-size: 13px; font-weight: 500;">File Name</td>
                                                <td align="right" style="color: #1e293b; font-size: 13px; font-weight: 600;">' . htmlspecialchars($fileName) . '</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0; border-bottom: 1px solid #e2e8f0;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td style="color: #64748b; font-size: 13px; font-weight: 500;">Upload Date</td>
                                                <td align="right" style="color: #1e293b; font-size: 13px; font-weight: 600;">' . date('F j, Y \a\t h:i A') . '</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td style="color: #64748b; font-size: 13px; font-weight: 500;">Status</td>
                                                <td align="right" style="color: #f59e0b; font-size: 13px; font-weight: 600;">Pending Review</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            
                            <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 25px 0;">
                            
                            <!-- Next Step -->
                            <h3 style="margin: 0 0 15px; font-size: 16px; color: #1e293b; font-weight: 600;">
                                📋 What Happens Next?
                            </h3>
                            <ul style="margin: 0 0 20px; padding-left: 20px; color: #555555; font-size: 14px; line-height: 1.8;">
                                <li style="margin-bottom: 10px;">Our review team will carefully evaluate your document</li>
                                <li style="margin-bottom: 10px;">You will receive an email notification once the review is complete</li>
                                <li style="margin-bottom: 10px;">If revisions are needed, we will provide detailed feedback</li>
                                <li>You can track your document status in your dashboard</li>
                            </ul>
                            
                            <!-- Button -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 25px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="https://bfinitiatives.com/scholar-portal/documents.php" 
                                           style="display: inline-block; background: linear-gradient(135deg, #4361ee 0%, #48cae4 100%); color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: 600; font-size: 14px;">
                                            View My Documents
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="margin: 25px 0 0; font-size: 13px; color: #64748b; line-height: 1.6;">
                                If you have any questions, please contact us at 
                                <a href="mailto:info@bfinitiatives.com" style="color: #4361ee; text-decoration: none;">info@bfinitiatives.com</a>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="background: #f8fafc; padding: 20px; text-align: center; border-top: 1px solid #e2e8f0;">
                            <p style="margin: 0 0 5px; font-size: 13px; color: #1e293b; font-weight: 600;">
                                Bright Future Initiatives
                            </p>
                            <p style="margin: 0 0 5px; font-size: 12px; color: #64748b;">
                                Empowering scholars to achieve their academic dreams
                            </p>
                            <p style="margin: 15px 0 0; font-size: 12px; color: #64748b;">
                                <a href="https://bfinitiatives.com" style="color: #4361ee; text-decoration: none; margin: 0 8px;">Visit Website</a>
                                <span style="color: #cbd5e1;">|</span>
                                <a href="https://bfinitiatives.com/contact.php" style="color: #4361ee; text-decoration: none; margin: 0 8px;">Contact Us</a>
                            </p>
                            <p style="margin: 10px 0 0; font-size: 11px; color: #94a3b8;">
                                © ' . date('Y') . ' Bright Future Initiatives. All rights reserved.
                            </p>
                        </td>
                    </tr>
                    
                </table>
            </td>
        </tr>
    </table>
</body>
</html>';

    // Create plain text version
    $plainTextBody = "Document Upload Confirmation\n\n";
    $plainTextBody .= "Dear " . $scholarName . ",\n\n";
    $plainTextBody .= "We have successfully received your document upload. Your document has been sent to our review team for evaluation.\n\n";
    $plainTextBody .= "Document Details:\n";
    $plainTextBody .= "- Type: " . $displayDocType . "\n";
    $plainTextBody .= "- File Name: " . $fileName . "\n";
    $plainTextBody .= "- Upload Date: " . date('F j, Y \a\t h:i A') . "\n";
    $plainTextBody .= "- Status: Pending Review\n\n";
    $plainTextBody .= "What Happens Next?\n";
    $plainTextBody .= "- Our review team will carefully evaluate your document\n";
    $plainTextBody .= "- You will receive an email notification once the review is complete\n";
    $plainTextBody .= "- If revisions are needed, we will provide detailed feedback\n";
    $plainTextBody .= "- You can track your document status in your dashboard\n\n";
    $plainTextBody .= "View your documents: https://bfinitiatives.com/scholar-portal/documents.php\n\n";
    $plainTextBody .= "If you have questions, contact us at info@bfinitiatives.com\n\n";
    $plainTextBody .= "Best regards,\n";
    $plainTextBody .= "Bright Future Initiatives Team";

    // Send the email
    return sendEmail($scholarEmail, $subject, $emailBody, $scholarName);
}
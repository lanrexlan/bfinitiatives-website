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
        $mail->addCC('habeebadesola1@gmail.com');
        $mail->addCC('manbau10@gmail.com');

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
 * Send application status update email
 * 
 * @param string $userEmail Recipient email
 * @param string $firstName Recipient first name
 * @param string $applicationId Application ID
 * @param string $newStatus New application status
 * @param string $adminComments Admin comments (optional)
 * @return array Status array with success flag and message
 */
function sendStatusUpdateEmail($userEmail, $firstName, $applicationId, $newStatus, $adminComments = '') {
    // Format the status for display
    $displayStatus = ucwords(str_replace('_', ' ', $newStatus));
    
    // Create email subject
    $subject = "BFI Mentorship Application - Status Update";
    
    // Create email body
    $emailBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BFI Mentorship Application Status</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f6f8; padding: 20px;">
    <div style="max-width: 600px; margin: auto; background-color: #ffffff; border-radius: 8px; overflow: hidden; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <div style="background-color: #2c3e50; color: #ffffff; text-align: center; padding: 20px;">
            <h1 style="margin: 0; font-size: 24px;">Mentorship Application Update</h1>
        </div>
        <div style="padding: 30px;">
            <p>Dear <strong>{$firstName}</strong>,</p>
            <p>We hope you are doing well. We want to inform you that your application for the <strong>BFI Mentorship Program</strong> has been updated.</p>
            <div style="background-color: #f0f4f8; padding: 20px; border-radius: 6px; margin: 20px 0;">
                <p style="margin: 0 0 10px; font-weight: bold;">Application Details</p>
                <p style="margin: 4px 0;">Application ID: <strong>{$applicationId}</strong></p>
                <p style="margin: 4px 0;">Current Status: <strong>{$displayStatus}</strong></p>
            </div>
HTML;

    if (!empty($adminComments)) {
        $emailBody .= <<<HTML
            <div style="background-color: #e8f4fd; padding: 20px; border-radius: 6px; margin: 20px 0;">
                <p style="margin: 0 0 10px; font-weight: bold;">Additional Comments</p>
                <p style="margin: 0;">{$adminComments}</p>
            </div>
HTML;
    }

    switch ($newStatus) {
        case 'approved':
            $emailBody .= <<<HTML
            <div style="background-color: #e6f9f0; padding: 20px; border-radius: 6px; margin: 20px 0; color: #155724;">
                <p style="margin: 0 0 10px; font-size: 16px;"><strong>Congratulations!</strong></p>
                <p style="margin: 0;">Your application has been <strong>approved</strong> to join our exclusive Mentorship Program. We are thrilled to have you on board and will be in touch shortly with details about the next steps. Prepare to embark on a transformative learning journey! ðŸš€</p>
            </div>
HTML;
            break;

        case 'rejected':
            $emailBody .= <<<HTML
            <div style="background-color: #fdecea; padding: 20px; border-radius: 6px; margin: 20px 0; color: #721c24;">
                <p style="margin: 0 0 10px; font-size: 16px;"><strong>Notice of Unsuccessful Application</strong></p>
                <p style="margin: 0 0 10px;">We appreciate the time and effort you invested in your application to the BFI Mentorship Program. After careful consideration by our selection committee, we regret to inform you that we are unable to offer you a place in this cohort.</p>
                <p style="margin: 0 0 10px;">The competition was exceptionally strong, and many highly qualified candidates applied. Although you were not selected this time, we recognize your dedication and potential. We encourage you to continue honing your skills, seeking feedback, and applying again when the next cohort opens.</p>
                <p style="margin: 0;">Thank you for your interest in BFI and for contributing to our mission of empowering future leaders. We wish you every success in your academic and professional endeavors and hope to see your application in the next cycle.</p>
            </div>
HTML;
            break;

        case 'under_review':
            $emailBody .= <<<HTML
            <div style="background-color: #fff8e1; padding: 20px; border-radius: 6px; margin: 20px 0; color: #856404;">
                <p style="margin: 0 0 10px; font-size: 16px;"><strong>Update:</strong></p>
                <p style="margin: 0;">Your application is currently <strong>under review</strong> by our mentorship committee. We appreciate your patience and will notify you of any updates as soon as the review is complete.</p>
            </div>
HTML;
            break;

        case 'pending_documents':
            $emailBody .= <<<HTML
            <div style="background-color: #fff8e1; padding: 20px; border-radius: 6px; margin: 20px 0; color: #856404;">
                <p style="margin: 0 0 10px; font-size: 16px;"><strong>Action Required:</strong></p>
                <p style="margin: 0;">We are missing some required documents to complete your application. Please submit the requested documents (listed above) at your earliest convenience to continue the review process.</p>
            </div>
HTML;
            break;
    }

    $emailBody .= <<<HTML
            <p style="margin: 20px 0;">You can always check your application status by logging into the <a href="https://bfinitiatives.com/check-status.php" style="color: #3498db; text-decoration: none;">BFI Mentorship Portal</a>.</p>
            <p style="margin: 20px 0;">If you have any questions, please donâ€™t hesitate to contact us at <a href="mailto:info@bfinitiatives.com" style="color: #3498db; text-decoration: none;">info@bfinitiatives.com</a>.</p>
            <div style="border-top: 1px solid #eee; padding-top: 20px; margin-top: 30px; text-align: center; font-size: 14px; color: #777;">
                <p style="margin: 0;">Bright Future Initiatives</p>
                <p style="margin: 4px 0;">Ede, Osun State, Nigeria</p>
                <p style="margin: 4px 0;">www.bfinitiatives.com</p>
            </div>
        </div>
    </div>
</body>
</html>
HTML;

    // Send the email using our general email function
    return sendEmail($userEmail, $subject, $emailBody, $firstName);
}


/**
 * Send document feedback email notification
 * 
 * @param string $userEmail Recipient email
 * @param string $firstName Recipient first name
 * @param string $documentType Document type label
 * @param string $newStatus New document status
 * @param string $feedback Admin feedback (optional)
 * @return array Status array with success flag and message
 */
function sendDocumentFeedbackEmail($userEmail, $firstName, $documentType, $newStatus, $feedback = '') {
    // Format the status for display
    $displayStatus = ucwords(str_replace('_', ' ', $newStatus));
    
    // Determine status color and icon
    $statusColor = '#6366f1';
    $statusIcon = '📄';
    $statusMessage = '';
    
    switch($newStatus) {
        case 'in_review':
            $statusColor = '#6366f1';
            $statusIcon = '🔍';
            $statusMessage = 'Your document is currently being reviewed. You will receive another notification once the review is complete.';
            break;
        case 'needs_revision':
            $statusColor = '#ef4444';
            $statusIcon = '✏️';
            $statusMessage = 'Please review the feedback carefully and make the necessary revisions to your document. Once updated, you can resubmit it for review.';
            break;
        case 'approved':
            $statusColor = '#10b981';
            $statusIcon = '✅';
            $statusMessage = 'Congratulations! Your document has been approved. You can proceed to the next step in your application process.';
            break;
        default:
            $statusMessage = 'Your document status has been updated. Please check your dashboard for more details.';
    }
    
    // Escape content for HTML
    $safeFeedback = htmlspecialchars($feedback, ENT_QUOTES, 'UTF-8');
    $safeFirstName = htmlspecialchars($firstName, ENT_QUOTES, 'UTF-8');
    $safeDocumentType = htmlspecialchars($documentType, ENT_QUOTES, 'UTF-8');
    
    // Create email subject - keep it simple and professional
    $subject = "Document Review Update: " . $documentType;
    
    // Create email body with better formatting
    $emailBody = '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <title>Document Review Update</title>
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
                                ' . $statusIcon . ' Document Review Update
                            </h1>
                            <p style="margin: 10px 0 0; color: rgba(255,255,255,0.9); font-size: 14px;">
                                Your document has been reviewed
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Body -->
                    <tr>
                        <td style="padding: 30px 20px;">
                            <p style="margin: 0 0 15px; font-size: 15px; color: #333333;">
                                Dear <strong>' . $safeFirstName . '</strong>,
                            </p>
                            <p style="margin: 0 0 20px; font-size: 14px; color: #555555; line-height: 1.6;">
                                We have completed our review of your <strong>' . $safeDocumentType . '</strong>. Please see the details below:
                            </p>
                            
                            <!-- Status Card -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 20px 0;">
                                <tr>
                                    <td style="background: #f8fafc; border-left: 4px solid ' . $statusColor . '; border-radius: 8px; padding: 20px;">
                                        <p style="margin: 0 0 8px; font-size: 12px; color: #64748b; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px;">
                                            Review Status
                                        </p>
                                        <p style="margin: 0; font-size: 18px; color: ' . $statusColor . '; font-weight: 700;">
                                            ' . $statusIcon . ' ' . $displayStatus . '
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
                                                <td align="right" style="color: #1e293b; font-size: 13px; font-weight: 600;">' . $safeDocumentType . '</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="padding: 8px 0;">
                                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
                                            <tr>
                                                <td style="color: #64748b; font-size: 13px; font-weight: 500;">Review Date</td>
                                                <td align="right" style="color: #1e293b; font-size: 13px; font-weight: 600;">' . date('F j, Y') . '</td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>';
    
    // Add feedback box if feedback is provided
    if (!empty($feedback)) {
        $emailBody .= '
                            <!-- Feedback Box -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 20px 0;">
                                <tr>
                                    <td style="background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 8px; padding: 20px;">
                                        <p style="margin: 0 0 10px; color: #92400e; font-weight: 600; font-size: 13px;">
                                            💬 Reviewer Feedback
                                        </p>
                                        <p style="margin: 0; color: #78350f; font-size: 13px; line-height: 1.6; white-space: pre-wrap;">' . $safeFeedback . '</p>
                                    </td>
                                </tr>
                            </table>';
    }

    $emailBody .= '
                            <hr style="border: none; border-top: 1px solid #e2e8f0; margin: 25px 0;">
                            
                            <p style="margin: 20px 0; font-size: 14px; color: #555555; line-height: 1.6;">
                                ' . $statusMessage . '
                            </p>
                            
                            <!-- Button -->
                            <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 25px 0;">
                                <tr>
                                    <td align="center">
                                        <a href="https://bfinitiatives.com/scholar-portal/dashboard.php" 
                                           style="display: inline-block; background: linear-gradient(135deg, #4361ee 0%, #48cae4 100%); color: #ffffff; text-decoration: none; padding: 12px 28px; border-radius: 8px; font-weight: 600; font-size: 14px;">
                                            View Document Details
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <p style="margin: 25px 0 0; font-size: 13px; color: #64748b; line-height: 1.6;">
                                If you have any questions about this feedback, please contact us at 
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
                                <a href="https://bfinitiatives.com/contact.html" style="color: #4361ee; text-decoration: none; margin: 0 8px;">Contact Us</a>
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

    // Create plain text version (important for spam filters)
    $plainTextBody = "Hello " . $firstName . ",\n\n";
    $plainTextBody .= "Your " . $documentType . " has been reviewed.\n\n";
    $plainTextBody .= "Review Status: " . $displayStatus . "\n";
    $plainTextBody .= "Review Date: " . date('F j, Y') . "\n\n";
    
    if (!empty($feedback)) {
        $plainTextBody .= "Reviewer Feedback:\n" . $feedback . "\n\n";
    }
    
    $plainTextBody .= $statusMessage . "\n\n";
    $plainTextBody .= "View your document details: https://bfinitiatives.com/scholar-portal/dashboard.php\n\n";
    $plainTextBody .= "If you have questions, contact us at info@bfinitiatives.com\n\n";
    $plainTextBody .= "Best regards,\n";
    $plainTextBody .= "Bright Future Initiatives Team\n";
    $plainTextBody .= "https://bfinitiatives.com";

    // Send the email using our general email function with improved settings
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = 'mail.bfinitiatives.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'info@bfinitiatives.com';
        $mail->Password = 'K5Y)T{gvZ-NS';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

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
        $mail->XMailer = ' '; // Remove X-Mailer header
        $mail->Priority = 3; // Normal priority
        
        // Proper headers to avoid spam
        $mail->addCustomHeader('X-Entity-Ref-ID', 'BFI-DOC-' . time());
        $mail->addCustomHeader('List-Unsubscribe', '<mailto:info@bfinitiatives.com?subject=unsubscribe>');
        
        // Recipients
        $mail->setFrom('info@bfinitiatives.com', 'BFI Scholar Portal');
        $mail->addAddress($userEmail, $firstName);
        $mail->addReplyTo('info@bfinitiatives.com', 'BFI Scholar Portal');

        // Add CCs for internal tracking
        $mail->addCC('lanreylan@gmail.com');
        $mail->addCC('habeebadesola1@gmail.com');
        $mail->addCC('manbau10@gmail.com');
        

        // Content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Encoding = 'base64';
        $mail->Subject = $subject;
        $mail->Body = $emailBody;
        $mail->AltBody = $plainTextBody;

        // Send
        $mail->send();
        error_log("Document feedback email sent successfully to: $userEmail");
        
        return [
            'success' => true,
            'message' => 'Email sent successfully'
        ];
    } catch (Exception $e) {
        error_log("Document feedback email failed. Error: {$mail->ErrorInfo}");
        
        return [
            'success' => false,
            'message' => "Email could not be sent. Error: {$mail->ErrorInfo}"
        ];
    }
}
?>

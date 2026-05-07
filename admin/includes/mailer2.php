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
        $mail->Host = 'mail.bfinitiatives.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'info@bfinitiatives.com';
        $mail->Password = 'K5Y)T{gvZ-NS';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = 465;

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
 * Send document feedback email notification
 */
function sendDocumentFeedbackEmail($email, $firstName, $documentType, $status, $feedback) {
    $mail = getMailer();
    
    try {
        // Recipients
        $mail->addAddress($email, $firstName);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Document Review Update - Bright Future Initiatives';
        
        // Format status for display
        $statusDisplay = ucwords(str_replace('_', ' ', $status));
        
        // Determine status color and icon
        $statusColor = '#6366f1'; // default
        $statusIcon = '📄';
        
        switch($status) {
            case 'in_review':
                $statusColor = '#6366f1';
                $statusIcon = '🔍';
                break;
            case 'needs_revision':
                $statusColor = '#ef4444';
                $statusIcon = '✏️';
                break;
            case 'approved':
                $statusColor = '#10b981';
                $statusIcon = '✅';
                break;
        }
        
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background-color: #f4f6f8; }
                .container { max-width: 600px; margin: 20px auto; background: white; border-radius: 16px; overflow: hidden; box-shadow: 0 8px 30px rgba(0,0,0,0.1); }
                .header { background: linear-gradient(135deg, #4361ee 0%, #48cae4 100%); color: white; padding: 40px 30px; text-align: center; }
                .header h1 { margin: 0; font-size: 28px; font-weight: 700; }
                .header p { margin: 10px 0 0; opacity: 0.9; font-size: 16px; }
                .content { padding: 40px 30px; }
                .greeting { font-size: 18px; font-weight: 600; color: #1e293b; margin-bottom: 20px; }
                .message { color: #475569; font-size: 15px; line-height: 1.8; margin-bottom: 25px; }
                .status-card { background: #f8fafc; border-left: 4px solid {$statusColor}; border-radius: 10px; padding: 20px; margin: 25px 0; }
                .status-label { color: #64748b; font-size: 13px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 8px; }
                .status-value { color: {$statusColor}; font-size: 20px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
                .document-info { background: #f1f5f9; border-radius: 10px; padding: 20px; margin: 25px 0; }
                .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #e2e8f0; }
                .info-row:last-child { border-bottom: none; }
                .info-label { color: #64748b; font-weight: 500; }
                .info-value { color: #1e293b; font-weight: 600; }
                .feedback-box { background: #fef3c7; border-left: 4px solid #f59e0b; border-radius: 10px; padding: 20px; margin: 25px 0; }
                .feedback-title { color: #92400e; font-weight: 600; font-size: 14px; margin-bottom: 10px; display: flex; align-items: center; gap: 8px; }
                .feedback-text { color: #78350f; font-size: 14px; line-height: 1.8; white-space: pre-wrap; }
                .button { display: inline-block; background: linear-gradient(135deg, #4361ee 0%, #48cae4 100%); color: white !important; text-decoration: none; padding: 14px 32px; border-radius: 10px; font-weight: 600; font-size: 15px; margin: 20px 0; box-shadow: 0 4px 15px rgba(67, 97, 238, 0.3); transition: all 0.3s; }
                .button:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(67, 97, 238, 0.4); }
                .footer { background: #f8fafc; padding: 30px; text-align: center; border-top: 1px solid #e2e8f0; }
                .footer p { color: #64748b; font-size: 13px; margin: 5px 0; }
                .divider { height: 1px; background: #e2e8f0; margin: 30px 0; }
                @media only screen and (max-width: 600px) {
                    .container { margin: 10px; }
                    .content { padding: 30px 20px; }
                    .header { padding: 30px 20px; }
                }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>{$statusIcon} Document Review Update</h1>
                    <p>Your document has been reviewed</p>
                </div>
                
                <div class='content'>
                    <div class='greeting'>Hello {$firstName},</div>
                    
                    <div class='message'>
                        We have completed our review of your <strong>{$documentType}</strong>. Please see the details below:
                    </div>
                    
                    <div class='status-card'>
                        <div class='status-label'>Review Status</div>
                        <div class='status-value'>
                            <span>{$statusIcon}</span>
                            <span>{$statusDisplay}</span>
                        </div>
                    </div>
                    
                    <div class='document-info'>
                        <div class='info-row'>
                            <span class='info-label'>Document Type</span>
                            <span class='info-value'>{$documentType}</span>
                        </div>
                        <div class='info-row'>
                            <span class='info-label'>Review Date</span>
                            <span class='info-value'>" . date('F j, Y') . "</span>
                        </div>
                    </div>
                    
                    " . (!empty($feedback) ? "
                    <div class='feedback-box'>
                        <div class='feedback-title'>
                            <span>💬</span>
                            <span>Reviewer Feedback</span>
                        </div>
                        <div class='feedback-text'>{$feedback}</div>
                    </div>
                    " : "") . "
                    
                    <div class='divider'></div>
                    
                    <div class='message'>
                        " . ($status === 'needs_revision' 
                            ? "Please review the feedback carefully and make the necessary revisions to your document. Once updated, you can resubmit it for review." 
                            : ($status === 'approved' 
                                ? "Congratulations! Your document has been approved. You can proceed to the next step in your application process." 
                                : "Your document is currently being reviewed. You will receive another notification once the review is complete.")) . "
                    </div>
                    
                    <center>
                        <a href='" . SITE_URL . "/scholar-dashboard.php' class='button'>View Document Details</a>
                    </center>
                    
                    <div class='message' style='margin-top: 30px; font-size: 14px; color: #64748b;'>
                        If you have any questions about this feedback, please don't hesitate to contact our support team.
                    </div>
                </div>
                
                <div class='footer'>
                    <p><strong>Bright Future Initiatives</strong></p>
                    <p>Empowering scholars to achieve their academic dreams</p>
                    <p style='margin-top: 15px;'>
                        <a href='" . SITE_URL . "' style='color: #4361ee; text-decoration: none;'>Visit Website</a> | 
                        <a href='" . SITE_URL . "/contact.php' style='color: #4361ee; text-decoration: none;'>Contact Us</a>
                    </p>
                    <p style='margin-top: 15px; font-size: 12px;'>© " . date('Y') . " Bright Future Initiatives. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        // Plain text version
        $mail->AltBody = "Hello {$firstName},\n\n"
            . "Your {$documentType} has been reviewed.\n\n"
            . "Review Status: {$statusDisplay}\n\n"
            . (!empty($feedback) ? "Feedback:\n{$feedback}\n\n" : "")
            . "Please log in to your dashboard to view the full details.\n\n"
            . "Best regards,\nBright Future Initiatives Team";
        
        $mail->send();
        return ['success' => true, 'message' => 'Email sent successfully'];
    } catch (Exception $e) {
        error_log("Document feedback email error: " . $e->getMessage());
        return ['success' => false, 'message' => $e->getMessage()];
    }
}
?>

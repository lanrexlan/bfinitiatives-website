<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/app_bootstrap.php';
bfi_require_phpmailer();

// Database connection
try {
    $db_connection = bfi_pg_connect('users');
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("Connection failed. Please try again later.");
}

// Function to validate file upload
function validateFile($file, $allowedTypes = ['pdf', 'doc', 'docx']) {
    if ($file['error'] !== 0) {
        return "File upload failed";
    }

    $fileInfo = pathinfo($file['name']);
    $extension = strtolower($fileInfo['extension']);

    if (!in_array($extension, $allowedTypes)) {
        return "Invalid file type. Allowed types: " . implode(', ', $allowedTypes);
    }

    if ($file['size'] > 5 * 1024 * 1024) { // 5MB limit
        return "File size too large. Maximum size: 5MB";
    }

    return null;
}

// Function to handle file upload
function handleFileUpload($file, $nomination_reference, $fileType) {
    $upload_dir = 'uploads/nominations/' . date('Y') . '/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $fileInfo = pathinfo($file['name']);
    $extension = strtolower($fileInfo['extension']);
    $newFilename = $nomination_reference . '_' . $fileType . '.' . $extension;
    $uploadPath = $upload_dir . $newFilename;

    if (!move_uploaded_file($file['tmp_name'], $uploadPath)) {
        throw new Exception("Failed to upload " . $fileType);
    }

    return $newFilename;
}

// Function to validate email format
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Function to send nominator confirmation email
function sendNominatorEmail($nominatorEmail, $nominatorFirstName, $nomineeFirstName, $nomineeLastName, $nominationReference) {
    $mail = new PHPMailer(true);
    
    try {
        bfi_configure_mailer($mail, [
            'from_name' => 'BFI Initiatives',
            'reply_to_email' => bfi_admin_email(),
            'reply_to_name' => 'BFI Initiatives',
        ]);
        $mail->addAddress($nominatorEmail, $nominatorFirstName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'BFI Talent of the Year - Nomination Received';
        
        // HTML email template
        $emailBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #ffffff; border-radius: 10px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="text-align: center; margin-bottom: 30px;">
            <h1 style="color: #f59e0b; font-size: 24px; margin-bottom: 10px;">Nomination Confirmation</h1>
        </div>

        <p style="margin-bottom: 20px;">Dear {$nominatorFirstName},</p>

        <p style="margin-bottom: 20px;">Thank you for nominating <strong>{$nomineeFirstName} {$nomineeLastName}</strong> for the BFI Talent of the Year Award. We appreciate your effort in recognizing exceptional talent.</p>

        <div style="background-color: #fff9eb; padding: 15px; margin: 20px 0; border-radius: 5px;">
            <p style="margin: 0; font-weight: bold;">Nomination Reference:</p>
            <p style="margin: 10px 0; font-size: 18px;"><strong>{$nominationReference}</strong></p>
            <p style="margin: 0;">Please save this reference number for future inquiries.</p>
        </div>

        <div style="margin: 30px 0;">
            <h2 style="color: #f59e0b; font-size: 18px;">What happens next?</h2>
            <ul style="list-style-type: none; padding-left: 0;">
                <li style="margin-bottom: 10px;">✓ We will reach out to the nominee to inform them of their nomination</li>
                <li style="margin-bottom: 10px;">✓ The selection committee will review all nominations</li>
                <li style="margin-bottom: 10px;">✓ All nominators will be notified of the results</li>
            </ul>
        </div>

        <p style="margin: 20px 0;">If you have any questions, please contact us at <a href="mailto:info@bfinitiatives.com" style="color: #f59e0b;">info@bfinitiatives.com</a></p>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
            <p style="margin: 0;">Best regards,<br>BFI Award Team</p>
        </div>
    </div>
</body>
</html>
HTML;

        $mail->Body = $emailBody;
        
        // Plain text version
        $mail->AltBody = "Dear {$nominatorFirstName},\n\n"
            . "Thank you for nominating {$nomineeFirstName} {$nomineeLastName} for the BFI Talent of the Year Award.\n\n"
            . "Nomination Reference: {$nominationReference}\n\n"
            . "Please save this reference number for future inquiries.\n\n"
            . "What happens next?\n"
            . "- We will reach out to the nominee to inform them of their nomination\n"
            . "- The selection committee will review all nominations\n"
            . "- All nominators will be notified of the results\n\n"
            . "If you have any questions, please contact us at info@bfinitiatives.com\n\n"
            . "Best regards,\nBFI Award Team";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail Error: " . $e->getMessage());
        return false;
    }
}

// Function to send email to nominee
function sendNomineeEmail($nomineeEmail, $nomineeFirstName, $nominatorFirstName, $nominatorLastName, $nominationReference) {
    $mail = new PHPMailer(true);
    
    try {
        bfi_configure_mailer($mail, [
            'from_name' => 'BFI Initiatives',
            'reply_to_email' => bfi_admin_email(),
            'reply_to_name' => 'BFI Initiatives',
        ]);
        $mail->addAddress($nomineeEmail, $nomineeFirstName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = "You've Been Nominated for the BFI Talent of the Year Award";
        $applicationUrl = bfi_public_url('talent.html?ref=' . urlencode($nominationReference));
        
        // HTML email template
        $emailBody = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    <div style="background-color: #ffffff; border-radius: 10px; padding: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        <div style="text-align: center; margin-bottom: 30px;">
            <h1 style="color: #2c5282; font-size: 24px; margin-bottom: 10px;">You've Been Nominated!</h1>
        </div>

        <p style="margin-bottom: 20px;">Dear {$nomineeFirstName},</p>

        <p style="margin-bottom: 20px;">We are pleased to inform you that <strong>{$nominatorFirstName} {$nominatorLastName}</strong> has nominated you for the BFI Talent of the Year Award.</p>
        
        <p style="margin-bottom: 20px;">This prestigious award recognizes exceptional Nigerian students who demonstrate outstanding academic excellence, leadership abilities, and commitment to community service.</p>

        <div style="background-color: #f0f7ff; padding: 15px; margin: 20px 0; border-radius: 5px;">
            <p style="margin: 0; font-weight: bold;">To complete your application, please click the button below:</p>
            <div style="text-align: center; margin-top: 20px;">
                <a href="{$applicationUrl}" style="background-color: #2c5282; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;">Complete Your Application</a>
            </div>
        </div>

        <p style="margin: 20px 0;">If you have any questions about the nomination or the award process, please contact us at <a href="mailto:info@bfinitiatives.com" style="color: #2c5282;">info@bfinitiatives.com</a></p>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
            <p style="margin: 0;">Best regards,<br>BFI Award Team</p>
        </div>
    </div>
</body>
</html>
HTML;

        $mail->Body = $emailBody;
        
        // Plain text version
        $mail->AltBody = "Dear {$nomineeFirstName},\n\n"
            . "We are pleased to inform you that {$nominatorFirstName} {$nominatorLastName} has nominated you for the BFI Talent of the Year Award.\n\n"
            . "This prestigious award recognizes exceptional Nigerian students who demonstrate outstanding academic excellence, leadership abilities, and commitment to community service.\n\n"
            . "To complete your application, please visit: {$applicationUrl}\n\n"
            . "If you have any questions about the nomination or the award process, please contact us at info@bfinitiatives.com\n\n"
            . "Best regards,\nBFI Award Team";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Nominee notification mail error: " . $e->getMessage());
        return false;
    }
}

// Function to send admin notification
function sendAdminNotificationEmail($nominationReference, $nominatorName, $nomineeName, $nomineeEmail) {
    $mail = new PHPMailer(true);
    
    try {
        bfi_configure_mailer($mail, [
            'from_name' => 'BFI Talent System',
            'reply_to_email' => bfi_admin_email(),
            'reply_to_name' => 'BFI Talent System',
        ]);
        $mail->addAddress(bfi_admin_email(), 'BFI Admin');

        // Content
        $mail->isHTML(true);
        $mail->Subject = "New BFI Talent Nomination - $nominationReference";
        
        $adminMessage = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9; border-radius: 10px;">
        <h2 style="color: #f59e0b;">New Talent Award Nomination</h2>
        
        <p>A new nomination has been submitted for the BFI Talent of the Year Award.</p>
        
        <div style="background-color: #ffffff; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <p><strong>Reference:</strong> {$nominationReference}</p>
            <p><strong>Nominator:</strong> {$nominatorName}</p>
            <p><strong>Nominee:</strong> {$nomineeName}</p>
            <p><strong>Nominee Email:</strong> {$nomineeEmail}</p>
            <p><strong>Date Submitted:</strong> {date('Y-m-d H:i:s')}</p>
        </div>
        
        <p>Please log in to the admin portal to review the complete nomination.</p>
        
        <div style="margin-top: 30px;">
            <p>BFI Talent Nomination System</p>
        </div>
    </div>
</body>
</html>
HTML;

        $mail->Body = $adminMessage;
        
        // Plain text alternative
        $mail->AltBody = "New Talent Award Nomination\n\n"
            . "Reference: {$nominationReference}\n"
            . "Nominator: {$nominatorName}\n"
            . "Nominee: {$nomineeName}\n"
            . "Nominee Email: {$nomineeEmail}\n"
            . "Date Submitted: " . date('Y-m-d H:i:s') . "\n\n"
            . "Please log in to the admin portal to review the complete nomination.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Admin notification mail error: " . $e->getMessage());
        return false;
    }
}

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Generate nomination reference
        $nominationReference = 'BFI-NOM-' . date('Y') . '-' . mt_rand(1000, 9999);
        
        // Nominator Information
        $nominatorFirstName = isset($_POST['nominatorFirstName']) ? trim($_POST['nominatorFirstName']) : '';
        $nominatorLastName = isset($_POST['nominatorLastName']) ? trim($_POST['nominatorLastName']) : '';
        $nominatorEmail = isset($_POST['nominatorEmail']) ? trim($_POST['nominatorEmail']) : '';
        $nominatorPhone = isset($_POST['nominatorPhone']) ? trim($_POST['nominatorPhone']) : '';
        $relationship = isset($_POST['relationship']) ? trim($_POST['relationship']) : '';
        $otherRelationship = isset($_POST['otherRelationship']) ? trim($_POST['otherRelationship']) : '';
        $howLongKnown = isset($_POST['howLongKnown']) ? trim($_POST['howLongKnown']) : '';
        
        // Nominee Information
        $nomineeFirstName = isset($_POST['nomineeFirstName']) ? trim($_POST['nomineeFirstName']) : '';
        $nomineeLastName = isset($_POST['nomineeLastName']) ? trim($_POST['nomineeLastName']) : '';
        $nomineeEmail = isset($_POST['nomineeEmail']) ? trim($_POST['nomineeEmail']) : '';
        $nomineePhone = isset($_POST['nomineePhone']) ? trim($_POST['nomineePhone']) : '';
        $institution = isset($_POST['institution']) ? trim($_POST['institution']) : '';
        $fieldOfStudy = isset($_POST['fieldOfStudy']) ? trim($_POST['fieldOfStudy']) : '';
        $academicLevel = isset($_POST['academicLevel']) ? trim($_POST['academicLevel']) : '';
        
        // Nomination Reasons
        $academicExcellence = isset($_POST['academicExcellence']) ? trim($_POST['academicExcellence']) : '';
        $leadershipSkills = isset($_POST['leadershipSkills']) ? trim($_POST['leadershipSkills']) : '';
        $communityImpact = isset($_POST['communityImpact']) ? trim($_POST['communityImpact']) : '';
        $additionalQualities = isset($_POST['additionalQualities']) ? trim($_POST['additionalQualities']) : '';
        
        // Other information
        $informedNominee = isset($_POST['informedNominee']) ? true : false;
        
        // Validate required fields
        if (empty($nominatorFirstName) || empty($nominatorLastName) || empty($nominatorEmail) || 
            empty($nominatorPhone) || empty($relationship) || empty($howLongKnown) || 
            empty($nomineeFirstName) || empty($nomineeLastName) || empty($nomineeEmail) || 
            empty($institution) || empty($fieldOfStudy) || empty($academicLevel) || 
            empty($academicExcellence) || empty($leadershipSkills) || empty($communityImpact)) {
            
            throw new Exception('Please fill in all required fields.');
        }
        
        // Validate emails
        if (!validateEmail($nominatorEmail)) {
            throw new Exception('Please enter a valid nominator email address.');
        }
        
        if (!validateEmail($nomineeEmail)) {
            throw new Exception('Please enter a valid nominee email address.');
        }
        
        // If relationship is "other", check for other relationship field
        if ($relationship === 'other' && empty($otherRelationship)) {
            throw new Exception('Please specify your relationship to the nominee.');
        }
        
        // Upload supporting document (optional)
        $supportingDocumentPath = null;
        if (isset($_FILES['supportingDocument']) && $_FILES['supportingDocument']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/nominations/' . date('Y') . '/';
            $allowedTypes = ['pdf', 'doc', 'docx'];
            
            $supportingDocumentPath = handleFileUpload($_FILES['supportingDocument'], $nominationReference, 'supporting');
            if (!$supportingDocumentPath && $_FILES['supportingDocument']['error'] !== UPLOAD_ERR_NO_FILE) {
                throw new Exception('Failed to upload supporting document. Please check file format and try again.');
            }
        }
        
        // Insert data into database
        $query = "INSERT INTO talent_nominations (
            nomination_reference, nominator_first_name, nominator_last_name, nominator_email, 
            nominator_phone, relationship, other_relationship, how_long_known, 
            nominee_first_name, nominee_last_name, nominee_email, nominee_phone, 
            institution, field_of_study, academic_level, academic_excellence, 
            leadership_skills, community_impact, additional_qualities, 
            supporting_document_path, informed_nominee, status, created_at
        ) VALUES (
            $1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16, $17, $18, $19, $20, $21, 'pending', CURRENT_TIMESTAMP
        ) RETURNING id";
        
        $result = pg_query_params(
            $db_connection,
            $query,
            array(
                $nominationReference, $nominatorFirstName, $nominatorLastName, $nominatorEmail, 
                $nominatorPhone, $relationship, $otherRelationship, $howLongKnown, 
                $nomineeFirstName, $nomineeLastName, $nomineeEmail, $nomineePhone, 
                $institution, $fieldOfStudy, $academicLevel, $academicExcellence, 
                $leadershipSkills, $communityImpact, $additionalQualities, 
                $supportingDocumentPath, $informedNominee
            )
        );
        
        if (!$result) {
            throw new Exception(pg_last_error($db_connection));
        }
        
        // Send confirmation email to nominator
        $nominatorEmailSent = sendNominatorEmail(
            $nominatorEmail, 
            $nominatorFirstName, 
            $nomineeFirstName, 
            $nomineeLastName, 
            $nominationReference
        );
        
        // Send notification to nominee if not informed by nominator
        $nomineeEmailSent = false;
        if (!$informedNominee) {
            $nomineeEmailSent = sendNomineeEmail(
                $nomineeEmail,
                $nomineeFirstName,
                $nominatorFirstName,
                $nominatorLastName,
                $nominationReference
            );
        }
        
        // Send notification to admin
        $adminEmailSent = sendAdminNotificationEmail(
            $nominationReference,
            "$nominatorFirstName $nominatorLastName",
            "$nomineeFirstName $nomineeLastName",
            $nomineeEmail
        );
        
        // Log email sending results
        error_log("Nominator email sent: " . ($nominatorEmailSent ? "Yes" : "No"));
        error_log("Nominee email sent: " . ($nomineeEmailSent ? "Yes" : "No"));
        error_log("Admin email sent: " . ($adminEmailSent ? "Yes" : "No"));
        
        // Return success response
        echo json_encode([
            'success' => true,
            'reference' => $nominationReference,
            'message' => $nominatorEmailSent 
                ? 'Your nomination has been submitted successfully! Please check your email for confirmation.'
                : 'Your nomination has been submitted successfully! Please save your reference number: ' . $nominationReference
        ]);
        
    } catch (Exception $e) {
        error_log("Nomination Error: " . $e->getMessage());
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
} else {
    // Not a POST request
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed. Please submit the form properly.'
    ]);
}

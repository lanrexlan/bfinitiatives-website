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
function handleFileUpload($file, $application_id, $fileType) {
    $upload_dir = 'uploads/applications/' . date('Y') . '/';
    
    // Create directory if it doesn't exist
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $fileInfo = pathinfo($file['name']);
    $extension = strtolower($fileInfo['extension']);
    $newFilename = $application_id . '_' . $fileType . '.' . $extension;
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

// Function to check if email already exists in applications
function isEmailAlreadyUsed($db_connection, $email) {
    $query = "SELECT COUNT(*) FROM talent_applications WHERE email = $1";
    $result = pg_query_params($db_connection, $query, array($email));
    
    if (!$result) {
        error_log("Email check error: " . pg_last_error($db_connection));
        return false; // Assume no duplicate if query fails
    }
    
    $row = pg_fetch_row($result);
    return (int)$row[0] > 0; // Return true if count > 0
}

// Function to send application confirmation email
function sendApplicationEmail($userEmail, $firstName, $applicationReference) {
    $mail = new PHPMailer(true);
    
    try {
        bfi_configure_mailer($mail, [
            'debug' => 3,
            'from_name' => 'BFI Initiatives',
            'reply_to_email' => bfi_admin_email(),
            'reply_to_name' => 'BFI Initiatives',
        ]);
        $mail->addAddress($userEmail, $firstName);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'BFI Talent of the Year - Application Received';        
        
        // Improved HTML email template
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
            <h1 style="color: #2c5282; font-size: 24px; margin-bottom: 10px;">Application Confirmation</h1>
        </div>

        <p style="margin-bottom: 20px;">Dear {$firstName},</p>

        <p style="margin-bottom: 20px;">Thank you for submitting your application for the BFI Talent of the Year Award. We have received your application successfully.</p>

        <div style="background-color: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 5px;">
            <p style="margin: 0; font-weight: bold;">Your Application Details:</p>
            <p style="margin: 10px 0;">Application Reference: <strong>{$applicationReference}</strong></p>
        </div>

        <div style="background-color: #fff3cd; padding: 15px; margin: 20px 0; border-radius: 5px;">
            <p style="margin: 0; color: #856404;"><strong>Important:</strong> Please save your Application Reference. You will need this to track your application status.</p>
        </div>

        <div style="margin: 30px 0;">
            <h2 style="color: #2c5282; font-size: 18px;">What's Next?</h2>
            <ul style="list-style-type: none; padding-left: 0;">
                <li style="margin-bottom: 10px;">✓ Our selection committee will review all applications within 30 days</li>
                <li style="margin-bottom: 10px;">✓ If shortlisted, you'll be contacted for an interview or additional information</li>
                <li style="margin-bottom: 10px;">✓ Final decisions will be announced on our website and via email</li>
            </ul>
        </div>

        <p style="margin: 20px 0;">If you have any questions, please don't hesitate to contact us at <a href="mailto:info@bfinitiatives.com" style="color: #3498db;">info@bfinitiatives.com</a></p>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
            <p style="margin: 0;">Best regards,<br>BFI Award Team</p>
        </div>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
            <p style="font-size: 12px; color: #666; text-align: center;">This is an automated message, please do not reply to this email.</p>
        </div>
    </div>
</body>
</html>
HTML;

        $mail->Body = $emailBody;
        
        // Create plain text version
        $mail->AltBody = "Dear {$firstName},\n\n"
            . "Thank you for submitting your application for the BFI Talent of the Year Award.\n\n"
            . "Your Application Reference: {$applicationReference}\n\n"
            . "Important: Please save this reference for future inquiries.\n\n"
            . "What's Next?\n"
            . "- Our selection committee will review all applications within 30 days\n"
            . "- If shortlisted, you'll be contacted for an interview or additional information\n"
            . "- Final decisions will be announced on our website and via email\n\n"
            . "If you have any questions, please contact us at info@bfinitiatives.com\n\n"
            . "Best regards,\nBFI Award Team";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail Error: " . $e->getMessage());
        return false;
    }
}

// Function to send admin notification email
function sendAdminNotificationEmail($applicationReference, $firstName, $lastName, $email) {
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
        $mail->Subject = "New BFI Talent Application - $applicationReference";
        
        $adminMessage = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
    <div style="max-width: 600px; margin: 0 auto; padding: 20px; background-color: #f9f9f9; border-radius: 10px;">
        <h2 style="color: #2c5282;">New Talent Award Application</h2>
        
        <p>A new application has been submitted for the BFI Talent of the Year Award.</p>
        
        <div style="background-color: #ffffff; padding: 15px; border-radius: 5px; margin: 20px 0;">
            <p><strong>Reference:</strong> {$applicationReference}</p>
            <p><strong>Applicant:</strong> {$firstName} {$lastName}</p>
            <p><strong>Email:</strong> {$email}</p>
            <p><strong>Date Submitted:</strong> {date('Y-m-d H:i:s')}</p>
        </div>
        
        <p>Please log in to the admin portal to review the complete application.</p>
        
        <div style="margin-top: 30px;">
            <p>BFI Talent Application System</p>
        </div>
    </div>
</body>
</html>
HTML;

        $mail->Body = $adminMessage;
        
        // Plain text alternative
        $mail->AltBody = "New Talent Award Application\n\n"
            . "Reference: {$applicationReference}\n"
            . "Applicant: {$firstName} {$lastName}\n"
            . "Email: {$email}\n"
            . "Date Submitted: " . date('Y-m-d H:i:s') . "\n\n"
            . "Please log in to the admin portal to review the complete application.";

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
        // Generate application reference
        $applicationReference = 'BFI-TAL-' . date('Y') . '-' . mt_rand(1000, 9999);
        
        // Personal Information
        $firstName = isset($_POST['firstName']) ? trim($_POST['firstName']) : '';
        $lastName = isset($_POST['lastName']) ? trim($_POST['lastName']) : '';
        $email = isset($_POST['email']) ? trim($_POST['email']) : '';
        $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
        $dob = isset($_POST['dob']) ? trim($_POST['dob']) : '';
        $gender = isset($_POST['gender']) ? trim($_POST['gender']) : '';
        $address = isset($_POST['address']) ? trim($_POST['address']) : '';
        $city = isset($_POST['city']) ? trim($_POST['city']) : '';
        $state = isset($_POST['state']) ? trim($_POST['state']) : '';
        
        // Academic Details
        $institution = isset($_POST['institution']) ? trim($_POST['institution']) : '';
        $degree = isset($_POST['degree']) ? trim($_POST['degree']) : '';
        $fieldOfStudy = isset($_POST['fieldOfStudy']) ? trim($_POST['fieldOfStudy']) : '';
        $yearOfStudy = isset($_POST['yearOfStudy']) ? trim($_POST['yearOfStudy']) : '';
        $gpa = isset($_POST['gpa']) ? trim($_POST['gpa']) : '';
        $expectedGraduationDate = isset($_POST['expectedGraduationDate']) ? trim($_POST['expectedGraduationDate']) : '';
        $previousEducation = isset($_POST['previousEducation']) ? trim($_POST['previousEducation']) : '';
        $academicAchievements = isset($_POST['academicAchievements']) ? trim($_POST['academicAchievements']) : '';
        
        // Achievements & Experience
        $leadershipExperience = isset($_POST['leadershipExperience']) ? trim($_POST['leadershipExperience']) : '';
        $communityService = isset($_POST['communityService']) ? trim($_POST['communityService']) : '';
        $volunteerHours = isset($_POST['volunteerHours']) ? intval($_POST['volunteerHours']) : 0;
        $organizationsInvolved = isset($_POST['organizationsInvolved']) ? intval($_POST['organizationsInvolved']) : 0;
        $workExperience = isset($_POST['workExperience']) ? trim($_POST['workExperience']) : '';
        $innovationProjects = isset($_POST['innovationProjects']) ? trim($_POST['innovationProjects']) : '';
        $personalStatement = isset($_POST['personalStatement']) ? trim($_POST['personalStatement']) : '';
        
        // Validate required fields
        if (empty($firstName) || empty($lastName) || empty($email) || empty($phone) || 
            empty($dob) || empty($gender) || empty($address) || empty($city) || 
            empty($state) || empty($institution) || empty($degree) || 
            empty($fieldOfStudy) || empty($yearOfStudy) || empty($gpa) ||
            empty($academicAchievements) || empty($leadershipExperience) || 
            empty($communityService) || empty($innovationProjects) || 
            empty($personalStatement)) {
            
            throw new Exception('Please fill in all required fields.');
        }
        
        // Validate email
        if (!validateEmail($email)) {
            throw new Exception('Please enter a valid email address.');
        }
        
        // Check if the email already exists in the database
        if (isEmailAlreadyUsed($db_connection, $email)) {
            throw new Exception('An application with this email already exists.');
        }
        
        // Upload files
        $uploadDir = 'uploads/applications/' . date('Y') . '/' . $applicationReference;
        $allowedTypes = ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        
        // Resume (required)
        if (!isset($_FILES['resumeUpload']) || $_FILES['resumeUpload']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Please upload your resume.');
        }
        $resumePath = handleFileUpload($_FILES['resumeUpload'], $applicationReference, 'resume');
        if (!$resumePath) {
            throw new Exception('Failed to upload resume. Please check file format and try again.');
        }
        
        // Transcript (required)
        if (!isset($_FILES['transcriptUpload']) || $_FILES['transcriptUpload']['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('Please upload your academic transcript.');
        }
        $transcriptPath = handleFileUpload($_FILES['transcriptUpload'], $applicationReference, 'transcript');
        if (!$transcriptPath) {
            throw new Exception('Failed to upload transcript. Please check file format and try again.');
        }
        
        // Recommendation letter (optional)
        $recommendationPath = null;
        if (isset($_FILES['recommendationUpload']) && $_FILES['recommendationUpload']['error'] === UPLOAD_ERR_OK) {
            $recommendationPath = handleFileUpload($_FILES['recommendationUpload'], $applicationReference, 'recommendation');
        }
        
        // Additional documents (optional)
        $additionalDocsPath = null;
        if (isset($_FILES['additionalDocsUpload']) && $_FILES['additionalDocsUpload']['error'] === UPLOAD_ERR_OK) {
            $additionalDocsPath = handleFileUpload($_FILES['additionalDocsUpload'], $applicationReference, 'additional');
        }
        
        // Insert data into database
        $query = "INSERT INTO talent_applications (
            application_reference, first_name, last_name, email, phone, dob, gender, 
            address, city, state, institution, degree, field_of_study, year_of_study, 
            gpa, expected_graduation_date, previous_education, academic_achievements, 
            leadership_experience, community_service, volunteer_hours, organizations_involved, 
            work_experience, innovation_projects, personal_statement, resume_path, 
            transcript_path, recommendation_path, additional_docs_path, status, created_at
        ) VALUES (
            $1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16, $17, $18, 
            $19, $20, $21, $22, $23, $24, $25, $26, $27, $28, $29, 'pending', CURRENT_TIMESTAMP
        ) RETURNING id";
        
        $result = pg_query_params(
            $db_connection,
            $query,
            array(
                $applicationReference, $firstName, $lastName, $email, $phone, $dob, $gender, 
                $address, $city, $state, $institution, $degree, $fieldOfStudy, $yearOfStudy, 
                $gpa, $expectedGraduationDate, $previousEducation, $academicAchievements, 
                $leadershipExperience, $communityService, $volunteerHours, $organizationsInvolved, 
                $workExperience, $innovationProjects, $personalStatement, $resumePath, 
                $transcriptPath, $recommendationPath, $additionalDocsPath
            )
        );
        
        if (!$result) {
            throw new Exception(pg_last_error($db_connection));
        }
        
        // Send confirmation email to applicant
        $emailSent = sendApplicationEmail($email, $firstName, $applicationReference);
        
        // Send notification to admin
        $adminEmailSent = sendAdminNotificationEmail($applicationReference, $firstName, $lastName, $email);
        
        // Log email sending results
        error_log("Applicant email sent: " . ($emailSent ? "Yes" : "No"));
        error_log("Admin email sent: " . ($adminEmailSent ? "Yes" : "No"));
        
        // Return success response
        echo json_encode([
            'success' => true,
            'reference' => $applicationReference,
            'message' => $emailSent 
                ? 'Your application has been submitted successfully! Please check your email for confirmation.'
                : 'Your application has been submitted successfully! Please save your reference number: ' . $applicationReference
        ]);
        
    } catch (Exception $e) {
        error_log("Application Error: " . $e->getMessage());
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

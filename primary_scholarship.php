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

$error = null;
$success = null;

// Check if applications are currently open
try {
    $status_query = "SELECT setting_value FROM system_settings WHERE setting_key = 'primary_application_status' LIMIT 1";
    $status_result = pg_query($db_connection, $status_query);
    
    if ($status_result) {
        $status_row = pg_fetch_assoc($status_result);
        $application_status = $status_row ? $status_row['setting_value'] : 'open'; // Default to open if not found
        
        if ($application_status === 'closed') {
            // Get opening date if available
            $date_query = "SELECT setting_value FROM system_settings WHERE setting_key = 'primary_opening_date' LIMIT 1";
            $date_result = pg_query($db_connection, $date_query);
            $date_row = pg_fetch_assoc($date_result);
            $opening_date = $date_row ? $date_row['setting_value'] : '';
            
            // Applications are closed
            $closed_message = "Applications are currently closed";
            if (!empty($opening_date)) {
                $closed_message .= ". Applications will open on " . date('F j, Y \a\t g:i A', strtotime($opening_date)) . " WAT.";
            } else {
                $closed_message .= ". Please check back later.";
            }
        } elseif ($application_status === 'maintenance') {
            // System is in maintenance mode
            $closed_message = "The application system is currently under maintenance. Please check back later.";
        }
    }
} catch (Exception $e) {
    error_log("Error checking application status: " . $e->getMessage());
    // Continue with default behavior (applications open) if we can't check
}

$error = null;
$success = null;

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
    $upload_dir = 'uploads/';
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

// Function to validate phone number
function validatePhone($phone) {
    return preg_match('/^[0-9+\-\(\)\s]{10,20}$/', $phone);
}

// Function to check if email already exists in applications
function isEmailAlreadyUsed($db_connection, $email) {
    $query = "SELECT COUNT(*) FROM primary_scholarship_applications WHERE email = $1";
    $result = pg_query_params($db_connection, $query, array($email));
    
    if (!$result) {
        error_log("Email check error: " . pg_last_error($db_connection));
        return false; // Assume no duplicate if query fails
    }
    
    $row = pg_fetch_row($result);
    return (int)$row[0] > 0; // Return true if count > 0
}

function sendApplicationEmail($userEmail, $firstName, $applicationId, $tempPassword) {
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
        $mail->Subject = 'BFI Primary School Scholarship Application Confirmation';        
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
            <h1 style="color: #2c3e50; font-size: 24px; margin-bottom: 10px;">Application Confirmation</h1>
        </div>

        <p style="margin-bottom: 20px;">Dear {$firstName},</p>

        <p style="margin-bottom: 20px;">Thank you for submitting your application for the BFI Primary School Scholarship Program. We have received your application successfully.</p>

        <div style="background-color: #f8f9fa; padding: 15px; margin: 20px 0; border-radius: 5px;">
            <p style="margin: 0; font-weight: bold;">Your Application Details:</p>
            <p style="margin: 10px 0;">Application ID: <strong>{$applicationId}</strong></p>
            <p style="margin: 10px 0;">Temporary Password: <strong>{$tempPassword}</strong></p>
        </div>

        <div style="background-color: #fff3cd; padding: 15px; margin: 20px 0; border-radius: 5px;">
            <p style="margin: 0; color: #856404;"><strong>Important:</strong> Please save your Application ID and Password. You will need these to track your application status.</p>
        </div>

        <div style="margin: 30px 0;">
            <h2 style="color: #2c3e50; font-size: 18px;">What's Next?</h2>
            <ul style="list-style-type: none; padding-left: 0;">
                <li style="margin-bottom: 10px;">✓ Our team will review your application</li>
                <li style="margin-bottom: 10px;">✓ You will receive updates about your application status via email</li>
                <li style="margin-bottom: 10px;">✓ If additional information is needed, we will contact you</li>
            </ul>
        </div>

        <p style="margin: 20px 0;">If you have any questions, please don't hesitate to contact us at <a href="mailto:info@bfinitiatives.com" style="color: #3498db;">info@bfinitiatives.com</a></p>

        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
            <p style="margin: 0;">Best regards,<br>BFI Team</p>
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
            . "Thank you for submitting your application for the BFI Primary School Scholarship Program.\n\n"
            . "Your Application ID: {$applicationId}\n"
            . "Your Temporary Password: {$tempPassword}\n\n"
            . "Important: Please save these details for future reference.\n\n"
            . "What's Next?\n"
            . "- Our team will review your application\n"
            . "- You will receive updates about your application status via email\n"
            . "- If additional information is needed, we will contact you\n\n"
            . "If you have any questions, please contact us at info@bfinitiatives.com\n\n"
            . "Best regards,\nBFI Team";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mail Error: " . $e->getMessage());
        return false;
    }
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Check if email already exists
        if (isset($_POST['email']) && !empty($_POST['email'])) {
            $email = trim($_POST['email']);
            
            // Validate email format
            if (!validateEmail($email)) {
                throw new Exception("Invalid email format");
            }
            
            // Check if email already used
            if (isEmailAlreadyUsed($db_connection, $email)) {
                throw new Exception("This email has already been used for an application. Each applicant can only submit one application.");
            }
        }
        
        // Generate unique application ID
        $application_id = 'BFI-PRIMARY-' . date('Y') . mt_rand(1000, 9999);
        
        // Generate temporary password
        $temp_password = bin2hex(random_bytes(4));
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

        // Validate required fields
        $required_fields = [
            'first_name',
            'last_name',
            'email',
            'parent_name',
            'parent_phone',
            'school_name',
            'grade_level',
            'academic_achievements',
            'extracurricular_activities'
        ];

        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Please fill in all required fields. Missing: " . str_replace('_', ' ', $field));
            }
        }

        // Validate phone format
        if (!validatePhone($_POST['parent_phone'])) {
            throw new Exception("Invalid phone number format");
        }

        // Initialize file paths
        $report_card_file = null;
        $recommendation_letter_file = null;

        // Validate and upload files
        if (isset($_FILES['report_card']) && $_FILES['report_card']['error'] != UPLOAD_ERR_NO_FILE) {
            $fileError = validateFile($_FILES['report_card'], ['pdf']);
            if ($fileError) throw new Exception($fileError);
            $report_card_file = handleFileUpload($_FILES['report_card'], $application_id, 'report_card');
        }

        if (isset($_FILES['recommendation_letter']) && $_FILES['recommendation_letter']['error'] != UPLOAD_ERR_NO_FILE) {
            $fileError = validateFile($_FILES['recommendation_letter']);
            if ($fileError) throw new Exception($fileError);
            $recommendation_letter_file = handleFileUpload($_FILES['recommendation_letter'], $application_id, 'recommendation');
        }

        // Convert interests array to PostgreSQL array format if provided
        $interests = isset($_POST['interests']) ? 
            '{' . implode(',', array_map(function($value) use ($db_connection) {
                return pg_escape_string($db_connection, $value);
            }, $_POST['interests'])) . '}' : 
            null;

        // Prepare the query
        $query = "INSERT INTO primary_scholarship_applications (
            application_id,
            password,
            first_name,
            last_name,
            date_of_birth,
            email,
            parent_name,
            parent_phone,
            address,
            school_name,
            grade_level,
            academic_achievements,
            extracurricular_activities,
            interests,
            why_deserve_scholarship,
            financial_need_statement,
            report_card_file,
            recommendation_letter_file,
            additional_information,
            how_did_you_hear,
            status,
            created_at
        ) VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, $13, $14, $15, $16, $17, $18, $19, $20, 'pending', CURRENT_TIMESTAMP)";

        $result = pg_query_params(
            $db_connection,
            $query,
            array(
                $application_id,
                $hashed_password,
                $_POST['first_name'],
                $_POST['last_name'],
                $_POST['date_of_birth'] ?? null,
                $_POST['email'],
                $_POST['parent_name'],
                $_POST['parent_phone'],
                $_POST['address'] ?? null,
                $_POST['school_name'],
                $_POST['grade_level'],
                $_POST['academic_achievements'],
                $_POST['extracurricular_activities'],
                $interests,
                $_POST['why_deserve_scholarship'] ?? null,
                $_POST['financial_need_statement'] ?? null,
                $report_card_file,
                $recommendation_letter_file,
                $_POST['additional_information'] ?? null,
                $_POST['how_did_you_hear'] ?? null
            )
        );

        if (!$result) {
            throw new Exception(pg_last_error($db_connection));
        }

        // Send confirmation email
        $emailSent = sendApplicationEmail(
            $_POST['email'],
            $_POST['first_name'],
            $application_id,
            $temp_password
        );
        
        // Store success message and redirect
        $_SESSION['message'] = $emailSent 
            ? "Application submitted successfully! Please check your email for application details."
            : "Application submitted successfully! There was an issue sending the confirmation email. Please save your application ID: " . $application_id;

        header("Location: success.php");
        exit();

    } catch (Exception $e) {
        $_SESSION['error'] = "An error occurred: " . $e->getMessage();
        error_log("Application error: " . $e->getMessage());
        header("Location: primary_scholarship.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/Images/bfi-new-logo.svg">
    <link rel="shortcut icon" href="/Images/bfi-new-logo.svg">
    <title>BFI Primary School Scholarship Application | Bold Footprint Initiatives</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root {
            --primary: #2c5282;
            --primary-light: #3b82f6;
            --primary-dark: #1e3a8a;
            --secondary: #f59e0b;
            --light: #f8fafc;
            --dark: #1e293b;
            --success: #10b981;
            --success-light: #d1fae5;
            --success-dark: #059669;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --gray: #64748b;
            --gray-light: #e2e8f0;
            --border-radius: 15px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            color: var(--dark);
            line-height: 1.6;
            overflow-x: hidden;
            background-color: var(--light);
        }
        
        h1, h2, h3, h4, h5 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            line-height: 1.3;
        }
        
        img {
            max-width: 100%;
        }
        
        a {
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
        }
        
        ul {
            list-style: none;
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 28px;
            border-radius: 30px;
            font-weight: 600;
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background-color: transparent;
            color: var(--primary);
        }
        
        .btn-success {
            background-color: var(--success);
            color: white;
            border-color: var(--success);
        }
        
        .btn-success:hover {
            background-color: transparent;
            color: var(--success);
        }
        
        .btn-outlined {
            background-color: transparent;
            color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-outlined:hover {
            background-color: var(--primary);
            color: white;
        }
        
        /* Header */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            transition: all 0.4s ease;
            background-color: white;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
        }
        
        .nav-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 1001;
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            background-color: white;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-size: 20px;
        }
        
        .logo-text {
            font-weight: 700;
            font-size: 22px;
            color: var(--primary);
        }
        
        .nav-menu {
            display: flex;
            gap: 30px;
        }
        
        .nav-link {
            font-weight: 500;
            position: relative;
            padding: 5px 0;
            color: var(--dark);
        }
        
        .nav-link:hover {
            color: var(--secondary);
        }
        
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background-color: var(--secondary);
            transition: width 0.3s ease;
        }
        
        .nav-link:hover::after {
            width: 100%;
        }
        
        .nav-link.active {
            color: var(--secondary);
        }
        
        .nav-link.active::after {
            width: 100%;
        }
        
        .menu-toggle {
            display: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--primary);
            z-index: 1001;
        }
        
        /* Mobile Menu */
        .mobile-menu-panel {
            position: fixed;
            top: 0;
            right: -100%;
            width: 300px;
            height: 100vh;
            background-color: white;
            z-index: 1000;
            padding: 80px 40px 40px;
            transition: right 0.4s ease;
            box-shadow: -10px 0 30px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
        }
        
        .mobile-menu-panel.active {
            right: 0;
        }
        
        .mobile-menu {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .mobile-link {
            font-weight: 500;
            color: var(--dark);
            padding: 10px 0;
            border-bottom: 1px solid var(--gray-light);
            display: block;
        }
        
        .mobile-link:hover {
            color: var(--primary);
        }
        
        .mobile-link.active {
            color: var(--primary);
        }
        
        .mobile-buttons {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 30px;
        }
        
        .menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.4s ease;
        }
        
        .menu-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        /* Page Hero */
        .page-hero {
            background: linear-gradient(135deg, rgba(44, 82, 130, 0.9), rgba(30, 58, 138, 0.85)), url('/Images/scholarship-bg.jpeg');
            min-height: 30vh;
            display: flex;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            align-items: center;
            position: relative;
            overflow: hidden;
            margin-bottom: 60px;
        }
        
        .page-hero-content {
            color: white;
            position: relative;
            z-index: 2;
            padding: 120px 0 60px;
            text-align: center;
        }
        
        .page-hero-title {
            font-size: 2.5rem;
            margin-bottom: 20px;
            animation: fadeInUp 1s ease;
        }
        
        .page-hero-subtitle {
            font-size: 1.1rem;
            max-width: 700px;
            margin: 0 auto;
            opacity: 0;
            animation: fadeInUp 1s ease 0.3s forwards;
        }
        
        .page-hero-shape {
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 150px;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="white" fill-opacity="1" d="M0,160L48,149.3C96,139,192,117,288,122.7C384,128,480,160,576,170.7C672,181,768,171,864,154.7C960,139,1056,117,1152,117.3C1248,117,1344,139,1392,149.3L1440,160L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>');
            background-size: cover;
            z-index: 1;
        }
        
        /* Application Form Section */
        .application-section {
            padding: 0 0 80px;
        }
        
        .form-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            padding: 40px;
            margin-bottom: 30px;
            transition: all 0.3s ease;
            animation: fadeInUp 1s ease;
        }
        
        .form-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .form-card h3 {
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            font-size: 1.5rem;
            color: var(--primary);
        }
        
        .form-card h3 i {
            margin-right: 15px;
            font-size: 1.2rem;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(44, 82, 130, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Progress bar */
        .progress-container {
            margin-bottom: 30px;
        }
        
        .progress-bar {
            height: 8px;
            background-color: var(--gray-light);
            border-radius: 10px;
            overflow: hidden;
        }
        
        .progress-bar-inner {
            height: 100%;
            background: linear-gradient(to right, var(--primary), var(--primary-light));
            border-radius: 10px;
            transition: width 0.5s ease;
            position: relative;
        }
        
        .progress-bar-inner::after {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: linear-gradient(45deg, 
                rgba(255, 255, 255, 0.2) 25%, 
                transparent 25%, 
                transparent 50%, 
                rgba(255, 255, 255, 0.2) 50%, 
                rgba(255, 255, 255, 0.2) 75%, 
                transparent 75%, 
                transparent);
            background-size: 20px 20px;
            animation: progressAnimation 2s linear infinite;
        }
        
        @keyframes progressAnimation {
            0% {
                background-position: 0 0;
            }
            100% {
                background-position: 20px 0;
            }
        }
        
        .progress-details {
            display: flex;
            justify-content: space-between;
            margin-top: 8px;
            font-size: 0.9rem;
            color: var(--gray);
        }
        
        .progress-percentage {
            font-weight: 600;
            color: var(--primary);
        }
        
        /* Steps Navigation */
        .steps-nav {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 30px;
            justify-content: center;
        }
        
        .step-button {
            padding: 12px 20px;
            border-radius: 30px;
            border: none;
            background-color: var(--gray-light);
            color: var(--dark);
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            font-family: 'Poppins', sans-serif;
        }
        
        .step-button.active {
            background-color: var(--primary);
            color: white;
        }
        
        .step-button.completed {
            background-color: var(--success-light);
            color: var(--success-dark);
        }
        
        .step-button span {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background-color: rgba(255, 255, 255, 0.3);
            margin-right: 10px;
            font-size: 0.8rem;
        }
        
        /* Form styles */
        .form-step {
            display: none;
            animation: fadeInUp 0.5s ease;
        }
        
        .form-step.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
        }
        
        .form-label.required::after {
            content: "*";
            color: var(--danger);
            margin-left: 5px;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--gray-light);
            border-radius: 10px;
            transition: all 0.3s ease;
            font-family: 'Poppins', sans-serif;
            font-size: 1rem;
        }
        
        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
            outline: none;
        }
        
        .form-control.is-invalid {
            border-color: var(--danger);
        }
        
        select.form-control {
            appearance: none;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="%2364748b" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"></polyline></svg>');
            background-repeat: no-repeat;
            background-position: right 15px center;
            background-size: 16px;
            padding-right: 40px;
        }
        
        textarea.form-control {
            min-height: 120px;
            resize: vertical;
        }
        
        .invalid-feedback {
            color: var(--danger);
            font-size: 0.85rem;
            margin-top: 5px;
            display: none;
        }
        
        .form-control.is-invalid + .invalid-feedback {
            display: block;
        }
        
        .form-footer {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        /* File upload styling */
        .file-upload {
            margin-bottom: 25px;
        }
        
        .file-drop-area {
            border: 2px dashed var(--gray-light);
            border-radius: var(--border-radius);
            padding: 30px 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background-color: rgba(248, 250, 252, 0.8);
        }
        
        .file-drop-area:hover, .file-drop-area.dragging {
            border-color: var(--primary);
            background-color: rgba(59, 130, 246, 0.05);
        }
        
        .file-icon {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 15px;
        }
        
        .file-message {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--dark);
        }
        
        .file-hint {
            font-size: 0.85rem;
            color: var(--gray);
        }
        
        .file-input {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
        }
        
        .file-preview {
            display: none;
            margin-top: 15px;
            padding: 12px 15px;
            border-radius: 10px;
            background-color: rgba(59, 130, 246, 0.1);
        }
        
        .file-preview.active {
            display: flex;
            align-items: center;
        }
        
        .file-preview-icon {
            font-size: 1.5rem;
            color: var(--primary);
            margin-right: 15px;
        }
        
        .file-preview-info {
            flex: 1;
        }
        
        .file-preview-name {
            font-weight: 600;
            margin-bottom: 3px;
            color: var(--dark);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 250px;
        }
        
        .file-preview-size {
            font-size: 0.8rem;
            color: var(--gray);
        }
        
        .file-preview-remove {
            background: none;
            border: none;
            color: var(--danger);
            cursor: pointer;
            font-size: 0.9rem;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .file-preview-remove:hover {
            background-color: rgba(239, 68, 68, 0.1);
        }
        
        /* Word counter */
        .word-counter {
            display: flex;
            justify-content: space-between;
            margin-top: 8px;
            font-size: 0.85rem;
            color: var(--gray);
        }
        
        .word-counter.limit-reached {
            color: var(--danger);
        }
        
        /* Custom checkbox styling */
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .custom-checkbox {
            position: relative;
        }
        
        .custom-checkbox input {
            position: absolute;
            opacity: 0;
        }
        
        .custom-checkbox label {
            display: flex;
            align-items: center;
            padding: 15px;
            border-radius: 10px;
            border: 2px solid var(--gray-light);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .custom-checkbox input:checked + label {
            border-color: var(--primary);
            background-color: rgba(59, 130, 246, 0.05);
        }
        
        .checkbox-custom-icon {
            width: 24px;
            height: 24px;
            border: 2px solid var(--gray-light);
            border-radius: 6px;
            margin-right: 12px;
            position: relative;
            transition: all 0.3s ease;
            flex-shrink: 0;
        }
        
        .custom-checkbox input:checked + label .checkbox-custom-icon {
            background-color: var(--primary);
            border-color: var(--primary);
        }
        
        .checkbox-custom-icon::after {
            content: '';
            position: absolute;
            top: 45%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(45deg) scale(0);
            width: 5px;
            height: 10px;
            border: solid white;
            border-width: 0 2px 2px 0;
            opacity: 0;
            transition: all 0.2s ease;
        }
        
        .custom-checkbox input:checked + label .checkbox-custom-icon::after {
            opacity: 1;
            transform: translate(-50%, -50%) rotate(45deg) scale(1);
        }
        
        /* Alerts */
        .alert {
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            display: flex;
            align-items: flex-start;
            gap: 15px;
        }
        
        .alert-primary {
            background-color: rgba(59, 130, 246, 0.1);
            border-left: 4px solid var(--primary);
            color: var(--primary-dark);
        }
        
        .alert-success {
            background-color: var(--success-light);
            border-left: 4px solid var(--success);
            color: var(--success-dark);
        }
        
        .alert-warning {
            background-color: rgba(245, 158, 11, 0.1);
            border-left: 4px solid var(--warning);
            color: #92400e;
        }
        
        .alert-danger {
            background-color: rgba(239, 68, 68, 0.1);
            border-left: 4px solid var(--danger);
            color: #b91c1c;
        }
        
        .alert-icon {
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .alert-content {
            flex: 1;
        }
        
        .alert-title {
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        /* Form submit button */
        .submit-button {
            display: block;
            width: 100%;
            max-width: 400px;
            margin: 40px auto 20px;
            padding: 15px 30px;
            border-radius: 30px;
            background: linear-gradient(to right, var(--primary), var(--primary-dark));
            color: white;
            font-weight: 700;
            font-size: 1.1rem;
            text-align: center;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 10px 20px rgba(30, 58, 138, 0.2);
            position: relative;
            overflow: hidden;
        }
        
        .submit-button:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 30px rgba(30, 58, 138, 0.3);
        }
        
        .submit-button::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(to right, rgba(255, 255, 255, 0) 0%, rgba(255, 255, 255, 0.3) 50%, rgba(255, 255, 255, 0) 100%);
            transform: translateX(-100%);
            transition: transform 1s ease;
        }
        
        .submit-button:hover::after {
            transform: translateX(100%);
        }
        
        .submit-button i {
            margin-right: 10px;
        }
        
        /* Toast notifications */
        .toast-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1001;
        }
        
        .toast {
            background-color: white;
            color: var(--dark);
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideInRight 0.3s ease forwards;
            max-width: 350px;
        }
        
        .toast.toast-success {
            border-left: 4px solid var(--success);
        }
        
        .toast.toast-error {
            border-left: 4px solid var(--danger);
        }
        
        .toast.toast-info {
            border-left: 4px solid var(--info);
        }
        
        .toast i {
            font-size: 1.2rem;
        }
        
        .toast.toast-success i {
            color: var(--success);
        }
        
        .toast.toast-error i {
            color: var(--danger);
        }
        
        .toast.toast-info i {
            color: var(--info);
        }
        
        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        @keyframes slideOutRight {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }
        
                /* Countdown Timer Styling */
        .countdown-container {
            background: linear-gradient(135deg, rgba(44, 82, 130, 0.05), rgba(30, 58, 138, 0.1));
            border-radius: var(--border-radius);
            padding: 25px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
            border: 1px solid rgba(67, 97, 238, 0.1);
            max-width: 600px;
            margin: 0 auto;
        }
        
        .countdown-timer {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 20px 0;
        }
        
        .countdown-item {
            text-align: center;
            position: relative;
            min-width: 80px;
        }
        
        .countdown-item:not(:last-child)::after {
            content: ':';
            position: absolute;
            right: -10px;
            top: 15px;
            font-size: 2rem;
            font-weight: 600;
            color: var(--primary);
        }
        
        .countdown-value {
            font-size: 2.5rem;
            font-weight: 700;
            line-height: 1;
            color: var(--primary);
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            padding: 15px 10px;
            margin-bottom: 10px;
            position: relative;
            overflow: hidden;
        }
        
        .countdown-value::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: linear-gradient(to right, transparent, rgba(67, 97, 238, 0.2), transparent);
        }
        
        .countdown-label {
            font-size: 0.9rem;
            font-weight: 500;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        #opening-date-text {
            color: var(--primary-dark);
            font-weight: 500;
        }
        
        @media (max-width: 576px) {
    .countdown-container {
        width: 100%;
        max-width: 100%;
        margin: 0 auto;
        padding: 20px;
    }

    .countdown-timer {
        gap: 10px;
        flex-wrap: wrap;
        justify-content: center;
    }

    .countdown-item {
        min-width: 60px;
    }

    .countdown-value {
        font-size: 1.8rem;
        padding: 10px 5px;
    }

    .countdown-item:not(:last-child)::after {
        right: -8px;
        top: 10px;
        font-size: 1.5rem;
    }

    .countdown-label {
        font-size: 0.75rem;
    }
}
        
        /* Footer */
        .footer{
            background: linear-gradient(rgba(0, 0, 0, 0.95), rgba(0, 0, 0, 0.95)), url('/Images/footer-bg.jpeg');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 60px 0 20px;
        }
        
        .footer-content {
            display: flex;
            flex-wrap: wrap;
            gap: 40px;
            margin-bottom: 60px;
        }
        
        .footer-logo {
            flex: 1;
            min-width: 250px;
        }
        
        .footer-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .footer-brand-icon {
            width: 40px;
            height: 40px;
            background-color: white;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-size: 20px;
        }
        
        .footer-brand-text {
            font-weight: 700;
            font-size: 1.5rem;
            color: white;
        }
        
        .footer-slogan {
            margin-bottom: 20px;
            font-weight: 300;
        }
        
        .footer-socials {
            display: flex;
            gap: 15px;
        }
        
        .social-link {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .social-link:hover {
            background: var(--secondary);
            color: var(--dark);
            transform: translateY(-5px);
        }
        
        .footer-links {
            flex: 1;
            min-width: 150px;
        }
        
        .footer-heading {
            font-size: 1.2rem;
            margin-bottom: 20px;
            position: relative;
            display: inline-block;
        }
        
        .footer-heading::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 30px;
            height: 2px;
            background: var(--secondary);
        }
        
        .footer-nav {
            list-style: none;
        }
        
        .footer-nav li {
            margin-bottom: 10px;
        }
        
        .footer-nav a {
            color: rgba(255, 255, 255, 0.7);
            transition: all 0.3s ease;
        }
        
        .footer-nav a:hover {
            color: var(--secondary);
            padding-left: 5px;
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
        }
        
        /* Animations */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        
        /* Grid Layout */
        .row {
            display: flex;
            flex-wrap: wrap;
            margin: 0 -15px;
        }
        
        .col-md-6 {
            flex: 0 0 50%;
            max-width: 50%;
            padding: 0 15px;
        }
        
        /* Responsive Styles */
        @media (max-width: 992px) {
            .menu-toggle {
                display: block;
            }
            
            .nav-menu {
                display: none;
            }
            
            .page-hero-title {
                font-size: 2rem;
            }
            
            .steps-nav {
                flex-direction: column;
                width: 100%;
                max-width: 300px;
                margin-left: auto;
                margin-right: auto;
            }
            
            .col-md-6 {
                flex: 0 0 100%;
                max-width: 100%;
            }
            
            .checkbox-group {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .nav-wrapper {
                padding: 15px 0;
            }
            
            .page-hero {
                min-height: 55vh;
            }
            
            .page-hero-title {
                font-size: 1.8rem;
            }
            
            .page-hero-subtitle {
                font-size: 1rem;
            }
            
            .form-card {
                padding: 25px;
            }
            
            .form-footer {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
            
            .footer-content {
                flex-direction: column;
                gap: 30px;
            }
        }
        
        @media (max-width: 576px) {
            .logo-text {
                font-size: 18px;
            }
            
            .page-hero-title {
                font-size: 1.5rem;
            }
            
            .file-preview-name {
                max-width: 150px;
            }
        }

        @media (max-width: 900px) {
            .page-hero-grid,
            .application-intro-grid,
            .application-split,
            .application-hero-card,
            .hero-metric-list,
            .hero-chip-row,
            .form-grid,
            .detail-grid {
                grid-template-columns: 1fr !important;
            }

            .page-hero-content,
            .application-hero-card,
            .form-card,
            .application-aside-card,
            .hero-metric,
            .btn,
            .button,
            button[type="submit"] {
                width: 100%;
                max-width: 100%;
                min-width: 0;
            }

            .page-hero-title,
            .hero-card-label,
            .hero-metric strong,
            .hero-metric span {
                overflow-wrap: anywhere;
                word-break: break-word;
            }
        }

        @media (max-width: 420px) {
            .page-hero-title {
                font-size: clamp(1.8rem, 8.8vw, 2.2rem) !important;
                line-height: 1.2;
            }

            .page-hero-subtitle,
            p,
            li {
                font-size: 15px;
                line-height: 1.65;
            }
        }

    </style>
    <link rel="stylesheet" href="site.css">
    <link rel="stylesheet" href="application-theme.css">
    <script defer src="site.js"></script>
</head>
<body>
    <nav class="nav" data-nav>
        <div class="container">
            <div class="nav-inner">
                <a href="index.html" class="brand">
                    <div class="brand-mark">
                        <img src="/Images/bfi-new-logo.svg" alt="Bold Footprint Initiatives logo">
                    </div>
                    <div class="brand-copy">Bold Footprint<span>Initiatives</span></div>
                </a>
                <ul class="nav-links">
                    <li><a href="about.html">About</a></li>
                    <li><a href="programs.html">Programs</a></li>
                    <li><a href="stories.html">Stories</a></li>
                    <li><a href="achievements.html">Achievements</a></li>
                    <li><a href="talent.html">Talent</a></li>
                    <li><a href="mentor.html">Mentor</a></li>
                </ul>
                <div class="nav-actions">
                    <a href="apply.html" class="nav-link-muted">Application tracks</a>
                    <a href="check-status.php" class="btn btn-primary">Check status</a>
                </div>
                <button class="nav-toggle" data-nav-toggle aria-expanded="false" aria-label="Open navigation"><span></span><span></span><span></span></button>
            </div>
            <div class="mobile-menu" data-mobile-menu>
                <a href="about.html">About</a>
                <a href="programs.html">Programs</a>
                <a href="stories.html">Stories</a>
                <a href="achievements.html">Achievements</a>
                <a href="talent.html">Talent</a>
                <a href="mentor.html">Mentor</a>
                <a href="apply.html">Apply</a>
                <a href="support.html">Support</a>
                <a href="contact.html">Contact</a>
                <a href="check-status.php">Check status</a>
            </div>
        </div>
    </nav>

    <!-- Page Hero Section -->
    <section class="page-hero application-track-primary">
        <div class="container page-hero-grid">
            <div class="page-hero-content">
                <div class="hero-kicker">Primary Track</div>
                <h1 class="page-hero-title">BFI Primary School Scholarship Program</h1>
                <p class="page-hero-subtitle">Supporting young minds with comprehensive educational funding to build strong foundations for their future academic journey.</p>
                <div class="hero-chip-row">
                    <span class="hero-chip">Grades 1-6</span>
                    <span class="hero-chip">Parent-supported application</span>
                    <span class="hero-chip">Tuition + learning essentials</span>
                </div>
            </div>
            <aside class="application-hero-card">
                <div class="application-hero-card-head">
                    <div class="application-hero-icon"><i class="fas fa-seedling"></i></div>
                    <div>
                        <span class="hero-card-label">At a glance</span>
                        <h3>Foundation years matter.</h3>
                    </div>
                </div>
                <div class="hero-metric-list">
                    <div class="hero-metric"><span>Support focus</span><strong>Fees, books, and steady learning support</strong></div>
                    <div class="hero-metric"><span>Best for</span><strong>Promising pupils whose progress needs a lift</strong></div>
                    <div class="hero-metric"><span>Prepare</span><strong>Parent details, school record, and context</strong></div>
                </div>
            </aside>
        </div>
        <div class="page-hero-shape"></div>
    </section>

    <!-- Toast Container for Notifications -->
    <div class="toast-container" id="toastContainer"></div>

    <!-- Application Section -->
    <section class="application-section">
        <div class="container">
            <!-- Introduction Card -->
            <div class="application-intro-grid">
                <div class="form-card application-overview-card">
                    <h3><i class="fas fa-lightbulb"></i> Program Overview</h3>
                    <p>Welcome to the Bold Footprint Initiatives (BFI) Primary School Scholarship Program application portal. This opportunity is designed for promising young students in grades 1-6 who demonstrate academic potential and a passion for learning.</p>
                    <p>Our primary school scholarships provide financial support for educational expenses including tuition, books, and school supplies. Selected scholars also receive academic guidance and access to enrichment activities to help them excel in their studies.</p>
                    <p>If you have a child who shows dedication to their education and could benefit from additional support, we encourage you to complete the application below.</p>
                </div>
                <aside class="application-aside-card">
                    <span class="hero-card-label">Before you start</span>
                    <h3>Prepare a thoughtful first application.</h3>
                    <p>The strongest submissions help us understand both need and promise from the very beginning.</p>
                    <ul class="application-note-list">
                        <li>Recent report card or academic record</li>
                        <li>Parent or guardian contact information</li>
                        <li>Clear context around financial need</li>
                        <li>Any academic or extracurricular highlights</li>
                    </ul>
                    <div class="application-mini-panel">
                        <strong>What we look for</strong>
                        <p>Promise, consistency, home support, and a clear case that BFI support can change the learner's trajectory early.</p>
                    </div>
                </aside>
            </div>

            <?php if (isset($success)): ?>
            <!-- Success Alert -->
            <div class="alert alert-success">
                <div class="alert-icon">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="alert-content">
                    <div class="alert-title">Application Submitted Successfully!</div>
                    <p><?php echo htmlspecialchars($success); ?></p>
                    <div class="mt-4">
                        <a href="check-status.php" class="btn btn-success">
                            <i class="fas fa-search me-2"></i>Check Application Status
                        </a>
                    </div>
                </div>
            </div>
            
            <?php elseif (isset($closed_message)): ?>
<!-- Applications Closed Alert -->
<div class="alert alert-warning">
    <div class="alert-icon">
        <i class="fas fa-clock"></i>
    </div>
    <div class="alert-content">
        <div class="alert-title">Applications Not Available</div>
        <p><?php echo htmlspecialchars($closed_message); ?></p>
        
        <?php if (isset($show_countdown) && $show_countdown): ?>
        <div class="countdown-container mt-4">
            <h4 class="text-center mb-3">Applications Open In:</h4>
            <div class="countdown-timer">
                <div class="countdown-item">
                    <div class="countdown-value" id="countdown-days">00</div>
                    <div class="countdown-label">Days</div>
                </div>
                <div class="countdown-item">
                    <div class="countdown-value" id="countdown-hours">00</div>
                    <div class="countdown-label">Hours</div>
                </div>
                <div class="countdown-item">
                    <div class="countdown-value" id="countdown-minutes">00</div>
                    <div class="countdown-label">Minutes</div>
                </div>
                <div class="countdown-item">
                    <div class="countdown-value" id="countdown-seconds">00</div>
                    <div class="countdown-label">Seconds</div>
                </div>
            </div>
            <p class="text-center mt-3" id="opening-date-text">Mark your calendar: Applications open on <?php echo date('F j, Y \a\t g:i A', strtotime($opening_date)); ?> WAT</p>
        </div>
        
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set the opening date (from PHP variable)
            const openingDate = new Date('<?php echo $opening_date; ?>').getTime();
            
            // Update the countdown every second
            const countdownTimer = setInterval(function() {
                // Get current date and time
                const now = new Date().getTime();
                
                // Find the time difference between now and the opening date
                const timeDifference = openingDate - now;
                
                // Time calculations for days, hours, minutes and seconds
                const days = Math.floor(timeDifference / (1000 * 60 * 60 * 24));
                const hours = Math.floor((timeDifference % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
                const minutes = Math.floor((timeDifference % (1000 * 60 * 60)) / (1000 * 60));
                const seconds = Math.floor((timeDifference % (1000 * 60)) / 1000);
                
                // Display the results
                document.getElementById('countdown-days').textContent = days.toString().padStart(2, '0');
                document.getElementById('countdown-hours').textContent = hours.toString().padStart(2, '0');
                document.getElementById('countdown-minutes').textContent = minutes.toString().padStart(2, '0');
                document.getElementById('countdown-seconds').textContent = seconds.toString().padStart(2, '0');
                
                // If the countdown is over, show a message
                if (timeDifference < 0) {
                    clearInterval(countdownTimer);
                    document.querySelector('.countdown-container').innerHTML = '<div class="alert alert-success">Applications are now open! Please refresh the page to apply.</div>';
                }
            }, 1000);
        });
        </script>
        <?php endif; ?>
        
        <div class="mt-4">
            <a href="index.html" class="btn btn-primary">
                <i class="fas fa-home me-2"></i>Return to Home
            </a>
        </div>
    </div>
</div>
            <?php else: ?>
            <!-- Progress Container -->
            <div class="progress-container">
                <div class="progress-bar">
                    <div class="progress-bar-inner" id="progressBar" style="width: 0%"></div>
                </div>
                <div class="progress-details">
                    <span id="progressStep">Step 1 of 4</span>
                    <span class="progress-percentage" id="progressPercent">0%</span>
                </div>
            </div>

            <!-- Step Navigation -->
            <div class="steps-nav">
                <button type="button" class="step-button active" data-step="1">
                    <span>1</span> Student Info
                </button>
                <button type="button" class="step-button" data-step="2">
                    <span>2</span> Parent/Guardian
                </button>
                <button type="button" class="step-button" data-step="3">
                    <span>3</span> Academic Info
                </button>
                <button type="button" class="step-button" data-step="4">
                    <span>4</span> Additional Info
                </button>
            </div>

            <!-- Application Form -->
            <form method="POST" action="" enctype="multipart/form-data" id="applicationForm">
                <!-- Step 1: Student Information -->
                <div class="form-step active" id="step1">
                    <div class="form-card">
                        <h3><i class="fas fa-user-graduate"></i> Student Information</h3>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="first_name" class="form-label required">First Name</label>
                                    <input type="text" id="first_name" name="first_name" class="form-control" required>
                                    <div class="invalid-feedback">Please enter the student's first name</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="last_name" class="form-label required">Last Name</label>
                                    <input type="text" id="last_name" name="last_name" class="form-control" required>
                                    <div class="invalid-feedback">Please enter the student's last name</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="date_of_birth" class="form-label required">Date of Birth</label>
                                    <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" required>
                                    <div class="invalid-feedback">Please enter the student's date of birth</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email" class="form-label required">Email Address</label>
                                    <input type="email" id="email" name="email" class="form-control" required>
                                    <div class="invalid-feedback" id="email-feedback">Please enter a valid email address</div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="address" class="form-label">Home Address</label>
                            <textarea id="address" name="address" class="form-control" rows="3" placeholder="Complete home address"></textarea>
                        </div>
                        
                        <div class="alert alert-primary">
                            <div class="alert-icon">
                                <i class="fas fa-info-circle"></i>
                            </div>
                            <div class="alert-content">
                                <div class="alert-title">Note</div>
                                <p>Please provide the student's information in this section. Parent or guardian information will be collected in the next step.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-footer">
                        <button type="button" class="btn btn-primary next-step">
                            Continue <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Parent/Guardian Information -->
                <div class="form-step" id="step2">
                    <div class="form-card">
                        <h3><i class="fas fa-users"></i> Parent/Guardian Information</h3>
                        
                        <div class="form-group">
                            <label for="parent_name" class="form-label required">Parent/Guardian Full Name</label>
                            <input type="text" id="parent_name" name="parent_name" class="form-control" required>
                            <div class="invalid-feedback">Please enter the parent/guardian's name</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="parent_phone" class="form-label required">Parent/Guardian Phone Number</label>
                            <input type="tel" id="parent_phone" name="parent_phone" class="form-control" required>
                            <div class="invalid-feedback">Please enter a valid phone number</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="financial_need_statement" class="form-label">Financial Need Statement</label>
                            <textarea id="financial_need_statement" name="financial_need_statement" class="form-control" rows="5" placeholder="Please describe your financial situation and how this scholarship would help support your child's education."></textarea>
                            <div class="word-counter">
                                <span id="financial_need_statement_count">0</span>
                                <span class="word-limit">Maximum 300 words</span>
                            </div>
                        </div>
                        
                        <div class="alert alert-warning">
                            <div class="alert-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="alert-content">
                                <div class="alert-title">Important</div>
                                <p>The parent/guardian contact information will be our primary means of communication regarding the scholarship application. Please ensure all details are accurate.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-footer">
                        <button type="button" class="btn btn-outlined prev-step">
                            <i class="fas fa-arrow-left"></i> Previous
                        </button>
                        <button type="button" class="btn btn-primary next-step">
                            Continue <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 3: Academic Information -->
                <div class="form-step" id="step3">
                    <div class="form-card">
                        <h3><i class="fas fa-school"></i> Academic Information</h3>
                        
                        <div class="form-group">
                            <label for="school_name" class="form-label required">Current School Name</label>
                            <input type="text" id="school_name" name="school_name" class="form-control" required>
                            <div class="invalid-feedback">Please enter the school name</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="grade_level" class="form-label required">Current Grade Level</label>
                            <select id="grade_level" name="grade_level" class="form-control" required>
                                <option value="">Select grade level</option>
                                <option value="Grade 1">Grade 1</option>
                                <option value="Grade 2">Grade 2</option>
                                <option value="Grade 3">Grade 3</option>
                                <option value="Grade 4">Grade 4</option>
                                <option value="Grade 5">Grade 5</option>
                                <option value="Grade 6">Grade 6</option>
                            </select>
                            <div class="invalid-feedback">Please select current grade level</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="academic_achievements" class="form-label required">Academic Achievements</label>
                            <textarea id="academic_achievements" name="academic_achievements" class="form-control" rows="4" placeholder="Describe any academic awards, recognition, or achievements the student has received." required></textarea>
                            <div class="invalid-feedback">Please describe academic achievements</div>
                        </div>
                        
                        <div class="form-group">
                            <label for="extracurricular_activities" class="form-label required">Extracurricular Activities</label>
                            <textarea id="extracurricular_activities" name="extracurricular_activities" class="form-control" rows="4" placeholder="List any clubs, sports, music, art, or other activities the student participates in." required></textarea>
                            <div class="invalid-feedback">Please describe extracurricular activities</div>
                        </div>
                        
                        <div class="file-upload">
                            <label class="form-label">Report Card/Academic Records</label>
                            <div class="file-drop-area" id="reportCardDropArea">
                                <i class="fas fa-file-pdf file-icon"></i>
                                <p class="file-message">Drag & drop the most recent report card here or click to browse</p>
                                <p class="file-hint">Accepted format: PDF only (Max 5MB)</p>
                                <input type="file" class="file-input" name="report_card" id="reportCardFile" accept=".pdf">
                            </div>
                            <div class="file-preview" id="reportCardPreview">
                                <i class="fas fa-file-pdf file-preview-icon"></i>
                                <div class="file-preview-info">
                                    <p class="file-preview-name">report_card.pdf
                                    <p class="file-preview-size">Size: 1.2 MB</p>
                                </div>
                                <button type="button" class="file-preview-remove" id="reportCardRemove">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-footer">
                        <button type="button" class="btn btn-outlined prev-step">
                            <i class="fas fa-arrow-left"></i> Previous
                        </button>
                        <button type="button" class="btn btn-primary next-step">
                            Continue <i class="fas fa-arrow-right"></i>
                        </button>
                    </div>
                </div>

                <!-- Step 4: Additional Information -->
                <div class="form-step" id="step4">
                    <div class="form-card">
                        <h3><i class="fas fa-info-circle"></i> Additional Information</h3>
                        
                        <div class="form-group">
                            <label class="form-label">Areas of Interest</label>
                            <p class="form-hint">Select all areas that the student is interested in (optional)</p>
                            
                            <div class="checkbox-group">
                                <div class="custom-checkbox">
                                    <input type="checkbox" name="interests[]" value="math" id="math">
                                    <label for="math">
                                        <span class="checkbox-custom-icon"></span>
                                        <span>Mathematics</span>
                                    </label>
                                </div>
                                
                                <div class="custom-checkbox">
                                    <input type="checkbox" name="interests[]" value="science" id="science">
                                    <label for="science">
                                        <span class="checkbox-custom-icon"></span>
                                        <span>Science</span>
                                    </label>
                                </div>
                                
                                <div class="custom-checkbox">
                                    <input type="checkbox" name="interests[]" value="reading" id="reading">
                                    <label for="reading">
                                        <span class="checkbox-custom-icon"></span>
                                        <span>Reading & Literature</span>
                                    </label>
                                </div>
                                
                                <div class="custom-checkbox">
                                    <input type="checkbox" name="interests[]" value="arts" id="arts">
                                    <label for="arts">
                                        <span class="checkbox-custom-icon"></span>
                                        <span>Arts & Crafts</span>
                                    </label>
                                </div>
                                
                                <div class="custom-checkbox">
                                    <input type="checkbox" name="interests[]" value="music" id="music">
                                    <label for="music">
                                        <span class="checkbox-custom-icon"></span>
                                        <span>Music</span>
                                    </label>
                                </div>
                                
                                <div class="custom-checkbox">
                                    <input type="checkbox" name="interests[]" value="sports" id="sports">
                                    <label for="sports">
                                        <span class="checkbox-custom-icon"></span>
                                        <span>Sports & Physical Activities</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="why_deserve_scholarship" class="form-label">Why Does the Student Deserve This Scholarship?</label>
                            <textarea id="why_deserve_scholarship" name="why_deserve_scholarship" class="form-control" rows="4" placeholder="Please explain why the student should be selected for this scholarship and how it will benefit their education."></textarea>
                            <div class="word-counter">
                                <span id="why_deserve_scholarship_count">0</span>
                                <span class="word-limit">Maximum 300 words</span>
                            </div>
                        </div>
                        
                        <div class="file-upload">
                            <label class="form-label">Teacher Recommendation Letter (Optional)</label>
                            <div class="file-drop-area" id="recommendationDropArea">
                                <i class="fas fa-file-alt file-icon"></i>
                                <p class="file-message">Drag & drop a recommendation letter here or click to browse</p>
                                <p class="file-hint">Accepted formats: PDF, DOC, DOCX (Max 5MB)</p>
                                <input type="file" class="file-input" name="recommendation_letter" id="recommendationFile" accept=".pdf,.doc,.docx">
                            </div>
                            <div class="file-preview" id="recommendationPreview">
                                <i class="fas fa-file-alt file-preview-icon"></i>
                                <div class="file-preview-info">
                                    <p class="file-preview-name">recommendation.pdf</p>
                                    <p class="file-preview-size">Size: 1.2 MB</p>
                                </div>
                                <button type="button" class="file-preview-remove" id="recommendationRemove">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="how_did_you_hear" class="form-label">How did you hear about the BFI Scholarship Program?</label>
                            <select id="how_did_you_hear" name="how_did_you_hear" class="form-control">
                                <option value="">Please select an option</option>
                                <option value="school">From School</option>
                                <option value="social_media">Social Media</option>
                                <option value="website">BFI Website</option>
                                <option value="friend">Friend/Family Referral</option>
                                <option value="community">Community Organization</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="additional_information" class="form-label">Additional Information (Optional)</label>
                            <textarea id="additional_information" name="additional_information" class="form-control" rows="4" placeholder="Is there anything else you would like to share with the selection committee?"></textarea>
                        </div>
                        
                        <div class="alert alert-warning">
                            <div class="alert-icon">
                                <i class="fas fa-exclamation-triangle"></i>
                            </div>
                            <div class="alert-content">
                                <div class="alert-title">Before Submitting</div>
                                <p>Please review all the information you have provided to ensure it is accurate and complete. Once submitted, you cannot edit your application.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-footer">
                        <button type="button" class="btn btn-outlined prev-step">
                            <i class="fas fa-arrow-left"></i> Previous
                        </button>
                        <button type="submit" class="submit-button">
                            <i class="fas fa-paper-plane"></i> Submit Application
                        </button>
                    </div>
                </div>
            </form>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <div class="footer-brand">
                        <div class="footer-brand-text">Bold Footprint Initiatives</div>
                    </div>
                    <p class="footer-slogan">Empowering dreams through education</p>
                    <div class="footer-socials">
                        <a href="https://web.facebook.com/profile.php?id=61574771032448" class="social-link"><i class="fab fa-facebook-f"></i></a>
                        <a href="https://x.com/BFIniatiatives" class="social-link"><i class="fab fa-x-twitter"></i></a>
                        <a href="https://www.instagram.com/bfinitiatives" class="social-link" target="_blank" rel="noopener" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="https://www.linkedin.com/company/bright-future-initiative" class="social-link"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                
                <div class="footer-links">
                    <h4 class="footer-heading">Connect With Us</h4>
                    <ul class="footer-nav">
                        <li><a href="https://web.facebook.com/profile.php?id=61574771032448"><i class="fab fa-facebook-f"></i> Facebook</a></li>
                        <li><a href="https://x.com/BFIniatiatives"><i class="fab fa-x-twitter"></i> Twitter</a></li>
                        <li><a href="#"><i class="fab fa-instagram"></i> Instagram</a></li>
                        <li><a href="https://www.linkedin.com/company/bright-future-initiative"><i class="fab fa-linkedin-in"></i> LinkedIn</a></li>
                    </ul>
                </div>
                
                <div class="footer-links">
                    <h4 class="footer-heading">Quick Links</h4>
                    <ul class="footer-nav">
                        <li><a href="index.html">Home</a></li>
                        <li><a href="about.html">About Us</a></li>
                        <li><a href="programs.html">Our Programs</a></li>
                        <li><a href="talent.html">Talent of the Year</a></li>
                        <li><a href="mentor.html">Become a Mentor</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2026 Bold Footprint Initiatives. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // DOM Elements
            const header = document.getElementById('header');
            const menuToggle = document.getElementById('menuToggle');
            const mobileMenuPanel = document.getElementById('mobileMenuPanel');
            const menuOverlay = document.getElementById('menuOverlay');
            const progressBar = document.getElementById('progressBar');
            const progressPercent = document.getElementById('progressPercent');
            const progressStep = document.getElementById('progressStep');
            const formSteps = document.querySelectorAll('.form-step');
            const stepButtons = document.querySelectorAll('.step-button');
            const nextButtons = document.querySelectorAll('.next-step');
            const prevButtons = document.querySelectorAll('.prev-step');
            const applicationForm = document.getElementById('applicationForm');
            const toastContainer = document.getElementById('toastContainer');
            
            // Current step tracker
            let currentStep = 1;
            const totalSteps = formSteps.length;
            
            // Event Listeners
            if (menuToggle && mobileMenuPanel && menuOverlay) {
                menuToggle.addEventListener('click', toggleMenu);
                menuOverlay.addEventListener('click', closeMenu);
            }
            
            // Event listeners for step buttons
            stepButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const step = parseInt(button.getAttribute('data-step'));
                    if (validateStep(currentStep) || step < currentStep) {
                        goToStep(step);
                    }
                });
            });
            
            // Next button event listeners
            nextButtons.forEach(button => {
                button.addEventListener('click', () => {
                    if (validateStep(currentStep)) {
                        goToStep(currentStep + 1);
                    }
                });
            });
            
            // Previous button event listeners
            prevButtons.forEach(button => {
                button.addEventListener('click', () => {
                    goToStep(currentStep - 1);
                });
            });
            
            // Form Validation
            applicationForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                let isValid = true;
                for (let i = 1; i <= totalSteps; i++) {
                    if (!validateStep(i, false)) {
                        goToStep(i);
                        isValid = false;
                        break;
                    }
                }
                
                if (isValid) {
                    const submitButton = this.querySelector('.submit-button');
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Submitting...';
                    
                    // Remove this line for real form submission
                    showToast('Form is being submitted...', 'info');
                    
                    // Submit the form
                    this.submit();
                }
            });
            
            // File upload functionality
            setupFileUpload('reportCardFile', 'reportCardDropArea', 'reportCardPreview', 'reportCardRemove');
            setupFileUpload('recommendationFile', 'recommendationDropArea', 'recommendationPreview', 'recommendationRemove');
            
            // Word counter for textareas
            setupWordCounter('financial_need_statement');
            setupWordCounter('why_deserve_scholarship');
            
            // Functions
            function toggleMenu() {
                mobileMenuPanel.classList.toggle('active');
                menuOverlay.classList.toggle('active');
                
                if (mobileMenuPanel.classList.contains('active')) {
                    menuToggle.innerHTML = '<i class="fas fa-times"></i>';
                    document.body.style.overflow = 'hidden';
                } else {
                    menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
                    document.body.style.overflow = 'auto';
                }
            }
            
            function closeMenu() {
                mobileMenuPanel.classList.remove('active');
                menuOverlay.classList.remove('active');
                menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
                document.body.style.overflow = 'auto';
            }
            
            function goToStep(step) {
                if (step < 1 || step > totalSteps) return;
                
                // Update form steps
                formSteps.forEach(formStep => {
                    formStep.classList.remove('active');
                });
                document.getElementById('step' + step).classList.add('active');
                
                // Update step buttons
                stepButtons.forEach(button => {
                    const buttonStep = parseInt(button.getAttribute('data-step'));
                    button.classList.remove('active', 'completed');
                    
                    if (buttonStep === step) {
                        button.classList.add('active');
                    } else if (buttonStep < step) {
                        button.classList.add('completed');
                    }
                });
                
                // Update progress
                const progress = ((step - 1) / (totalSteps - 1)) * 100;
                progressBar.style.width = progress + '%';
                progressPercent.textContent = Math.round(progress) + '%';
                progressStep.textContent = 'Step ' + step + ' of ' + totalSteps;
                
                // Update current step
                currentStep = step;
                
                // Scroll to top of form
                document.querySelector('.application-section').scrollIntoView({
                    behavior: 'smooth'
                });
            }
            
            function validateStep(step, showErrors = true) {
                const formStep = document.getElementById('step' + step);
                let isValid = true;
                
                // Reset validation visual states if needed
                if (showErrors) {
                    formStep.querySelectorAll('.is-invalid').forEach(element => {
                        element.classList.remove('is-invalid');
                    });
                }
                
                // Validate required fields in current step
                const requiredFields = formStep.querySelectorAll('[required]');
                requiredFields.forEach(field => {
                    if (!validateField(field)) {
                        isValid = false;
                        if (showErrors) field.classList.add('is-invalid');
                    }
                });
                
                if (!isValid && showErrors) {
                    // Scroll to first invalid field
                    const firstInvalid = formStep.querySelector('.is-invalid');
                    if (firstInvalid) {
                        firstInvalid.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });
                    }
                    
                    showToast('Please fill in all required fields', 'error');
                }
                
                return isValid;
            }
            
            function validateField(field) {
                if (field.type === 'email') {
                    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(field.value);
                } else if (field.type === 'tel') {
                    return /^[0-9+\-\(\)\s]{10,20}$/.test(field.value);
                } else if (field.type === 'date') {
                    return field.value !== '';
                } else if (field.tagName === 'SELECT') {
                    return field.value !== '';
                } else {
                    return field.value.trim() !== '';
                }
            }
            
            function setupFileUpload(fileInputId, dropAreaId, previewId, removeButtonId) {
                const fileInput = document.getElementById(fileInputId);
                const dropArea = document.getElementById(dropAreaId);
                const preview = document.getElementById(previewId);
                const removeButton = document.getElementById(removeButtonId);
                
                if (!fileInput || !dropArea || !preview || !removeButton) return;
                
                // Handle drag & drop events
                ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
                    dropArea.addEventListener(eventName, preventDefaults, false);
                });
                
                function preventDefaults(e) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                
                ['dragenter', 'dragover'].forEach(eventName => {
                    dropArea.addEventListener(eventName, highlight, false);
                });
                
                ['dragleave', 'drop'].forEach(eventName => {
                    dropArea.addEventListener(eventName, unhighlight, false);
                });
                
                function highlight() {
                    dropArea.classList.add('dragging');
                }
                
                function unhighlight() {
                    dropArea.classList.remove('dragging');
                }
                
                // Handle file drop
                dropArea.addEventListener('drop', handleDrop, false);
                
                function handleDrop(e) {
                    const dt = e.dataTransfer;
                    const files = dt.files;
                    fileInput.files = files;
                    updateFilePreview(files[0]);
                }
                
                // Handle file input change
                fileInput.addEventListener('change', function() {
                    if (this.files.length > 0) {
                        updateFilePreview(this.files[0]);
                    }
                });
                
                function updateFilePreview(file) {
                    if (!file) return;
                    
                    // Validate file size
                    if (file.size > 5 * 1024 * 1024) {
                        showToast('File size exceeds 5MB limit', 'error');
                        fileInput.value = '';
                        return;
                    }
                    
                    const previewName = preview.querySelector('.file-preview-name');
                    const previewSize = preview.querySelector('.file-preview-size');
                    const previewIcon = preview.querySelector('.file-preview-icon');
                    
                    previewName.textContent = file.name;
                    previewSize.textContent = 'Size: ' + formatFileSize(file.size);
                    
                    // Set appropriate icon
                    if (file.type === 'application/pdf') {
                        previewIcon.className = 'fas fa-file-pdf file-preview-icon';
                    } else if (file.type.includes('word')) {
                        previewIcon.className = 'fas fa-file-word file-preview-icon';
                    } else {
                        previewIcon.className = 'fas fa-file file-preview-icon';
                    }
                    
                    preview.classList.add('active');
                    showToast('File uploaded successfully', 'success');
                }
                
                // Remove file
                removeButton.addEventListener('click', function() {
                    fileInput.value = '';
                    preview.classList.remove('active');
                });
                
                function formatFileSize(bytes) {
                    if (bytes === 0) return '0 Bytes';
                    const k = 1024;
                    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
                    const i = Math.floor(Math.log(bytes) / Math.log(k));
                    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
                }
            }
            
            function setupWordCounter(textareaId) {
                const textarea = document.getElementById(textareaId);
                const counter = document.getElementById(textareaId + '_count');
                
                if (textarea && counter) {
                    textarea.addEventListener('input', function() {
                        const words = this.value.trim().split(/\s+/).filter(word => word.length > 0);
                        counter.textContent = words.length;
                        
                        const maxWords = 300;
                        
                        if (words.length > maxWords) {
                            counter.style.color = '#ef4444';
                        } else {
                            counter.style.color = '';
                        }
                    });
                }
            }
            
            function showToast(message, type = 'info') {
                const toast = document.createElement('div');
                toast.className = `toast toast-${type}`;
                
                let icon = 'info-circle';
                if (type === 'success') icon = 'check-circle';
                if (type === 'error') icon = 'exclamation-circle';
                
                toast.innerHTML = `
                    <i class="fas fa-${icon}"></i>
                    <span>${message}</span>
                `;
                
                toastContainer.appendChild(toast);
                
                // Auto remove toast after 5 seconds
                setTimeout(() => {
                    toast.style.animation = 'slideOutRight 0.3s forwards';
                    setTimeout(() => {
                        toastContainer.removeChild(toast);
                    }, 300);
                }, 5000);
            }
        });
    </script>
</body>
</html>
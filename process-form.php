<?php
require_once __DIR__ . '/app_bootstrap.php';

// Set headers for JSON response
header('Content-Type: application/json');

// Enable error reporting for development
ini_set('display_errors', 0);
error_reporting(E_ALL);

// Initialize response array
$response = [
    'success' => false,
    'message' => ''
];

try {
    // Validate POST data
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }
    
    // Check required fields
    $requiredFields = ['fullName', 'email', 'phone', 'expertise', 'experience', 'motivation'];
    foreach ($requiredFields as $field) {
        if (empty($_POST[$field])) {
            throw new Exception("Missing required field: $field");
        }
    }
    
    // Sanitize input
    $fullName = filter_input(INPUT_POST, 'fullName', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $expertise = filter_input(INPUT_POST, 'expertise', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $experience = filter_input(INPUT_POST, 'experience', FILTER_VALIDATE_INT);
    $motivation = filter_input(INPUT_POST, 'motivation', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }
    
    // Validate experience
    if ($experience === false || $experience < 1) {
        throw new Exception('Experience must be a positive number');
    }
    
    // Connect to PostgreSQL
    $pdo = bfi_pdo_connect('users');
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Check if email already exists
    $checkStmt = $pdo->prepare("
        SELECT id FROM mentor_applications 
        WHERE email = :email
    ");
    $checkStmt->execute(['email' => $email]);
    
    if ($checkStmt->fetchColumn()) {
        throw new Exception('An application with this email already exists');
    }
    
    // Insert data into mentor_applications table
    $stmt = $pdo->prepare("
        INSERT INTO mentor_applications (
            full_name, 
            email, 
            phone, 
            expertise, 
            years_of_experience, 
            motivation,
            status,
            created_at
        ) VALUES (
            :full_name, 
            :email, 
            :phone, 
            :expertise, 
            :years_of_experience, 
            :motivation,
            'pending',
            CURRENT_TIMESTAMP
        )
    ");
    
    $params = [
        ':full_name' => $fullName,
        ':email' => $email,
        ':phone' => $phone,
        ':expertise' => $expertise,
        ':years_of_experience' => $experience,
        ':motivation' => $motivation
    ];
    
    $stmt->execute($params);
    
    // Get the new mentor application ID
    $mentorId = $pdo->lastInsertId();
    
    // Log the application submission
    $logStmt = $pdo->prepare("
        INSERT INTO activity_logs (
            activity_type,
            entity_type,
            entity_id,
            description,
            ip_address,
            created_at
        ) VALUES (
            'mentor_application',
            'mentor_applications',
            :entity_id,
            :description,
            :ip_address,
            CURRENT_TIMESTAMP
        )
    ");
    
    $logParams = [
        ':entity_id' => $mentorId,
        ':description' => "New mentor application submitted by $fullName",
        ':ip_address' => $_SERVER['REMOTE_ADDR']
    ];
    
    $logStmt->execute($logParams);
    
    // Commit transaction
    $pdo->commit();
    
    // Send email notification (admin notification)
    $adminEmail = bfi_admin_email();
    $subject = 'New Mentor Application Received';
    $message = "A new mentor application has been submitted:\n\n";
    $message .= "Name: $fullName\n";
    $message .= "Email: $email\n";
    $message .= "Phone: $phone\n";
    $message .= "Expertise: $expertise\n";
    $message .= "Years of Experience: $experience\n";
    $message .= "Motivation: $motivation\n\n";
    $message .= "Please login to the admin portal to review this application.";
    
    $headers = bfi_mail_headers([
        'from_email' => bfi_no_reply_email(),
        'from_name' => 'BFI Website',
        'reply_to_email' => $email,
    ]);
    
    // Use mail() function for simplicity, consider using a proper mailer library in production
    mail($adminEmail, $subject, $message, $headers);
    
    // Send confirmation email to applicant
    $applicantSubject = 'Thank You for Your Mentor Application';
    $applicantMessage = "Dear $fullName,\n\n";
    $applicantMessage .= "Thank you for your application to become a mentor with Bright Future Initiatives. ";
    $applicantMessage .= "We have received your application and our team will review it shortly.\n\n";
    $applicantMessage .= "We appreciate your interest in our mentorship program and will contact you soon ";
    $applicantMessage .= "regarding the next steps.\n\n";
    $applicantMessage .= "Best regards,\n";
    $applicantMessage .= "The BFI Team";
    
    $applicantHeaders = bfi_mail_headers([
        'from_email' => bfi_no_reply_email(),
        'from_name' => 'BFI Website',
    ]);
    
    mail($email, $applicantSubject, $applicantMessage, $applicantHeaders);
    
    // Set success response
    $response['success'] = true;
    $response['message'] = 'Application submitted successfully';
    
} catch (PDOException $e) {
    // Rollback transaction if database connection was established
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    // Log database error
    error_log('Database Error: ' . $e->getMessage());
    
    $response['message'] = 'A database error occurred. Please try again later.';
} catch (Exception $e) {
    // Handle other exceptions
    $response['message'] = $e->getMessage();
}

// Send JSON response
echo json_encode($response);

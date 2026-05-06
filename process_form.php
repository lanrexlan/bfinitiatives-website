<?php
require_once __DIR__ . '/app_bootstrap.php';

// Email parameters
$recipientEmail = bfi_admin_email();

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';
    $currentDate = date('Y-m-d H:i:s');
    
    // Validate required fields
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        header("Location: contact.html?status=error&message=Please fill all required fields");
        exit;
    }
    
    // Connect to PostgreSQL database
    try {
        $dbconn = bfi_pg_connect('communication');
        
        if (!$dbconn) {
            throw new Exception("Database connection failed");
        }
        
        // Insert data into database
        $query = "INSERT INTO contact_submissions (name, email, phone, subject, message, submission_date) 
                  VALUES ($1, $2, $3, $4, $5, $6)";
        $result = pg_query_params($dbconn, $query, [$name, $email, $phone, $subject, $message, $currentDate]);
        
        if (!$result) {
            throw new Exception("Database insert failed: " . pg_last_error($dbconn));
        }
        
        pg_close($dbconn);
        
        // Send email
        $emailSubject = "Contact Form: $subject";
        $emailBody = "You have received a new message from your website contact form.\n\n";
        $emailBody .= "Name: $name\n";
        $emailBody .= "Email: $email\n";
        $emailBody .= "Phone: $phone\n";
        $emailBody .= "Subject: $subject\n";
        $emailBody .= "Message:\n$message\n";
        
        $headers = bfi_mail_headers([
            'from_email' => bfi_no_reply_email(),
            'from_name' => 'BFI Website',
            'reply_to_email' => $email,
        ]);
        
        $mailSuccess = mail($recipientEmail, $emailSubject, $emailBody, $headers);
        
        if (!$mailSuccess) {
            throw new Exception("Email could not be sent");
        }
        
        // Redirect back with success message
        header("Location: contact.html?status=success");
        exit;
        
    } catch (Exception $e) {
        // Log error
        error_log("Contact form error: " . $e->getMessage());
        
        // Redirect back with error message
        header("Location: contact.html?status=error&message=" . urlencode($e->getMessage()));
        exit;
    }
} else {
    // Not a POST request, redirect to contact page
    header("Location: contact.html");
    exit;
}
?>

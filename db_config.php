<?php
require_once __DIR__ . '/app_bootstrap.php';

// Database configuration
$dbConfig = bfi_database_config('users');
$db_host = $dbConfig['host'];
$db_name = $dbConfig['dbname'];
$db_user = $dbConfig['user'];
$db_password = $dbConfig['password'];

// Connect to PostgreSQL database
try {
    $db = bfi_pdo_connect('users');
} catch (PDOException $e) {
    error_log("Database Connection Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection error. Please try again later.'
    ]);
    exit;
}

// Email configuration
$admin_email = bfi_admin_email();
$admin_name = bfi_admin_name();

// Function to generate unique reference numbers
function generateReference($prefix) {
    $timestamp = time();
    $random = mt_rand(1000, 9999);
    return strtoupper($prefix . substr($timestamp, -6) . $random);
}

// Function to sanitize input data
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Function to validate email
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// Function to send email notifications
function sendEmail($to, $subject, $message, $from = null, $fromName = null) {
    global $admin_email, $admin_name;
    
    $fromEmail = $from ?? bfi_from_email();
    $fromNameFinal = $fromName ?? $admin_name;

    $headers = bfi_mail_headers([
        'from_email' => $fromEmail,
        'from_name' => $fromNameFinal,
        'html' => true,
    ]);

    return mail($to, $subject, $message, $headers);
}

// Upload file function
function uploadFile($file, $directory, $allowedTypes) {
    // Check if the directory exists, if not create it
    if (!file_exists($directory)) {
        mkdir($directory, 0755, true);
    }
    
    // Check for errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }
    
    // Check file type
    $fileInfo = pathinfo($file['name']);
    $extension = strtolower($fileInfo['extension']);
    
    if (!in_array($extension, $allowedTypes)) {
        return false;
    }
    
    // Create a unique filename
    $newFileName = uniqid() . '_' . time() . '.' . $extension;
    $targetPath = $directory . '/' . $newFileName;
    
    // Move the file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return $targetPath;
    }
    
    return false;
}
?>

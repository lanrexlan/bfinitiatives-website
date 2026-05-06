<?php
require_once __DIR__ . '/app_bootstrap.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullName = $_POST['fullName'];
    $emailAddress = $_POST['emailAddress'];
    
    $to = bfi_admin_email();
    $subject = "Scholarship Notification Registration";
    $message = "Full Name: " . $fullName . "\nEmail Address: " . $emailAddress;
    $headers = bfi_mail_headers([
        'from_email' => bfi_no_reply_email(),
        'from_name' => 'BFI Website',
        'reply_to_email' => $emailAddress,
    ]);
    
    if (mail($to, $subject, $message, $headers)) {
        echo "success";
    } else {
        echo "error";
    }
}
?>

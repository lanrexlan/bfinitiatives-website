<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $db = new Database();
        $conn = $db->getConnection();
        
        // Validate and sanitize inputs
        $first_name = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_STRING);
        $last_name = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);

        // Basic validation
        if (empty($first_name) || empty($email)) {
            throw new Exception('Required fields are missing');
        }

        // Update database
        $stmt = $conn->prepare("
            UPDATE users 
            SET 
                first_name = :first_name,
                last_name = :last_name,
                email = :email,
                phone = :phone
            WHERE id = :user_id
        ");

        $stmt->execute([
            ':first_name' => $first_name,
            ':last_name' => $last_name,
            ':email' => $email,
            ':phone' => $phone,
            ':user_id' => $_SESSION['user_id']
        ]);

        // Redirect back to profile with success message
        $_SESSION['success'] = 'Profile updated successfully';
        header('Location: profile.php');
        exit();

    } catch (Exception $e) {
        error_log("Update error: " . $e->getMessage());
        $_SESSION['error'] = 'Error updating profile: ' . $e->getMessage();
        header('Location: profile.php');
        exit();
    }
}
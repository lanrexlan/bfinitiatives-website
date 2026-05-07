<?php
// Start session
session_start();

// Check if the user is logged in as admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit;
}

// Set JSON header
header('Content-Type: application/json');

// Include database connection
require_once 'includes/config.php';
require_once 'includes/db.php';

// Handle user status update
try {
    // Validate required fields
    if (empty($_POST['user_id']) || empty($_POST['new_status'])) {
        throw new Exception('Missing required fields');
    }

    $user_id = $_POST['user_id'];
    $new_status = $_POST['new_status'];
    $scholarship_status = $_POST['scholarship_status'] ?? null;
    $admin_comments = $_POST['admin_comments'] ?? '';

    // Get database connection
    $db = new Database();
    $conn = $db->getConnection();

    // Prepare the update query
    $query = "UPDATE users SET status = :status, updated_at = CURRENT_TIMESTAMP";
    $params = [
        ':status' => $new_status,
        ':user_id' => $user_id
    ];
    
    // Add scholarship_status to the query if provided
    if (!empty($scholarship_status)) {
        $query .= ", scholarship_status = :scholarship_status";
        $params[':scholarship_status'] = $scholarship_status;
    }
    
    // Complete the query
    $query .= " WHERE id = :user_id";
    
    // Execute the update
    $stmt = $conn->prepare($query);
    $stmt->execute($params);

    // Check if any rows were affected
    if ($stmt->rowCount() > 0) {
        // Get user email for notification
        $select_query = "SELECT email, first_name FROM users WHERE id = :user_id";
        $stmt = $conn->prepare($select_query);
        $stmt->execute([':user_id' => $user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && !empty($user['email'])) {
            // Send email notification
            require_once 'includes/mailer.php';
            
            // Create email content
            $subject = "BFI Scholarship - Status Update";
            
            // Create email body with HTML formatting
            $messageBody = "
                <p>Dear {$user['first_name']},</p>
                <p>Your scholar account status has been updated to: <strong>" . ucfirst($new_status) . "</strong></p>
            ";
            
            if (!empty($scholarship_status)) {
                $messageBody .= "<p>Your scholarship status has been updated to: <strong>" . ucfirst($scholarship_status) . "</strong></p>";
            }
            
            if (!empty($admin_comments)) {
                $messageBody .= "<p>Additional comments: {$admin_comments}</p>";
            }
            
            $messageBody .= "
                <p>You can log in to your account to check for more details.</p>
                <p>Best regards,<br>BFI Scholarship Team</p>
            ";
            
            // Send the email
            $email_result = sendEmail(
                $user['email'],
                $subject,
                $messageBody,
                $user['first_name']
            );
            
            if (!$email_result['success']) {
                error_log("Failed to send status update email: " . $email_result['message']);
            }
        }

        // Log the action
        try {
            $log_query = "INSERT INTO admin_logs (admin_id, action, details, ip_address) 
                         VALUES (:admin_id, 'update_user_status', :details, :ip_address)";
            
            $log_details = "Updated user {$user_id} status to {$new_status}";
            if (!empty($scholarship_status)) {
                $log_details .= ", scholarship status to {$scholarship_status}";
            }
            
            $log_stmt = $conn->prepare($log_query);
            $log_stmt->execute([
                ':admin_id' => $_SESSION['admin_id'],
                ':details' => $log_details,
                ':ip_address' => $_SERVER['REMOTE_ADDR']
            ]);
        } catch (Exception $e) {
            // Just log the error but continue processing
            error_log("Error logging admin action: " . $e->getMessage());
        }

        echo json_encode(['success' => true, 'message' => 'User status updated successfully']);
    } else {
        echo json_encode([
            'success' => false, 
            'message' => 'No user found with ID: ' . $user_id
        ]);
    }

} catch (Exception $e) {
    error_log("User status update error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => 'Error updating status: ' . $e->getMessage()
    ]);
}
?>
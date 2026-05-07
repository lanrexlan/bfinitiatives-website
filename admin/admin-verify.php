<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php';
require_once 'includes/db.php';

// Add error logging
error_log("Admin verification process started");

$error = null;
$success = null;

try {
    $database = new Database();
    $conn = $database->getConnection();
    error_log("Database connection established");

    if (isset($_GET['token'])) {
        $token = $_GET['token'];
        error_log("Token received: " . $token);
        
        // First, check if token exists and is valid in the admins table
        $check_stmt = $conn->prepare("
            SELECT id, first_name, email 
            FROM admins 
            WHERE verification_token = :token 
            AND is_active = FALSE
        ");
        
        $check_stmt->execute([':token' => $token]);
        $admin = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($admin) {
            error_log("Valid token found for admin ID: " . $admin['id']);
            
            // Update admin verification status
            $update_stmt = $conn->prepare("
                UPDATE admins 
                SET is_active = TRUE,
                    verification_token = NULL,
                    email_verified_at = CURRENT_TIMESTAMP,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :admin_id
            ");
            
            $update_stmt->execute([':admin_id' => $admin['id']]);
            
            if ($update_stmt->rowCount() > 0) {
                error_log("Admin verified successfully");
                $success = "Your email has been verified successfully! You can now log in to your account.";
            } else {
                error_log("Failed to update admin verification status");
                $error = "Failed to verify email. Please try again.";
            }
        } else {
            error_log("Invalid or expired token");
            $error = "Invalid or expired verification token.";
        }
    } else {
        error_log("No token provided");
        $error = "No verification token provided.";
    }
} catch (Exception $e) {
    error_log("Verification error: " . $e->getMessage());
    $error = "An error occurred during verification. Please try again.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - BFI Admin Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .verification-container {
            max-width: 600px;
            margin: 40px auto;
            padding: 20px;
        }
        .card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,0,0,0.1);
        }
        .card-title {
            color: #2c3e50;
            font-weight: 600;
        }
        .alert {
            border-radius: 8px;
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <div class="card">
            <div class="card-body p-4">
                <h4 class="card-title text-center mb-4">
                    <i class="fas fa-envelope-open-text me-2"></i>
                    Admin Email Verification
                </h4>
                
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                    <div class="text-center mt-4">
                        <a href="admin-login.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-2"></i>Go to Admin Login
                        </a>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                    <div class="text-center mt-4">
                        <a href="admin-login.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-2"></i>Proceed to Admin Login
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
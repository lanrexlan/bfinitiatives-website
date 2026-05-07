<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'includes/config.php';
require_once 'includes/db.php';

// Add error logging
error_log("Verification process started");

$error = null;
$success = null;

try {
    $database = new Database();
    $conn = $database->getConnection();
    error_log("Database connection established");

    if (isset($_GET['token'])) {
        $token = $_GET['token'];
        error_log("Token received: " . $token);
        
        // First, check if token exists and is valid
        $check_stmt = $conn->prepare("
            SELECT id, first_name, email 
            FROM users 
            WHERE verification_token = :token 
            AND is_verified = FALSE
        ");
        
        $check_stmt->execute([':token' => $token]);
        $user = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            error_log("Valid token found for user ID: " . $user['id']);
            
            // Update user verification status
            $update_stmt = $conn->prepare("
                UPDATE users 
                SET is_verified = TRUE,
                    verification_token = NULL,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :user_id
            ");
            
            $update_stmt->execute([':user_id' => $user['id']]);
            
            if ($update_stmt->rowCount() > 0) {
                error_log("User verified successfully");
                $success = "Your email has been verified successfully! You can now log in to your account.";
            } else {
                error_log("Failed to update user verification status");
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
    <title>Email Verification - BFI Scholar Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4e73df;
            --success-color: #1cc88a;
            --error-color: #e74a3b;
            --dark-text: #2c3e50;
            --light-bg: #f8f9fa;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e4e8f0 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .verification-container {
            max-width: 600px;
            width: 100%;
            margin: 0 auto;
        }
        
        .card {
            border: none;
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(50, 50, 93, 0.1), 0 5px 15px rgba(0, 0, 0, 0.07);
            overflow: hidden;
            transform: translateY(0);
            transition: all 0.3s ease;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(50, 50, 93, 0.15), 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color), #7c8ce4);
            padding: 2rem 0;
            text-align: center;
            border-bottom: none;
        }
        
        .logo-container {
            width: 90px;
            height: 90px;
            margin: 0 auto 1rem;
            background-color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }
        
        .logo-icon {
            font-size: 2.5rem;
            color: var(--primary-color);
        }
        
        .card-title {
            color: white;
            font-weight: 600;
            margin-bottom: 0;
            font-size: 1.6rem;
        }
        
        .card-body {
            padding: 2.5rem;
        }
        
        .alert {
            border-radius: 12px;
            padding: 1.2rem;
            margin-bottom: 1.5rem;
            border: none;
            display: flex;
            align-items: center;
        }
        
        .alert-success {
            background-color: rgba(28, 200, 138, 0.1);
            color: var(--success-color);
        }
        
        .alert-danger {
            background-color: rgba(231, 74, 59, 0.1);
            color: var(--error-color);
        }
        
        .alert i {
            font-size: 1.5rem;
            margin-right: 1rem;
        }
        
        .btn {
            border-radius: 50px;
            padding: 0.75rem 2rem;
            font-weight: 500;
            box-shadow: 0 4px 6px rgba(50, 50, 93, 0.11), 0 1px 3px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #7c8ce4);
            border: none;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 7px 14px rgba(50, 50, 93, 0.1), 0 3px 6px rgba(0, 0, 0, 0.08);
            background: linear-gradient(135deg, #4668c5, #6d7fd1);
        }
        
        .status-message {
            font-size: 1.1rem;
            margin-bottom: 1.5rem;
        }
        
        .footer-text {
            text-align: center;
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 2rem;
        }
        
        @media (max-width: 576px) {
            .card-body {
                padding: 1.5rem;
            }
            
            .logo-container {
                width: 70px;
                height: 70px;
            }
            
            .logo-icon {
                font-size: 2rem;
            }
            
            .card-title {
                font-size: 1.3rem;
            }
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <div class="card">
            <div class="card-header">
                <div class="logo-container">
                    <i class="fas fa-envelope-open-text logo-icon"></i>
                </div>
                <h4 class="card-title">Email Verification</h4>
            </div>
            <div class="card-body">
                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <div>
                            <div class="status-message"><?php echo htmlspecialchars($error); ?></div>
                            <p>Please check your email for a valid verification link or contact support if the issue persists.</p>
                        </div>
                    </div>
                    <div class="text-center mt-4">
                        <a href="login.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-2"></i>Go to Login
                        </a>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <div>
                            <div class="status-message"><?php echo htmlspecialchars($success); ?></div>
                            <p>Welcome to the BFI Scholar Portal! Your account is now active.</p>
                        </div>
                    </div>
                    <div class="text-center mt-4">
                        <a href="login.php" class="btn btn-primary">
                            <i class="fas fa-sign-in-alt me-2"></i>Proceed to Login
                        </a>
                    </div>
                <?php endif; ?>

                <div class="footer-text">
                    <p>If you have any questions, please contact <a href="mailto:support@bfinitiatives.com">support@bfinitiatives.com</a></p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
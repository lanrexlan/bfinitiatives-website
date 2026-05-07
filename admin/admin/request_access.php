<?php
// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Start session
session_start();

// Define the path to includes directory
define('INCLUDES_PATH', dirname(__FILE__) . '/../admin/includes/');

// Require the necessary files
require_once INCLUDES_PATH . 'config.php';
require_once INCLUDES_PATH . 'db.php';

// Initialize variables
$error = null;
$success = null;

try {
    error_log("Attempting database connection");
    $database = new Database();
    $conn = $database->getConnection();
    error_log("Database connection successful");
} catch (Exception $e) {
    error_log("Database connection error: " . $e->getMessage());
    die("Connection failed. Please try again later.");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Sanitize inputs
        $first_name = filter_var($_POST['first_name'], FILTER_SANITIZE_STRING);
        $last_name = filter_var($_POST['last_name'], FILTER_SANITIZE_STRING);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        $position = filter_var($_POST['position'], FILTER_SANITIZE_STRING);
        $reason = filter_var($_POST['reason'], FILTER_SANITIZE_STRING);

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format");
        }

        // Insert request into database
        $stmt = $conn->prepare("
            INSERT INTO admin_access_requests (
                first_name,
                last_name,
                email,
                position,
                reason,
                status,
                created_at
            ) VALUES (
                :first_name,
                :last_name,
                :email,
                :position,
                :reason,
                'pending',
                CURRENT_TIMESTAMP
            )
        ");

        $stmt->execute([
            ':first_name' => $first_name,
            ':last_name' => $last_name,
            ':email' => $email,
            ':position' => $position,
            ':reason' => $reason
        ]);

        // Set success message
        $success = "Your request has been submitted successfully. We will review it and contact you soon.";
        
        // Redirect after successful submission
        header("Location: index.php?status=success");
        exit();

    } catch (Exception $e) {
        error_log("Error in request_access.php: " . $e->getMessage());
        $error = "An error occurred. Please try again later.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Request Admin Access - BFI Admin Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8fafc;
            font-family: 'Inter', sans-serif;
        }
        .request-form {
            max-width: 600px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="request-form">
            <h2 class="text-center mb-4">Request Admin Access</h2>
            
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php else: ?>
                <form method="POST" action="">
                    <div class="row mb-3">
                        <div class="col-md-6">
                            <label class="form-label">First Name</label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Last Name</label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Position/Role</label>
                        <input type="text" name="position" class="form-control" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Reason for Access</label>
                        <textarea name="reason" class="form-control" rows="4" required></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-paper-plane me-2"></i>Submit Request
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
<?php
require_once 'includes/config.php';
require_once 'includes/db.php';
require_once 'includes/functions.php';

$error = '';
$success = '';
$valid_token = false;
$token = $_GET['token'] ?? '';

if (!$token) {
    header('Location: login.php');
    exit();
}

$db = new Database();
$conn = $db->getConnection();

// Verify token
$stmt = $conn->prepare("
    SELECT pr.*, u.email 
    FROM password_resets pr 
    JOIN users u ON pr.user_id = u.id 
    WHERE pr.token = ? AND pr.used = 0 AND pr.expires_at > NOW()
");
$stmt->execute([$token]);
$reset = $stmt->fetch();

if ($reset) {
    $valid_token = true;
    
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $password = $_POST['password'];
        $confirm_password = $_POST['confirm_password'];
        
        if (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters long';
        } elseif ($password !== $confirm_password) {
            $error = 'Passwords do not match';
        } else {
            try {
                $conn->beginTransaction();
                
                // Update password
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt->execute([$hashed_password, $reset['user_id']]);
                
                // Mark token as used
                $stmt = $conn->prepare("UPDATE password_resets SET used = 1 WHERE token = ?");
                $stmt->execute([$token]);
                
                $conn->commit();
                $success = 'Password has been reset successfully. You can now login with your new password.';
                
                // Redirect to login page after 3 seconds
                header("refresh:3;url=login.php");
            } catch (Exception $e) {
                $conn->rollBack();
                $error = 'Failed to reset password. Please try again.';
            }
        }
    }
} else {
    $error = 'Invalid or expired reset link.';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - BFI Scholar Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .reset-password-container {
            max-width: 400px;
            margin: 50px auto;
            padding: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="reset-password-container">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title text-center mb-4">Reset Password</h4>
                    
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
                    <?php endif; ?>
                    
                    <?php if ($valid_token): ?>
                        <form method="POST">
                            <div class="mb-3">
                                <label for="password" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">Confirm New Password</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">Reset Password</button>
                        </form>
                    <?php endif; ?>
                    
                    <div class="text-center mt-3">
                        <a href="login.php" class="text-decoration-none">Back to Login</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php
// admin/complete_registration.php

session_start();
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

$error = null;
$success = null;
$token = $_GET['token'] ?? '';

// Verify token first
try {
    $db = new Database();
    $conn = $db->getConnection();
    
    $stmt = $conn->prepare("
        SELECT * FROM admin_allowed_emails 
        WHERE registration_token = :token 
        AND token_expiry > CURRENT_TIMESTAMP 
        AND is_used = false
    ");
    
    $stmt->execute([':token' => $token]);
    $invitation = $stmt->fetch();

    if (!$invitation) {
        die("Invalid or expired registration link. Please contact support.");
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if (empty($password)) {
            $error = "Password is required";
        } else if (strlen($password) < 8) {
            $error = "Password must be at least 8 characters long";
        } else if ($password !== $confirm_password) {
            $error = "Passwords do not match";
        } else {
            try {
                $conn->beginTransaction();

                // Create user account
                $stmt = $conn->prepare("
                    INSERT INTO users (
                        first_name,
                        last_name,
                        email,
                        password,
                        role_id,
                        is_admin,
                        is_active,
                        is_verified,
                        created_at,
                        updated_at
                    ) VALUES (
                        :first_name,
                        :last_name,
                        :email,
                        :password,
                        1,
                        true,
                        true,
                        true,
                        CURRENT_TIMESTAMP,
                        CURRENT_TIMESTAMP
                    )
                ");

                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                $stmt->execute([
                    ':first_name' => $invitation['first_name'],
                    ':last_name' => $invitation['last_name'],
                    ':email' => $invitation['email'],
                    ':password' => $hashed_password
                ]);

                // Mark invitation as used
                $stmt = $conn->prepare("
                    UPDATE admin_allowed_emails 
                    SET is_used = true, 
                        used_at = CURRENT_TIMESTAMP 
                    WHERE registration_token = :token
                ");
                $stmt->execute([':token' => $token]);

                $conn->commit();
                $success = "Registration complete! You can now log in to your admin account.";

            } catch (Exception $e) {
                $conn->rollBack();
                $error = "Registration failed. Please try again.";
                error_log("Admin registration error: " . $e->getMessage());
            }
        }
    }

} catch (Exception $e) {
    die("An error occurred. Please try again later.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Admin Registration - BFI Admin Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Add your existing admin portal styles here */
        
        :root {
            --primary-color: #2563eb;
            --secondary-color: #1e40af;
            --accent-color: #3b82f6;
            --success-color: #059669;
            --danger-color: #dc2626;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
        }

        .navbar {
            background: white !important;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.08);
            padding: 1rem 0;
        }

        .navbar-brand {
            font-weight: 700;
            color: var(--secondary-color) !important;
            font-size: 1.5rem;
        }

        .nav-link {
            font-weight: 500;
            color: #1e293b !important;
            transition: all 0.3s ease;
            padding: 0.5rem 1rem;
            border-radius: 8px;
        }

        .nav-link:hover {
            background: #f1f5f9;
            color: var(--primary-color) !important;
        }

        .navbar-logo {
            width: 45px;
            height: auto;
            margin-right: 10px;
        }

        .hero-section {
            background: linear-gradient(rgba(30, 58, 138, 0.95), rgba(30, 58, 138, 0.9)),
                        url('/Images/admin-bg.jpeg') no-repeat center center;
            background-size: cover;
            padding: 6rem 0;
            color: white;
            text-align: center;
            border-radius: 0 0 3rem 3rem;
            margin-bottom: 4rem;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            animation: fadeInUp 1s ease;
        }

        .hero-subtitle {
            font-size: 1.25rem;
            font-weight: 400;
            margin-bottom: 2rem;
            opacity: 0.9;
            animation: fadeInUp 1s ease 0.2s;
            animation-fill-mode: both;
        }

        .admin-features {
            margin-bottom: 4rem;
        }

        .feature-card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            height: 100%;
            transition: all 0.3s ease;
            border: 1px solid #e2e8f0;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            border-color: var(--primary-color);
        }

        .feature-icon {
            width: 60px;
            height: 60px;
            background: #f0f9ff;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1.5rem;
            color: var(--primary-color);
            font-size: 1.5rem;
        }

        .feature-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #1e293b;
        }

        .feature-text {
            color: #64748b;
            line-height: 1.6;
        }

        .stats-section {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            padding: 4rem 0;
            color: white;
            border-radius: 1rem;
            margin-bottom: 4rem;
        }

        .stat-card {
            text-align: center;
            padding: 2rem;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .action-card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            text-align: center;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }

        .btn-admin {
            background: var(--primary-color);
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-admin:hover {
            background: var(--secondary-color);
            color: white;
            transform: translateY(-2px);
        }

        .footer {
            background: #0f172a;
            color: white;
            padding: 4rem 0 1.5rem;
            margin-top: 4rem;
        }

        .footer-content {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 4rem;
            margin-bottom: 2rem;
        }

        .footer-brand h3 {
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .footer-links {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 2rem;
        }

        .footer-section h4 {
            font-weight: 600;
            margin-bottom: 1.5rem;
            color: #e2e8f0;
        }

        .footer-section a {
            color: #cbd5e1;
            text-decoration: none;
            display: block;
            margin-bottom: 0.75rem;
            transition: color 0.3s ease;
        }

        .footer-section a:hover {
            color: var(--primary-color);
        }

        .social-links {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .social-links a {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .social-links a:hover {
            background: var(--primary-color);
            transform: translateY(-3px);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }
            .footer-content {
                grid-template-columns: 1fr;
            }
            .footer-links {
                grid-template-columns: 1fr;
            }
        }
        
        .password-requirements {
            background: #f8fafc;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }

        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
            color: #64748b;
            font-size: 0.875rem;
        }

        .requirement i {
            margin-right: 0.5rem;
            font-size: 0.75rem;
        }

        .requirement.met {
            color: #059669;
        }

        .requirement.met i {
            color: #059669;
        }
    </style>
</head>
<body>
    
<nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand" href="index.php">
                <img src="/Images/BFI_Logo.png" alt="BFI Logo" class="navbar-logo">
                BFI Admin Portal
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
    </nav>

    <section class="hero-section">
        <div class="container">
            <h1 class="hero-title">Welcome to BFI Admin Portal</h1>
            <p class="hero-subtitle">Manage scholars, track progress, and streamline operations efficiently</p>
        </div>
    </section>
    
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <img src="../assets/images/bfi-logo.png" alt="BFI Logo" class="mb-3" style="max-width: 120px;">
                            <h3>Complete Your Registration</h3>
                            <p class="text-muted">Set up your admin account password</p>
                        </div>

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
                                <div class="mt-3">
                                    <a href="login.php" class="btn btn-success w-100">
                                        <i class="fas fa-sign-in-alt me-2"></i>Proceed to Login
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <form method="POST" action="" class="needs-validation" novalidate>
                                <div class="mb-3">
                                    <label class="form-label">Set Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" id="password" name="password" required>
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="form-label">Confirm Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" class="form-control" name="confirm_password" required>
                                    </div>
                                </div>

                                <div class="password-requirements">
                                    <div class="requirement" data-requirement="length">
                                        <i class="fas fa-circle"></i>
                                        At least 8 characters
                                    </div>
                                    <div class="requirement" data-requirement="uppercase">
                                        <i class="fas fa-circle"></i>
                                        One uppercase letter
                                    </div>
                                    <div class="requirement" data-requirement="lowercase">
                                        <i class="fas fa-circle"></i>
                                        One lowercase letter
                                    </div>
                                    <div class="requirement" data-requirement="number">
                                        <i class="fas fa-circle"></i>
                                        One number
                                    </div>
                                </div>

                                <button type="submit" class="btn btn-primary w-100 mt-4">
                                    <i class="fas fa-user-plus me-2"></i>Complete Registration
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password visibility toggle
        document.getElementById('togglePassword').addEventListener('click', function() {
            const password = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (password.type === 'password') {
                password.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        // Password requirements checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            
            // Check each requirement
            const requirements = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password)
            };

            // Update UI for each requirement
            Object.keys(requirements).forEach(req => {
                const element = document.querySelector(`[data-requirement="${req}"]`);
                if (requirements[req]) {
                    element.classList.add('met');
                    element.querySelector('i').classList.remove('fa-circle');
                    element.querySelector('i').classList.add('fa-check-circle');
                } else {
                    element.classList.remove('met');
                    element.querySelector('i').classList.remove('fa-check-circle');
                    element.querySelector('i').classList.add('fa-circle');
                }
            });
        });
    </script>
</body>
</html>
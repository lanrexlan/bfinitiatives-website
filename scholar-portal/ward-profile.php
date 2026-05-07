<?php
require_once 'includes/config.php';
require_once 'includes/db.php';

// Check if user is logged in and is a primary scholar
if (!isset($_SESSION['user_id']) || $_SESSION['user_program'] !== 'primary') {
    header('Location: login.php');
    exit();
}

$message = '';
$message_type = '';

try {
    $db = new Database();
    $conn = $db->getConnection();

    // First, check if a record exists for this user
    $checkStmt = $conn->prepare("SELECT id FROM ward_details WHERE user_id = :user_id");
    $checkStmt->execute([':user_id' => $_SESSION['user_id']]);
    $exists = $checkStmt->fetch();

    // Handle form submission for profile update
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if ($exists) {
            // Update existing record
            $stmt = $conn->prepare("
                UPDATE ward_details 
                SET 
                    ward_name = :ward_name,
                    date_of_birth = :date_of_birth,
                    current_class = :current_class,
                    current_term = :current_term,  /* Added this line */
                    school_name = :school_name,
                    school_address = :school_address,
                    guardian_name = :guardian_name,
                    guardian_phone = :guardian_phone,
                    guardian_email = :guardian_email,
                    updated_at = CURRENT_TIMESTAMP
                WHERE user_id = :user_id
            ");
        } else {
            // Insert new record
            $stmt = $conn->prepare("
                INSERT INTO ward_details 
                (user_id, ward_name, date_of_birth, current_class, current_term school_name, 
                school_address, guardian_name, guardian_phone, guardian_email)
                VALUES 
                (:user_id, :ward_name, :date_of_birth, :current_class, :school_name,
                :school_address, :guardian_name, :guardian_phone, :guardian_email)
            ");
        }

        // Sanitize and validate input data
        $ward_name = filter_var($_POST['ward_name'], FILTER_SANITIZE_STRING);
        $date_of_birth = $_POST['date_of_birth'];
        $current_class = filter_var($_POST['current_class'], FILTER_SANITIZE_STRING);
        $school_name = filter_var($_POST['school_name'], FILTER_SANITIZE_STRING);
        $school_address = filter_var($_POST['school_address'], FILTER_SANITIZE_STRING);
        $guardian_name = filter_var($_POST['guardian_name'], FILTER_SANITIZE_STRING);
        $guardian_phone = filter_var($_POST['guardian_phone'], FILTER_SANITIZE_STRING);
        $guardian_email = filter_var($_POST['guardian_email'], FILTER_SANITIZE_EMAIL);

        $params = [
            ':user_id' => $_SESSION['user_id'],
            ':ward_name' => $ward_name,
            ':date_of_birth' => $date_of_birth,
            ':current_class' => $current_class,
            ':current_term' => $_POST['current_term'],  /* Added this line */
            ':school_name' => $school_name,
            ':school_address' => $school_address,
            ':guardian_name' => $guardian_name,
            ':guardian_phone' => $guardian_phone,
            ':guardian_email' => $guardian_email
        ];

        try {
            $result = $stmt->execute($params);
            if ($result) {
                $message = 'Ward profile updated successfully!';
                $message_type = 'success';
                
                // Refresh the page to show updated data
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=1");
                exit();
            } else {
                $message = 'Failed to update profile. Please try again.';
                $message_type = 'error';
            }
        } catch (PDOException $e) {
            error_log("Database Error: " . $e->getMessage());
            $message = 'Database error occurred. Please try again.';
            $message_type = 'error';
        }
    }

    // Fetch current ward details
    $stmt = $conn->prepare("SELECT * FROM ward_details WHERE user_id = :user_id");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $ward = $stmt->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    $message = 'An error occurred. Please try again later.';
    $message_type = 'error';
}

// Show success message if redirected after successful update
if (isset($_GET['success']) && $_GET['success'] == 1) {
    $message = 'Ward profile updated successfully!';
    $message_type = 'success';
}
?>

<!-- [Rest of your HTML code remains the same] -->


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ward Profile</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Include your existing styles */
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --primary-color: #4f46e5;
            --primary-hover: #4338ca;
            --secondary-color: #818cf8;
            --text-color: #1f2937;
            --light-bg: #f3f4f6;
            --border-color: #e5e7eb;
            --success-color: #059669;
            --error-color: #dc2626;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background-color: #f9fafb;
            color: var(--text-color);
            line-height: 1.6;
        }

        .main-content {
            padding: 2rem;
            margin-left: 250px;
            min-height: 100vh;
        }

        .profile-container {
            background: white;
            padding: 2rem;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1), 0 2px 4px -2px rgb(0 0 0 / 0.1);
            max-width: 900px;
            margin: 2rem auto;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border-color);
        }

        .profile-header h1 {
            color: var(--primary-color);
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
        }

        .section {
            background: #ffffff;
            margin-bottom: 2rem;
            padding: 1.5rem;
            border-radius: 0.75rem;
            border: 1px solid var(--border-color);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .section:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.1);
        }

        .section-title {
            color: var(--primary-color);
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .section-title i {
            color: var(--secondary-color);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }
        
        /* Add to your existing CSS */
select.form-control {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.5rem;
    border: 1px solid var(--border-color);
    border-radius: 0.5rem;
    font-size: 1rem;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20' viewBox='0 0 24 24' fill='none' stroke='%236b7280' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='6 9 12 15 18 9'%3E%3C/polyline%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 1rem center;
    background-size: 1em;
}

select.form-control:focus {
    outline: none;
    border-color: var(--primary-color);
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
}
        
        label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-color);
            font-weight: 500;
            font-size: 0.95rem;
        }

        input[type="text"],
        input[type="email"],
        input[type="date"],
        input[type="tel"] {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 0.5rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: #fff;
        }

        input:focus {
            outline: none;
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
        }

        .save-btn {
            background: var(--primary-color);
            color: black;
            border: none;
            padding: 1rem 2rem;
            border-radius: 0.5rem;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            width: 100%;
            max-width: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin: 2rem auto 0;
            transition: all 0.3s ease;
        }

        .save-btn:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }

        .message {
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-10px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .message.success {
            background: #ecfdf5;
            color: var(--success-color);
            border: 1px solid #a7f3d0;
        }

        .message.error {
            background: #fef2f2;
            color: var(--error-color);
            border: 1px solid #fecaca;
        }

        .required {
            color: var(--error-color);
            margin-left: 3px;
        }

        /* Fancy input styles */
        .input-wrapper {
            position: relative;
        }

        .input-wrapper i {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #9ca3af;
        }

        .input-wrapper input {
            padding-left: 2.5rem;
        }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .profile-container {
                padding: 1rem;
            }

            .section {
                padding: 1rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        body {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)),
                        url('/Images/programs-bg.jpeg') center/cover fixed;
            color: var(--text-color);
            min-height: 100vh;
        }

        /* Header Styles */
        .header {
    position: fixed;
    top: 0;
    right: 0;
    left: var(--sidebar-width); /* Align with sidebar */
    height: 70px;
    background: white;
    border-bottom: 1px solid var(--border-color);
    z-index: 100;
    padding: 0 2rem;
    display: flex;
    justify-content: flex-start; /* Force left alignment */
}

        .header-left {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-right: auto; /* Push other elements to the right */
}

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .notification-bell {
            position: relative;
            cursor: pointer;
        }

        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #ff4757;
            color: white;
            border-radius: 50%;
            width: 18px;
            height: 18px;
            font-size: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        
        .upload-container {
            background: white;
            padding: 25px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            max-width: 800px;
            margin: 20px auto;
        }

        .upload-header {
            margin-bottom: 20px;
            text-align: center;
        }

        .upload-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .upload-form {
            text-align: center;
        }

        .file-input-container {
            border: 2px dashed #ccc;
            padding: 30px;
            border-radius: 5px;
            margin: 20px 0;
            cursor: pointer;
            transition: border-color 0.3s ease;
        }

        .file-input-container:hover {
            border-color: #007bff;
        }

        .file-input {
            display: none;
        }

        .upload-btn {
            background: #007bff;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s ease;
        }

        .upload-btn:hover {
            background: #0056b3;
        }

        .message {
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }

        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .requirements {
            margin-top: 20px;
            font-size: 0.9em;
            color: #666;
        }

        #selected-file-name {
            margin-top: 10px;
            font-size: 0.9em;
            color: #666;
        }
        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }
        
        .progress-circle {
            position: relative;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: #f0f0f0;
            margin: 0 auto;
        }

        .progress-circle::after {
            content: attr(data-progress) '%';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 1.2rem;
            font-weight: bold;
            color: var(--primary-color);
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }

        .quick-action-btn {
            background: white;
            border: none;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            transition: all 0.3s;
            cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .quick-action-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .quick-action-btn i {
            font-size: 1.5rem;
            margin-bottom: 10px;
            color: var(--primary-color);
        }

        .stat-card {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .notification-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            width: 300px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            padding: 15px;
            display: none;
            z-index: 1000;
        }

        .notification-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }

        .notification-item:last-child {
            border-bottom: none;
        }

        .chart-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-top: 20px;
        }

        /* New animated background */
        .gradient-background {
            background: linear-gradient(-45deg, #4361ee, #3f37c9, #48cae4, #4895ef);
            background-size: 400% 400%;
            animation: gradient 15s ease infinite;
        }

        @keyframes gradient {
            0% {
                background-position: 0% 50%;
            }
            50% {
                background-position: 100% 50%;
            }
            100% {
                background-position: 0% 50%;
            }
        }
        
        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            position: fixed;
            left: 0; /* Align to extreme left */
            top: 0; /* Align to top */
            margin: 0; /* Remove any margin */
            height: 90vh;
            transition: 0.3s;
            backdrop-filter: blur(10px);
            z-index: 1000;
        }

        .sidebar-header {
            padding: 20px 0;
            text-align: center;
            border-bottom: 1px solid rgba(238, 238, 238, 0.5);
            margin-bottom: 20px;
        }

        .sidebar-header h2 {
            color: var(--primary-color);
            font-size: 1.5rem;
        }

        .nav-menu {
            list-style: none;
        }

        .nav-item {
            margin-bottom: 10px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: var(--text-color);
            text-decoration: none;
            border-radius: 8px;
            transition: 0.3s;
        }

        .nav-link:hover {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: blue;
            transform: translateX(5px);
        }

        .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        

        .dashboard-header {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            backdrop-filter: blur(10px);
        }

        .welcome-message {
            font-size: 1.5rem;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 10px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .stat-card {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card h3 {
            color: var(--text-color);
            font-size: 1.1rem;
            margin-bottom: 10px;
        }

        .stat-card .value {
            font-size: 2rem;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: bold;
        }

        .recent-activity {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .activity-list {
            list-style: none;
        }

        .activity-item {
            padding: 15px 0;
            border-bottom: 1px solid rgba(238, 238, 238, 0.5);
            transition: background-color 0.3s;
        }

        .activity-item:hover {
            background-color: rgba(67, 97, 238, 0.05);
            padding-left: 10px;
        }

        .activity-item:last-child {
            border-bottom: none;
        }

        .activity-item .time {
            color: #666;
            font-size: 0.9rem;
        }

        /* Footer Styles */
        .footer {
            position: fixed;
            bottom: 0;
            right: 0;
            left: 1.5rem;
            height: var(--footer-height);
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            box-shadow: 0 -2px 5px rgba(0,0,0,0.1);
        }

        .footer-links {
            display: flex;
            gap: 20px;
        }

        .footer-links a {
            color: var(--text-color);
            text-decoration: none;
            font-size: 0.9rem;
        }

        .footer-links a:hover {
            color: var(--primary-color);
        }
        
        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .header {
                left: 0;
            }

            .main-content {
                margin-left: 0;
            }

            .footer {
                left: 0;
            }

            .mobile-toggle {
                display: block;
                position: fixed;
                top: 20px;
                left: 20px;
                z-index: 1001;
                background: var(--primary-color);
                color: white;
                padding: 10px;
                border-radius: 5px;
                cursor: pointer;
            }

            .header-right {
                gap: 10px;
            }

            .user-profile span {
                display: none;
            }
        }
        
        .upload-container {
            background: rgba(255, 255, 255, 0.95);
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .document-list {
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .upload-form {
            max-width: 600px;
            margin: 0 auto;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 500;
        }
        
        .form-group select,
        .form-group input[type="file"] {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .submit-btn {
            background: var(--primary-color);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: 0.3s;
        }
        
        .submit-btn:hover {
            background: var(--secondary-color);
        }
        
        .alert {
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .document-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .document-item:last-child {
            border-bottom: none;
        }
        
        .document-info {
            flex: 1;
        }
        
        .document-actions {
            display: flex;
            gap: 10px;
        }
        
        .action-btn {
            padding: 5px 10px;
            border-radius: 3px;
            cursor: pointer;
            border: none;
            transition: 0.3s;
        }
        
        .view-btn {
            background: #4361ee;
            color: white;
        }
        
        .delete-btn {
            background: #dc3545;
            color: white;
        }
    </style>
</head>
<body>
    
        <!-- Sidebar -->
<div class="sidebar">
    <div class="sidebar-header">
        <h2>Scholar Portal</h2>
    </div>
    <ul class="nav-menu">
        <li class="nav-item">
            <a href="dashboard_primary.php" class="nav-link">
                <i class="fas fa-home"></i>
                Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a href="ward-profile.php" class="nav-link">
                <i class="fas fa-child"></i>
                Ward's Profile
            </a>
        </li>
        <li class="nav-item">
            <a href="upload-result.php" class="nav-link">
                <i class="fas fa-file-upload"></i>
                Upload Results
            </a>
        </li>
        
        <li class="nav-item">
            <a href="support.php" class="nav-link">
                <i class="fas fa-life-ring"></i>
                Support
            </a>
        </li>
                <li class="nav-item">
            <a href="logout.php" class="nav-link">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </a>
        </li>
    </ul>
</div>

    
        
        <div class="main-content">
        <div class="profile-container">
            <div class="profile-header">
                <h1>Ward Profile Management</h1>
                <p>Update and manage your ward's information</p>
            </div>

            <?php if ($message): ?>
                <div class="message <?php echo $message_type; ?>">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="section">
                    <h2 class="section-title">
                        <i class="fas fa-user"></i>
                        Personal Information
                    </h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Ward's Full Name<span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-child"></i>
                                <input type="text" name="ward_name" value="<?php echo htmlspecialchars($ward['ward_name'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Date of Birth<span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-calendar"></i>
                                <input type="date" name="date_of_birth" value="<?php echo htmlspecialchars($ward['date_of_birth'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Current Class<span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-graduation-cap"></i>
                                <input type="text" name="current_class" value="<?php echo htmlspecialchars($ward['current_class'] ?? ''); ?>" required>
                            </div>
                        <div class="form-group">
                    <label for="current_term">Current Term <span clas="required">*</span></label>
                    <div class="input-wrapper">
                    <i class="fas fa-calendar-alt"></i>
                    <select name="current_term" id="current_term" class="form-control" required>
            <option value="" disabled selected>Select Current Term</option>
            <option value="First Term" <?php echo ($ward['current_term'] ?? '') === 'First Term' ? 'selected' : ''; ?>>First Term</option>
            <option value="Second Term" <?php echo ($ward['current_term'] ?? '') === 'Second Term' ? 'selected' : ''; ?>>Second Term</option>
            <option value="Third Term" <?php echo ($ward['current_term'] ?? '') === 'Third Term' ? 'selected' : ''; ?>>Third Term</option>
        </select>
    </div>
</div>
                            </div>
                    </div>
                </div>

                <div class="section">
                    <h2 class="section-title">
                        <i class="fas fa-school"></i>
                        School Information
                    </h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>School Name<span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-building"></i>
                                <input type="text" name="school_name" value="<?php echo htmlspecialchars($ward['school_name'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>School Address<span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-map-marker-alt"></i>
                                <input type="text" name="school_address" value="<?php echo htmlspecialchars($ward['school_address'] ?? ''); ?>" required>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="section">
                    <h2 class="section-title">
                        <i class="fas fa-users"></i>
                        Guardian Information
                    </h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Guardian's Full Name<span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-user"></i>
                                <input type="text" name="guardian_name" value="<?php echo htmlspecialchars($ward['guardian_name'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Guardian's Phone Number<span class="required">*</span></label>
                            <div class="input-wrapper">
                                <i class="fas fa-phone"></i>
                                <input type="tel" name="guardian_phone" value="<?php echo htmlspecialchars($ward['guardian_phone'] ?? ''); ?>" required>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Guardian's Email</label>
                            <div class="input-wrapper">
                                <i class="fas fa-envelope"></i>
                                <input type="email" name="guardian_email" value="<?php echo htmlspecialchars($ward['guardian_email'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <button type="submit" class="save-btn">
                    <i class="fas fa-save"></i>
                    Save Changes
                </button>
            </form>
        </div>
    </div>
    
    <!-- Footer -->
        <footer class="footer">
            <div class="footer-copyright">
                © 2024 Bold Footprint Initiatives. All rights reserved.
            </div>
            <div class="footer-links">
                <a href="/"><i class="fas fa-home me-2"></i>Home</a>
                        <a href="/about-us"><i class="fas fa-info-circle me-2"></i>About Us</a>
                        <a href="/programs"><i class="fas fa-graduation-cap me-2"></i>Programs</a>
            </div>
        </footer>
    <script>
        function deleteDocument(documentId) {
            if (confirm('Are you sure you want to delete this document?')) {
                // Add delete functionality here
                window.location.href = `delete-document.php?id=${documentId}`;
            }
        }
    </script>
    
    <script>
// Add this to your existing JavaScript
document.querySelector('.upload-form').addEventListener('submit', function(e) {
    const fileInput = document.querySelector('#document');
    const submitBtn = document.querySelector('.submit-btn');
    
    if (fileInput.files.length > 0) {
        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
        submitBtn.disabled = true;
    }
});

function updateFileName(input) {
            const fileNameDisplay = document.getElementById('selected-file-name');
            if (input.files.length > 0) {
                fileNameDisplay.textContent = 'Selected file: ' + input.files[0].name;
            } else {
                fileNameDisplay.textContent = '';
            }
        }

        // Drag and drop functionality
        const dropZone = document.querySelector('.file-input-container');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropZone.addEventListener(eventName, highlight, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropZone.addEventListener(eventName, unhighlight, false);
        });

        function highlight(e) {
            dropZone.style.borderColor = '#007bff';
        }

        function unhighlight(e) {
            dropZone.style.borderColor = '#ccc';
        }

        dropZone.addEventListener('drop', handleDrop, false);

        function handleDrop(e) {
            const dt = e.dataTransfer;
            const files = dt.files;
            const fileInput = document.getElementById('result_file');
            
            fileInput.files = files;
            updateFileName(fileInput);
        }
</script>
<script>
        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const phoneInput = document.querySelector('input[name="guardian_phone"]');
            const phonePattern = /^[0-9+\-\s()]*$/;
            
            if (!phonePattern.test(phoneInput.value)) {
                e.preventDefault();
                alert('Please enter a valid phone number');
                phoneInput.focus();
            }
        });
    </script>

</body>
</html>
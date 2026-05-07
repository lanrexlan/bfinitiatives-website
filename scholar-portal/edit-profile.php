<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Initialize variables
$success_message = '';
$error_message = '';
$user_data = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'program' => '',
    'profile_picture' => ''
];

define('PROFILE_PIC_DIR', 'profile-pictures/'); // Assuming you named the folder 'profile_pics';

// Create profile pictures directory if it doesn't exist
if (!file_exists(PROFILE_PIC_DIR)) {
    mkdir(PROFILE_PIC_DIR, 0777, true);
}

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Fetch current user data
    $stmt = $conn->prepare("
        SELECT 
            first_name,
            last_name,
            email,
            program,
            profile_picture,
            created_at
        FROM users 
        WHERE id = :user_id
    ");
    
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $fetched_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($fetched_data) {
        $user_data = array_merge($user_data, $fetched_data);
    }
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['update_profile'])) {
            // Safely get and trim form data with defaults
            $updates = [
                'first_name' => isset($_POST['first_name']) ? trim(strval($_POST['first_name'])) : '',
                'last_name' => isset($_POST['last_name']) ? trim(strval($_POST['last_name'])) : '',
                'email' => isset($_POST['email']) ? trim(strval($_POST['email'])) : '',
                'program' => isset($_POST['program']) ? trim(strval($_POST['program'])) : ''
            ];
            
            // Validate required fields
            if (empty($updates['first_name']) || empty($updates['last_name']) || 
                empty($updates['email']) || empty($updates['program'])) {
                throw new Exception("All fields are required");
            }
            
            // Validate email format
            if (!filter_var($updates['email'], FILTER_VALIDATE_EMAIL)) {
                throw new Exception("Invalid email format");
            }
            
            // Check if email already exists
            $email_check = $conn->prepare("
                SELECT id FROM users 
                WHERE email = :email 
                AND id != :user_id
            ");
            $email_check->execute([
                ':email' => $updates['email'],
                ':user_id' => $_SESSION['user_id']
            ]);
            
            if ($email_check->fetch()) {
                throw new Exception("Email already exists");
            }
            
            // Update user data
            $update_stmt = $conn->prepare("
                UPDATE users 
                SET 
                    first_name = :first_name,
                    last_name = :last_name,
                    email = :email,
                    program = :program,
                    updated_at = CURRENT_TIMESTAMP
                WHERE id = :user_id
            ");
            
            $updates['user_id'] = $_SESSION['user_id'];
            
            if ($update_stmt->execute($updates)) {
                $user_data = array_merge($user_data, $updates);
                $success_message = "Profile updated successfully!";
            } else {
                throw new Exception("Failed to update profile");
            }
        }
        
        // Handle profile picture upload
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['profile_picture'];
    $fileName = uniqid() . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
    $targetPath = PROFILE_PIC_DIR . $fileName;

    // Check file type
    $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
    $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileType, $allowedTypes)) {
        throw new Exception("Only JPG, JPEG, PNG, GIF files are allowed.");
    }

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Update database
        $update_pic_stmt = $conn->prepare("UPDATE users SET profile_picture = :profile_picture WHERE id = :user_id");
        $update_pic_stmt->execute([
            ':profile_picture' => $fileName,
            ':user_id' => $_SESSION['user_id']
        ]);
        
        $user_data['profile_picture'] = $fileName;
        $success_message = "Profile picture updated successfully!";
    } else {
        throw new Exception("Failed to upload profile picture.");
    }
}
        
        $_SESSION['user_data'] = $user_data;
        
        // Handle password change
        if (isset($_POST['current_password']) && !empty($_POST['current_password'])) {
            // Your existing password change code...
        }
    }
    
} catch (Exception $e) {
    error_log("Profile update error: " . $e->getMessage());
    $error_message = $e->getMessage();
}

// Ensure first_name has a value for display
$first_name = $user_data['first_name'] ?: 'User';

// Debug output
error_log("User data: " . print_r($user_data, true));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Scholar Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* Your existing styles */
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        :root {
            --primary-color: #4361ee;
            --secondary-color: #3f37c9;
            --accent-color: #48cae4;
            --text-color: #333;
            --bg-color: #f4f6f8;
            --sidebar-width: 250px;
            --header-height: 70px;
            --footer-height: 60px;
        }

        body {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)),
                        url('/Images/dashboard-bg.jpeg') center/cover fixed;
            color: var(--text-color);
            min-height: 100vh;
        }

        /* Header Styles */
        /* Adjust the header to not block content */
.header {
    position: fixed;
    top: 0;
    right: 0;
    left: var(--sidebar-width);
    height: var(--header-height);
    background: rgba(255, 255, 255, 0.95);
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 30px;
    z-index: 100;
    backdrop-filter: blur(10px);
}

        .header-left {
            display: flex;
            align-items: center;
            gap: 15px;
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
        
        /* Add spacing for the form groups */
.form-group {
    margin-bottom: 20px;
    padding: 0 15px; /* Add horizontal padding */
}

/* Add these new styles for better spacing */
.dashboard-header {
    margin-bottom: 30px; /* Add space below dashboard header */
}

.tab-content {
    padding: 20px 0; /* Add vertical padding to tab content */
}

/* Adjust responsive behavior */
@media (max-width: 768px) {
    .main-content {
        margin-left: 0;
        padding: 20px;
    }
    
    .profile-container {
        margin-top: 80px; /* Increase top margin on mobile */
    }
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
            height: 100vh;
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
            color: white;
            transform: translateX(5px);
        }

        .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        /* Main Content Styles */
        .container {
            display: flex;
            min-height: 100vh;
            padding-bottom: var(--footer-height);
        }

        /* Adjust main content positioning */
        
.main-content {
    flex: 1;
    margin-left: var(--sidebar-width); /* Keep this */
    margin-top: var(--header-height); /* Keep this */
    padding: 30px; /* Increased padding */
    position: relative; /* Add this */
    min-height: calc(100vh - var(--header-height)); /* Add this */
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
            left: var(--sidebar-width);
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
        
        /* Additional styles for edit profile */
        /* Adjust profile container positioning */
.profile-container {
    background: rgba(255, 255, 255, 0.95);
    border-radius: 10px;
    box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    padding: 30px;
    margin-top: 20px; /* Add space below header */
    margin-bottom: 20px;
    backdrop-filter: blur(10px);
    max-width: 800px; /* Optional: limit maximum width */
    margin-left: auto; /* Optional: center the container */
    margin-right: auto; /* Optional: center the container */
}

        .profile-form {
            max-width: 600px;
            margin: 0 auto;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-color);
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }

        .form-group input:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
            outline: none;
        }

        .submit-btn {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            width: 100%;
            margin-top: 20px;
            transition: all 0.3s;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
        }

        .submit-btn:active {
            transform: translateY(0);
        }

        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success {
            background: #d4edda;
            color: #155724;
            border-left: 4px solid #28a745;
        }

        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border-left: 4px solid #dc3545;
        }

        .profile-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .profile-header h1 {
            color: var(--primary-color);
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .profile-header p {
            color: #666;
        }

        /* Field validation styles */
        .form-group input:invalid {
            border-color: #dc3545;
        }

        .form-group .field-hint {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
        }

        /* Loading state */
        .submit-btn.loading {
            background: #ccc;
            cursor: not-allowed;
        }

        .submit-btn.loading i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        /* Additional styles for profile picture and password change */
        /* Adjust profile picture container */
.profile-picture-container {
    text-align: center;
    margin-top: 20px; /* Add space below header */
    margin-bottom: 30px;
    padding-top: 20px; /* Add space at top */
}

        .profile-picture {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            margin-bottom: 15px;
            border: 4px solid var(--primary-color);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .profile-picture-upload {
            display: none;
        }

        .upload-btn {
            background: var(--primary-color);
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-block;
            font-size: 0.9rem;
        }

        .upload-btn:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
        }

        .password-section {
            border-top: 1px solid #eee;
            margin-top: 30px;
            padding-top: 30px;
        }

        .password-requirements {
            font-size: 0.85rem;
            color: #666;
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }

        .password-requirements ul {
            margin: 5px 0;
            padding-left: 20px;
        }

        * Adjust tabs positioning */
.tabs {
    display: flex;
    margin-bottom: 20px;
    border-bottom: 1px solid #eee;
    margin-top: 20px; /* Add space below profile picture */
    padding-top: 10px; /* Add internal spacing */
}

        .tab {
            padding: 10px 20px;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
        }

        .tab.active {
            border-bottom-color: var(--primary-color);
            color: var(--primary-color);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }
    </style>
</head>
<body>
    <!-- Your existing sidebar and header -->
    
     <!-- Header -->
        <!-- Header -->
<header class="header">
    <div class="header-left">
        <div class="search-bar">
            <input type="search" placeholder="Search..." style="padding: 8px; border-radius: 20px; border: 1px solid #ddd;">
        </div>
    </div>
    <div class="header-right">
        <div class="notification-bell">
            <i class="fas fa-bell"></i>
            <span class="notification-badge">3</span>
        </div>
        <div class="user-profile">
    <div class="user-avatar">
        <?php echo strtoupper(substr($first_name ?? 'U', 0, 1)); ?>
    </div>
    <span><?php echo htmlspecialchars($first_name ?? 'User'); ?></span>
</div>
    </div>
</header>
        
         <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <h2>Scholar Portal</h2>
            </div>
            <ul class="nav-menu">
                <li class="nav-item">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-home"></i>
                        Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a href="profile.php" class="nav-link">
                        <i class="fas fa-user"></i>
                        Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a href="scholarship-status.php" class="nav-link">
                        <i class="fas fa-graduation-cap"></i>
                        Scholarship Status
                    </a>
                </li>
                <li class="nav-item">
                    <a href="upload-document.php" class="nav-link">
                        <i class="fas fa-file-alt"></i>
                        Documents
                    </a>
                </li>
                <li class="nav-item">
                    <a href="notifications.php" class="nav-link">
                        <i class="fas fa-bell"></i>
                        Notifications
                    </a>
                </li>
                <li class="nav-item">
                    <a href="settings.php" class="nav-link">
                        <i class="fas fa-cog"></i>
                        Settings
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
    
    <div class="profile-container">
        <!-- Profile Picture Section -->
        <div class="profile-picture-container">
            <img src="<?php echo !empty($user_data['profile_picture']) ? htmlspecialchars($user_data['profile_picture']) : 'images/default-profile.png'; ?>" 
                 alt="Profile Picture" 
                 class="profile-picture" 
                 id="profilePicture">
            <label for="profilePictureUpload" class="upload-btn">
                <i class="fas fa-camera"></i> Change Picture
            </label>
            <input type="file" 
                   id="profilePictureUpload" 
                   name="profile_picture" 
                   class="profile-picture-upload" 
                   accept="image/*">
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <div class="tab active" data-tab="profile">Profile Information</div>
            <div class="tab" data-tab="password">Change Password</div>
        </div>

        <!-- Profile Information Tab -->
        <div class="tab-content active" id="profileTab">
            <form class="profile-form" method="POST" id="profileForm">
                <input type="hidden" name="update_profile" value="1">
                <!-- Your existing profile form fields -->
                <div class="form-group">
    <label for="first_name">First Name</label>
    <input type="text" id="first_name" name="first_name" 
           value="<?php echo htmlspecialchars($user_data['first_name']); ?>" 
           required>
</div>

<div class="form-group">
    <label for="last_name">Last Name</label>
    <input type="text" id="last_name" name="last_name" 
           value="<?php echo htmlspecialchars($user_data['last_name']); ?>" 
           required>
</div>

<div class="form-group">
    <label for="email">Email Address</label>
    <input type="email" id="email" name="email" 
           value="<?php echo htmlspecialchars($user_data['email']); ?>" 
           required>
    <div class="field-hint">This will be used for all communications</div>
</div>

<div class="form-group">
    <label for="program">Program</label>
    <input type="text" id="program" name="program" 
           value="<?php echo htmlspecialchars($user_data['program']); ?>" 
           required>
</div>
                
                
                <form class="profile-form" method="POST" id="profileForm">
    <input type="hidden" name="update_profile" value="1">
    <!-- Your form fields here -->
    <button type="submit" class="submit-btn" id="profileSubmitBtn">
        <i class="fas fa-save"></i> Save Changes
    </button>
</form>
            </form>
        </div>

        <!-- Password Change Tab -->
        <div class="tab-content" id="passwordTab">
            <form class="profile-form" method="POST" id="passwordForm">
                <div class="form-group">
                    <label for="current_password">Current Password</label>
                    <input type="password" 
                           id="current_password" 
                           name="current_password" 
                           required>
                </div>

                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" 
                           id="new_password" 
                           name="new_password" 
                           required>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" 
                           id="confirm_password" 
                           name="confirm_password" 
                           required>
                </div>

                <div class="password-requirements">
                    Password must:
                    <ul>
                        <li>Be at least 8 characters long</li>
                        <li>Include at least one uppercase letter</li>
                        <li>Include at least one number</li>
                        <li>Include at least one special character</li>
                    </ul>
                </div>

                <button type="submit" class="submit-btn" id="passwordSubmitBtn">
                    <i class="fas fa-key"></i> Update Password
                </button>
            </form>
        </div>
    </div>

    <script>
        // Profile picture preview
        document.getElementById('profilePictureUpload').addEventListener('change', function(e) {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profilePicture').src = e.target.result;
                };
                reader.readAsDataURL(this.files[0]);
                
                // Automatically submit the form when a new picture is selected
                const formData = new FormData();
                formData.append('profile_picture', this.files[0]);
                
                fetch('edit-profile.php', {
                    method: 'POST',
                    body: formData
                }).then(response => response.text())
                  .then(data => {
                      if (data.includes('success')) {
                          // Show success message
                      }
                  });
            }
        });

        // Tab switching
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs and contents
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                
                // Add active class to clicked tab and corresponding content
                this.classList.add('active');
                document.getElementById(this.dataset.tab + 'Tab').classList.add('active');
            });
        });
            
            document.getElementById('profilePictureUpload').addEventListener('change', function(e) {
    if (this.files && this.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('profilePicture').src = e.target.result;
        };
        reader.readAsDataURL(this.files[0]);
        
        // Automatically submit the form when a new picture is selected
        const formData = new FormData();
        formData.append('profile_picture', this.files[0]);
        
        fetch('edit-profile.php', {
            method: 'POST',
            body: formData
        }).then(response => response.text())
          .then(data => {
              if (data.includes('success')) {
                  alert('Profile picture updated successfully!');
              }
          });
    }
});
            
            
        // Password validation
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (newPassword !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return;
            }
            
            if (newPassword.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                return;
            }
            
            if (!/[A-Z]/.test(newPassword)) {
                e.preventDefault();
                alert('Password must include at least one uppercase letter!');
                return;
            }
            
            if (!/[0-9]/.test(newPassword)) {
                e.preventDefault();
                alert('Password must include at least one number!');
                return;
            }
            
            if (!/[!@#$%^&*]/.test(newPassword)) {
                e.preventDefault();
                alert('Password must include at least one special character!');
                return;
            }
        });
        document.getElementById('profileForm').addEventListener('submit', function(e) {
    // Your validation here if any, but ensure it doesn't always preventDefault
    // e.preventDefault(); // Comment this out if it's there without condition
});
    </script>
</body>
</html>
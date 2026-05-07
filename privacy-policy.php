<?php
session_start();

// Initialize default values
$first_name = '';
$profile_picture = null;

// Get user data if logged in
if (isset($_SESSION['user_id'])) {
    try {
        require_once 'includes/config.php';
        require_once 'includes/db.php';
        
        $db = new Database();
        $conn = $db->getConnection();
        
        // Get user details
        $stmt = $conn->prepare("
            SELECT 
                first_name, 
                profile_picture
            FROM users 
            WHERE id = :user_id
        ");
        
        $stmt->execute([':user_id' => $_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $first_name = $user['first_name'];
            $profile_picture = $user['profile_picture'] ?? null;
        }
    } catch (Exception $e) {
        error_log("Privacy policy page error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Privacy Policy - Scholar Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Base styles - matching dashboard */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }
        
        :root {
            --primary: #4361ee;
            --primary-light: #4895ef;
            --primary-dark: #3a0ca3;
            --secondary: #8ecae6;
            --accent: #f72585;
            --success: #4cc9f0;
            --warning: #ffbe0b;
            --danger: #d00000;
            --dark: #212529;
            --light: #f8f9fa;
            --gray: #adb5bd;
            --white: #ffffff;
            
            --shadow-sm: 0 .125rem .25rem rgba(0,0,0,.075);
            --shadow: 0 .5rem 1rem rgba(0,0,0,.15);
            --shadow-lg: 0 1rem 3rem rgba(0,0,0,.175);
            
            --gradient-primary: linear-gradient(120deg, #4361ee, #4cc9f0);
            --gradient-accent: linear-gradient(120deg, #f72585, #ff9e00);
            
            --sidebar-width: 280px;
            --header-height: 70px;
            --footer-height: 60px;
            
            --border-radius-sm: 0.5rem;
            --border-radius: 1rem;
            --border-radius-lg: 1.5rem;
        }

        body {
            background-color: #f3f4f9;
            color: var(--dark);
            line-height: 1.6;
            overflow-x: hidden;
        }

        /* Animated background - matching dashboard */
        .bg-animated {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: -1;
            background: linear-gradient(135deg, rgba(73, 86, 238, 0.05) 0%, rgba(72, 149, 239, 0.05) 100%);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
        }

        @keyframes gradientBG {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .bg-animated::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: url("data:image/svg+xml,%3Csvg width='100' height='100' viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M11 18c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm48 25c3.866 0 7-3.134 7-7s-3.134-7-7-7-7 3.134-7 7 3.134 7 7 7zm-43-7c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm63 31c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM34 90c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zm56-76c1.657 0 3-1.343 3-3s-1.343-3-3-3-3 1.343-3 3 1.343 3 3 3zM12 86c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm28-65c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm23-11c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-6 60c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm29 22c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zM32 63c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm57-13c2.76 0 5-2.24 5-5s-2.24-5-5-5-5 2.24-5 5 2.24 5 5 5zm-9-21c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM60 91c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM35 41c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2zM12 60c1.105 0 2-.895 2-2s-.895-2-2-2-2 .895-2 2 .895 2 2 2z' fill='%234361ee' fill-opacity='0.02' fill-rule='evenodd'/%3E%3C/svg%3E");
            opacity: 0.6;
        }

        /* Main wrapper */
        .app-wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Mobile Toggle Button */
        .mobile-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1100;
            background: white;
            color: var(--primary);
            padding: 12px;
            border-radius: 50%;
            cursor: pointer;
            border: none;
            box-shadow: var(--shadow);
            width: 46px;
            height: 46px;
            transition: all 0.3s;
        }

        .mobile-toggle:hover {
            transform: rotate(90deg);
        }

        .mobile-toggle i {
            font-size: 1.2rem;
        }

        /* User Avatar Styles */
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            background: var(--gradient-primary);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-sm);
            transition: transform 0.3s;
        }

        .user-avatar:hover {
            transform: scale(1.1);
        }

        .profile-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }

        .avatar-initials {
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
            text-transform: uppercase;
        }

        .fallback-avatar {
            background: var(--gradient-primary);
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* Sidebar Overlay */
        .sidebar-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 999;
            backdrop-filter: blur(5px);
            opacity: 0;
            transition: opacity 0.3s;
        }

        .sidebar-overlay.active {
            display: block;
            opacity: 1;
        }

        /* Header Styles */
        .header {
            position: fixed;
            top: 0;
            right: 0;
            left: <?php echo isset($_SESSION['user_id']) ? 'var(--sidebar-width)' : '0'; ?>;
            height: var(--header-height);
            background: rgba(255, 255, 255, 0.95);
            box-shadow: var(--shadow-sm);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 30px;
            z-index: 100;
            backdrop-filter: blur(10px);
            transition: all 0.3s;
        }

        .header-left {
            display: flex;
            align-items: center;
        }

        .header-title {
            font-weight: 600;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin: 0;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: var(--border-radius);
            transition: all 0.3s;
        }

        .user-profile:hover {
            background: rgba(67, 97, 238, 0.1);
        }

        .user-name {
            font-weight: 500;
            font-size: 0.95rem;
            white-space: nowrap;
        }

        /* Sidebar Styles - only if logged in */
        <?php if (isset($_SESSION['user_id'])): ?>
        .sidebar {
            width: var(--sidebar-width);
            background: white;
            box-shadow: var(--shadow);
            padding: 20px;
            position: fixed;
            left: 0;
            top: 0;
            margin: 0;
            height: 100vh;
            transition: transform 0.3s ease;
            z-index: 1000;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .sidebar-header {
            padding: 20px 0;
            text-align: center;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            margin-bottom: 20px;
        }

        .sidebar-header h2 {
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: 700;
            font-size: 1.8rem;
            margin: 0;
        }

        .nav-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .nav-item {
            margin-bottom: 5px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: var(--dark);
            text-decoration: none;
            border-radius: var(--border-radius);
            transition: all 0.3s;
            font-weight: 500;
        }

        .nav-link:hover, .nav-link.active {
            background: var(--gradient-primary);
            color: white;
            transform: translateX(5px);
            box-shadow: var(--shadow-sm);
        }

        .nav-link i {
            margin-right: 12px;
            font-size: 1.1rem;
            width: 20px;
            text-align: center;
            transition: transform 0.3s;
        }

        .nav-link:hover i, .nav-link.active i {
            transform: scale(1.2);
        }
        <?php endif; ?>

        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: <?php echo isset($_SESSION['user_id']) ? 'var(--sidebar-width)' : '0'; ?>;
            margin-top: var(--header-height);
            padding: 25px;
            width: <?php echo isset($_SESSION['user_id']) ? 'calc(100% - var(--sidebar-width))' : '100%'; ?>;
            transition: all 0.3s;
        }

        /* Welcome section - matching dashboard */
        .welcome-section {
            border-radius: var(--border-radius);
            margin-bottom: 25px;
            overflow: hidden;
            position: relative;
            background: var(--white);
            box-shadow: var(--shadow);
        }

        .welcome-content {
            padding: 25px;
            position: relative;
            z-index: 1;
        }

        .welcome-background {
            position: absolute;
            top: 0;
            right: 0;
            width: 40%;
            height: 100%;
            background: var(--gradient-primary);
            clip-path: polygon(20% 0%, 100% 0%, 100% 100%, 0% 100%);
            opacity: 0.1;
        }

        .welcome-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 10px;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .welcome-subtitle {
            color: var(--gray);
            margin-bottom: 20px;
        }

        /* Privacy policy content */
        .privacy-container {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            padding: 35px;
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }

        .privacy-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
        }

        .privacy-section {
            margin-bottom: 35px;
            padding-bottom: 25px;
            border-bottom: 1px solid rgba(67, 97, 238, 0.1);
        }

        .privacy-section:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .privacy-section h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 20px;
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .privacy-section h3 {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--dark);
        }

        .privacy-section h2 i {
            font-size: 1.2rem;
            width: 24px;
            height: 24px;
            background: rgba(67, 97, 238, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.9rem;
        }

        .privacy-text {
            font-size: 1rem;
            line-height: 1.8;
            color: var(--dark);
            margin-bottom: 15px;
        }

        .privacy-list {
            list-style: none;
            padding: 0;
            margin: 15px 0;
        }

        .privacy-list li {
            display: flex;
            align-items: flex-start;
            margin-bottom: 10px;
            padding-left: 0;
        }

        .privacy-list li::before {
            content: "•";
            color: var(--primary);
            font-weight: bold;
            margin-right: 10px;
            margin-top: 2px;
        }

        .highlight-box {
            background: rgba(67, 97, 238, 0.05);
            border-left: 4px solid var(--primary);
            padding: 20px;
            border-radius: 0 var(--border-radius-sm) var(--border-radius-sm) 0;
            margin: 20px 0;
        }

        .contact-info {
            background: rgba(76, 201, 240, 0.05);
            border-left: 4px solid var(--success);
            padding: 20px;
            border-radius: 0 var(--border-radius-sm) var(--border-radius-sm) 0;
            margin: 20px 0;
        }

        .contact-info h4 {
            color: var(--success);
            margin-bottom: 15px;
            font-weight: 600;
        }

        .contact-item {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            color: var(--dark);
        }

        .contact-item i {
            margin-right: 10px;
            color: var(--success);
            width: 20px;
        }

        .contact-item a {
            color: var(--primary);
            text-decoration: none;
            transition: all 0.3s;
        }

        .contact-item a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        /* Footer */
        .footer {
            margin-top: auto;
            margin-left: <?php echo isset($_SESSION['user_id']) ? 'var(--sidebar-width)' : '0'; ?>;
            background: white;
            padding: 20px 30px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: var(--shadow-sm);
            transition: all 0.3s;
        }

        .footer-copyright {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .footer-links {
            display: flex;
            gap: 20px;
        }

        .footer-links a {
            color: var(--gray);
            text-decoration: none;
            font-size: 0.9rem;
            transition: all 0.3s;
        }

        .footer-links a:hover {
            color: var(--primary);
        }

        .footer-links i {
            margin-right: 5px;
        }

        /* RESPONSIVE STYLES */
        @media (max-width: 768px) {
            :root {
                --header-height: 60px;
            }
            
            /* Show mobile toggle */
            .mobile-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
            }

            <?php if (isset($_SESSION['user_id'])): ?>
            /* Sidebar modifications */
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
                z-index: 1000;
            }

            .sidebar.active {
                transform: translateX(0);
            }
            <?php endif; ?>

            /* Header modifications */
            .header {
                left: 0;
                padding: 0 20px;
            }

            .header-left {
                display: none;
            }

            .user-name {
                display: none;
            }

            /* Main content modifications */
            .main-content {
                margin-left: 0;
                padding: 20px 15px;
                width: 100%;
            }

            /* Footer modifications */
            .footer {
                margin-left: 0;
                padding: 15px;
                flex-direction: column;
                gap: 10px;
                text-align: center;
            }

            .footer-links {
                flex-wrap: wrap;
                justify-content: center;
            }
            
            .privacy-container {
                padding: 25px 20px;
            }
        }

        @media (max-width: 576px) {
            .mobile-toggle {
                top: 8px;
                left: 8px;
                width: 40px;
                height: 40px;
                padding: 10px;
            }
            
            .welcome-title {
                font-size: 1.5rem;
            }
            
            .privacy-container {
                padding: 20px 15px;
            }
            
            .privacy-section h2 {
                font-size: 1.3rem;
            }
            
            .privacy-section h3 {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-animated"></div>

    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay"></div>
    
    <div class="app-wrapper">
        <!-- Mobile Menu Toggle -->
        <?php if (isset($_SESSION['user_id'])): ?>
        <button class="mobile-toggle">
            <i class="fas fa-bars"></i>
        </button>
        <?php endif; ?>

        <!-- Header -->
        <header class="header">
            <div class="header-left">
                <h2 class="header-title">Privacy Policy</h2>
            </div>
            
            <div class="header-right">
                <?php if (isset($_SESSION['user_id'])): ?>
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php if ($profile_picture): ?>
                            <img src="<?php echo htmlspecialchars('./uploads/profile_pictures/' . $profile_picture); ?>" 
                                alt="Profile Picture"
                                class="profile-image"
                                onerror="this.onerror=null; this.src='/Images/success-stories/default-avatar.png'; this.classList.add('fallback-avatar');">
                        <?php else: ?>
                            <div class="avatar-initials">
                                <?php echo strtoupper(substr($first_name, 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <span class="user-name"><?php echo htmlspecialchars($first_name); ?></span>
                </div>
                <?php else: ?>
                <a href="login.php" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt me-2"></i>Login
                </a>
                <?php endif; ?>
            </div>
        </header>
    
        <?php if (isset($_SESSION['user_id'])): ?>
        <!-- Sidebar - Scholar Portal -->
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
                        My Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a href="documents.php" class="nav-link">
                        <i class="fas fa-file-alt"></i>
                        My Documents
                    </a>
                </li>
                <li class="nav-item">
                    <a href="scholarships.php" class="nav-link">
                        <i class="fas fa-graduation-cap"></i>
                        Scholarships
                    </a>
                </li>
                <li class="nav-item">
                    <a href="mentors.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        Mentors
                    </a>
                </li>
                <li class="nav-item">
                    <a href="applications.php" class="nav-link">
                        <i class="fas fa-clipboard-list"></i>
                        Applications
                    </a>
                </li>
                <li class="nav-item">
                    <a href="resources.php" class="nav-link">
                        <i class="fas fa-book"></i>
                        Resources
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
        <?php endif; ?>

        <!-- Main Content -->
        <div class="main-content">
            <!-- Welcome Section -->
            <div class="welcome-section">
                <div class="welcome-background"></div>
                <div class="welcome-content">
                    <h1 class="welcome-title">Privacy Policy</h1>
                    <p class="welcome-subtitle">Your privacy and data protection are our top priorities at Bold Footprint Initiatives</p>
                    <p class="text-muted"><small>Last updated: December 2024</small></p>
                </div>
            </div>

            <!-- Privacy Policy Content -->
            <div class="privacy-container">
                <div class="privacy-section">
                    <h2><i class="fas fa-info-circle"></i>Introduction</h2>
                    <p class="privacy-text">
                        Bold Footprint Initiatives ("we," "our," or "us") is committed to protecting your privacy and ensuring the security of your personal information. This Privacy Policy explains how we collect, use, disclose, and safeguard your information when you use our Scholar Portal and related services.
                    </p>
                    <div class="highlight-box">
                        <strong>Our Commitment:</strong> We are dedicated to transparency in how we handle your data and ensuring compliance with applicable data protection laws.
                    </div>
                </div>

                <div class="privacy-section">
                    <h2><i class="fas fa-database"></i>Information We Collect</h2>
                    
                    <h3>Personal Information You Provide</h3>
                    <p class="privacy-text">When you register for our services, we may collect:</p>
                    <ul class="privacy-list">
                        <li>Name, email address, and contact information</li>
                        <li>Educational background and academic records</li>
                        <li>Profile information and photographs</li>
                        <li>Application documents and personal statements</li>
                        <li>Communication preferences and settings</li>
                    </ul>

                    <h3>Automatically Collected Information</h3>
                    <p class="privacy-text">We may automatically collect certain information when you use our services:</p>
                    <ul class="privacy-list">
                        <li>Device information and IP addresses</li>
                        <li>Browser type and operating system</li>
                        <li>Usage patterns and preferences</li>
                        <li>Login activity and session data</li>
                    </ul>
                </div>

                <div class="privacy-section">
                    <h2><i class="fas fa-cogs"></i>How We Use Your Information</h2>
                    <p class="privacy-text">We use your information to:</p>
                    <ul class="privacy-list">
                        <li>Provide and maintain our scholarship portal services</li>
                        <li>Connect you with mentors and educational opportunities</li>
                        <li>Send important updates about your applications</li>
                        <li>Improve our services and user experience</li>
                        <li>Comply with legal obligations and protect our rights</li>
                        <li>Prevent fraud and ensure platform security</li>
                    </ul>
                </div>

                <div class="privacy-section">
                    <h2><i class="fas fa-share-alt"></i>Information Sharing and Disclosure</h2>
                    <p class="privacy-text">We may share your information in the following circumstances:</p>
                    
                    <h3>With Your Consent</h3>
                    <p class="privacy-text">We share information when you explicitly agree to such sharing, such as connecting with mentors or sharing success stories.</p>
                    
                    <h3>Service Providers</h3>
                    <p class="privacy-text">We may share information with trusted third-party service providers who assist us in operating our platform, subject to confidentiality agreements.</p>
                    
                    <h3>Legal Requirements</h3>
                    <p class="privacy-text">We may disclose information when required by law or to protect our rights, users, or the public.</p>
                    
                    <div class="highlight-box">
                        <strong>Important:</strong> We never sell your personal information to third parties for commercial purposes.
                    </div>
                </div>

                <div class="privacy-section">
                    <h2><i class="fas fa-shield-alt"></i>Data Security</h2>
                    <p class="privacy-text">We implement appropriate technical and organizational measures to protect your personal information:</p>
                    <ul class="privacy-list">
                        <li>Encryption of data in transit and at rest</li>
                        <li>Regular security audits and vulnerability assessments</li>
                        <li>Access controls and authentication measures</li>
                        <li>Employee training on data protection practices</li>
                        <li>Incident response procedures for security breaches</li>
                    </ul>
                </div>

                <div class="privacy-section">
                    <h2><i class="fas fa-user-check"></i>Your Rights and Choices</h2>
                    <p class="privacy-text">You have several rights regarding your personal information:</p>
                    
                    <h3>Access and Portability</h3>
                    <p class="privacy-text">You can request a copy of your personal information and download your data from your account settings.</p>
                    
                    <h3>Correction and Updates</h3>
                    <p class="privacy-text">You can update your profile information at any time through your account dashboard.</p>
                    
                    <h3>Deletion</h3>
                    <p class="privacy-text">You can request deletion of your account and associated data, subject to certain legal retention requirements.</p>
                    
                    <h3>Communication Preferences</h3>
                    <p class="privacy-text">You can control how and when we communicate with you through your notification settings.</p>
                </div>

                <div class="privacy-section">
                    <h2><i class="fas fa-cookie-bite"></i>Cookies and Tracking Technologies</h2>
                    <p class="privacy-text">We use cookies and similar technologies to:</p>
                    <ul class="privacy-list">
                        <li>Remember your login preferences and settings</li>
                        <li>Analyze usage patterns to improve our services</li>
                        <li>Provide personalized content and recommendations</li>
                        <li>Ensure platform security and prevent fraud</li>
                    </ul>
                    <p class="privacy-text">You can control cookie settings through your browser preferences.</p>
                </div>

                <div class="privacy-section">
                    <h2><i class="fas fa-clock"></i>Data Retention</h2>
                    <p class="privacy-text">We retain your personal information for as long as necessary to:</p>
                    <ul class="privacy-list">
                        <li>Provide our services to you</li>
                        <li>Comply with legal obligations</li>
                        <li>Resolve disputes and enforce agreements</li>
                        <li>Support ongoing mentorship relationships</li>
                    </ul>
                    <p class="privacy-text">When data is no longer needed, we securely delete or anonymize it.</p>
                </div>

                <div class="privacy-section">
                    <h2><i class="fas fa-globe"></i>International Data Transfers</h2>
                    <p class="privacy-text">As a global platform connecting students worldwide, we may transfer your information across borders. We ensure appropriate safeguards are in place for such transfers, including:</p>
                    <ul class="privacy-list">
                        <li>Adequacy decisions by relevant authorities</li>
                        <li>Standard contractual clauses</li>
                        <li>Certification schemes and codes of conduct</li>
                    </ul>
                </div>

                <div class="privacy-section">
                    <h2><i class="fas fa-baby"></i>Children's Privacy</h2>
                    <p class="privacy-text">Our services are not intended for children under 16 years of age. We do not knowingly collect personal information from children under 16. If you believe we have collected information from a child under 16, please contact us immediately.</p>
                </div>

                <div class="privacy-section">
                    <h2><i class="fas fa-edit"></i>Changes to This Privacy Policy</h2>
                    <p class="privacy-text">We may update this Privacy Policy from time to time. We will notify you of any material changes by:</p>
                    <ul class="privacy-list">
                        <li>Posting the updated policy on our website</li>
                        <li>Sending you an email notification</li>
                        <li>Providing in-app notifications</li>
                    </ul>
                    <p class="privacy-text">Your continued use of our services after such changes constitutes acceptance of the updated policy.</p>
                </div>

                <div class="privacy-section">
                    <h2><i class="fas fa-envelope"></i>Contact Us</h2>
                    <p class="privacy-text">If you have any questions about this Privacy Policy or our privacy practices, please contact us:</p>
                    
                    <div class="contact-info">
                        <h4>Bold Footprint Initiatives</h4>
                        <div class="contact-item">
                            <i class="fas fa-envelope"></i>
                            <span>Email: <a href="mailto:privacy@brightfuture.org">privacy@brightfuture.org</a></span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-globe"></i>
                            <span>Website: <a href="https://brightfuture.org" target="_blank">brightfuture.org</a></span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Address: [Your Organization Address]</span>
                        </div>
                        <div class="contact-item">
                            <i class="fas fa-phone"></i>
                            <span>Phone: [Your Contact Number]</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="footer">
            <div class="footer-copyright">
                © 2024 Bold Footprint Initiatives. All rights reserved.
            </div>
            <div class="footer-links">
                <a href="index.html"><i class="fas fa-home"></i>Home</a>
                <a href="/about.html"><i class="fas fa-info-circle"></i>About Us</a>
                <a href="/programs.html"><i class="fas fa-graduation-cap"></i>Programs</a>
                <a href="/contact.html"><i class="fas fa-envelope"></i>Contact</a>
            </div>
        </footer>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['user_id'])): ?>
            const mobileToggle = document.querySelector('.mobile-toggle');
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            const body = document.body;
            
            // Toggle sidebar
            function toggleSidebar() {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
                body.classList.toggle('sidebar-open');
                
                if (sidebar.classList.contains('active')) {
                    body.style.overflow = 'hidden';
                } else {
                    body.style.overflow = '';
                }
            }

            // Event listeners
            mobileToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleSidebar();
            });

            overlay.addEventListener('click', toggleSidebar);

            // Handle window resize
            let resizeTimer;
            window.addEventListener('resize', function() {
                clearTimeout(resizeTimer);
                resizeTimer = setTimeout(function() {
                    if (window.innerWidth > 768 && sidebar.classList.contains('active')) {
                        toggleSidebar();
                    }
                }, 250);
            });
            <?php endif; ?>
        });
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
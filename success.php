<?php
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/Images/bfi-new-logo.svg">
    <link rel="shortcut icon" href="/Images/bfi-new-logo.svg">
    <title>Application Submitted - Bold Footprint Initiatives</title>
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,400;0,500;0,600;1,400&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        :root {
            --primary: #2c5282;
            --primary-light: #3b82f6;
            --primary-dark: #1e3a8a;
            --secondary: #f59e0b;
            --light: #f8fafc;
            --dark: #1e293b;
            --success: #10b981;
            --success-light: #d1fae5;
            --success-dark: #059669;
            --danger: #ef4444;
            --warning: #f59e0b;
            --info: #3b82f6;
            --gray: #64748b;
            --gray-light: #e2e8f0;
            --border-radius: 15px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Poppins', sans-serif;
            color: var(--dark);
            line-height: 1.6;
            overflow-x: hidden;
            background-color: var(--light);
        }
        
        h1, h2, h3, h4, h5 {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700;
            line-height: 1.3;
        }
        
        img {
            max-width: 100%;
        }
        
        a {
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
        }
        
        ul {
            list-style: none;
        }
        
        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 28px;
            border-radius: 30px;
            font-weight: 600;
            text-align: center;
            transition: all 0.3s ease;
            border: 2px solid transparent;
            font-family: 'Poppins', sans-serif;
            cursor: pointer;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .btn-primary:hover {
            background-color: transparent;
            color: var(--primary);
        }
        
        .btn-success {
            background-color: var(--success);
            color: white;
            border-color: var(--success);
        }
        
        .btn-success:hover {
            background-color: transparent;
            color: var(--success);
        }
        
        .btn-outlined {
            background-color: transparent;
            color: var(--primary);
            border-color: var(--primary);
        }
        
        .btn-outlined:hover {
            background-color: var(--primary);
            color: white;
        }
        
        /* Header */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            z-index: 1000;
            transition: all 0.4s ease;
            background-color: white;
            box-shadow: 0 2px 20px rgba(0, 0, 0, 0.1);
        }
        
        .nav-wrapper {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 0;
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            z-index: 1001;
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            background-color: white;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-size: 20px;
        }
        
        .logo-text {
            font-weight: 700;
            font-size: 22px;
            color: var(--primary);
        }
        
        .nav-menu {
            display: flex;
            gap: 30px;
        }
        
        .nav-link {
            font-weight: 500;
            position: relative;
            padding: 5px 0;
            color: var(--dark);
        }
        
        .nav-link:hover {
            color: var(--secondary);
        }
        
        .nav-link::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background-color: var(--secondary);
            transition: width 0.3s ease;
        }
        
        .nav-link:hover::after {
            width: 100%;
        }
        
        .nav-link.active {
            color: var(--secondary);
        }
        
        .nav-link.active::after {
            width: 100%;
        }
        
        .menu-toggle {
            display: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--primary);
            z-index: 1001;
        }
        
        /* Mobile Menu */
        .mobile-menu-panel {
            position: fixed;
            top: 0;
            right: -100%;
            width: 300px;
            height: 100vh;
            background-color: white;
            z-index: 1000;
            padding: 80px 40px 40px;
            transition: right 0.4s ease;
            box-shadow: -10px 0 30px rgba(0, 0, 0, 0.1);
            overflow-y: auto;
        }
        
        .mobile-menu-panel.active {
            right: 0;
        }
        
        .mobile-menu {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .mobile-link {
            font-weight: 500;
            color: var(--dark);
            padding: 10px 0;
            border-bottom: 1px solid var(--gray-light);
            display: block;
        }
        
        .mobile-link:hover {
            color: var(--primary);
        }
        
        .mobile-link.active {
            color: var(--primary);
        }
        
        .mobile-buttons {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 30px;
        }
        
        .menu-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.7);
            z-index: 999;
            opacity: 0;
            visibility: hidden;
            transition: all 0.4s ease;
        }
        
        .menu-overlay.active {
            opacity: 1;
            visibility: visible;
        }
        
        /* Success Page */
        .page-hero {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.9), rgba(5, 150, 105, 0.85)), url('/Images/scholarship-bg.jpeg');
            min-height: 30vh;
            display: flex;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            align-items: center;
            position: relative;
            overflow: hidden;
            margin-bottom: 60px;
        }
        
        .page-hero-content {
            color: white;
            position: relative;
            z-index: 2;
            padding: 120px 0 60px;
            text-align: center;
        }
        
        .page-hero-title {
            font-size: 2.5rem;
            margin-bottom: 20px;
            animation: fadeInUp 1s ease;
        }
        
        .page-hero-subtitle {
            font-size: 1.1rem;
            max-width: 700px;
            margin: 0 auto;
            opacity: 0;
            animation: fadeInUp 1s ease 0.3s forwards;
        }
        
        .page-hero-shape {
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 150px;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="white" fill-opacity="1" d="M0,160L48,149.3C96,139,192,117,288,122.7C384,128,480,160,576,170.7C672,181,768,171,864,154.7C960,139,1056,117,1152,117.3C1248,117,1344,139,1392,149.3L1440,160L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>');
            background-size: cover;
            z-index: 1;
        }
        
        .success-section {
            padding: 0 0 80px;
        }
        
        .success-card {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            padding: 40px;
            margin-bottom: 30px;
            transition: all 0.3s ease;
            animation: fadeInUp 1s ease;
        }
        
        .success-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }
        
        .success-icon {
            width: 100px;
            height: 100px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
            color: var(--success);
            font-size: 48px;
            position: relative;
            z-index: 1;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
            }
            70% {
                box-shadow: 0 0 0 15px rgba(16, 185, 129, 0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(16, 185, 129, 0);
            }
        }
        
        .success-title {
            font-size: 2rem;
            margin-bottom: 20px;
            color: var(--dark);
            text-align: center;
        }
        
        .alert {
            padding: 20px;
            border-radius: var(--border-radius);
            margin-bottom: 30px;
            position: relative;
            overflow: hidden;
        }
        
        .alert-success {
            background-color: var(--success-light);
            color: var(--success-dark);
            border-left: 6px solid var(--success);
        }
        
        .application-id {
            background: var(--light);
            padding: 20px;
            border-radius: var(--border-radius);
            margin: 25px 0;
            font-family: 'Courier New', monospace;
            font-size: 1.25rem;
            font-weight: 600;
            letter-spacing: 1px;
            border: 2px dashed rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-wrap: wrap;
            gap: 10px;
            animation: fadeIn 1s ease 0.5s forwards;
            opacity: 0;
        }
        
        .application-id span {
            background: white;
            padding: 8px 15px;
            border-radius: 50px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.08);
        }
        
        .info-section {
            background: var(--primary-light);
            background-color: rgba(59, 130, 246, 0.1);
            padding: 25px;
            border-radius: var(--border-radius);
            margin: 25px 0;
            text-align: left;
            animation: fadeIn 1s ease 0.8s forwards;
            opacity: 0;
        }
        
        .info-section h3 {
            display: flex;
            align-items: center;
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 15px;
            color: var(--primary-dark);
        }
        
        .info-section h3 i {
            margin-right: 10px;
        }
        
        .info-section ul {
            padding-left: 25px;
            margin-bottom: 0;
        }
        
        .info-section li {
            margin-bottom: 10px;
            position: relative;
        }
        
        .info-section li::before {
            content: "";
            position: absolute;
            left: -25px;
            top: 8px;
            width: 10px;
            height: 10px;
            border-radius: 50%;
            background: var(--primary);
        }
        
        .buttons-container {
            margin-top: 30px;
            display: flex;
            justify-content: center;
            flex-wrap: wrap;
            gap: 15px;
            animation: fadeIn 1s ease 1s forwards;
            opacity: 0;
        }
        
        .confetti {
            position: absolute;
            width: 10px;
            height: 20px;
            opacity: 0;
        }
        
        .animate-confetti {
            animation: confetti-fall 3s ease-in-out forwards;
        }
        
        /* Footer */
        .footer{
            background: linear-gradient(rgba(0, 0, 0, 0.95), rgba(0, 0, 0, 0.95)), url('/Images/footer-bg.jpeg');
            background-size: cover;
            background-position: center;
            color: white;
            padding: 60px 0 20px;
        }
        
        .footer-content {
            display: flex;
            flex-wrap: wrap;
            gap: 40px;
            margin-bottom: 60px;
        }
        
        .footer-logo {
            flex: 1;
            min-width: 250px;
        }
        
        .footer-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .footer-brand-icon {
            width: 40px;
            height: 40px;
            background-color: white;
            color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-size: 20px;
        }
        
        .footer-brand-text {
            font-weight: 700;
            font-size: 1.5rem;
            color: white;
        }
        
        .footer-slogan {
            margin-bottom: 20px;
            font-weight: 300;
        }
        
        .footer-socials {
            display: flex;
            gap: 15px;
        }
        
        .social-link {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }
        
        .social-link:hover {
            background: var(--secondary);
            color: var(--dark);
            transform: translateY(-5px);
        }
        
        .footer-links {
            flex: 1;
            min-width: 150px;
        }
        
        .footer-heading {
            font-size: 1.2rem;
            margin-bottom: 20px;
            position: relative;
            display: inline-block;
        }
        
        .footer-heading::after {
            content: '';
            position: absolute;
            bottom: -8px;
            left: 0;
            width: 30px;
            height: 2px;
            background: var(--secondary);
        }
        
        .footer-nav {
            list-style: none;
        }
        
        .footer-nav li {
            margin-bottom: 10px;
        }
        
        .footer-nav a {
            color: rgba(255, 255, 255, 0.7);
            transition: all 0.3s ease;
        }
        
        .footer-nav a:hover {
            color: var(--secondary);
            padding-left: 5px;
        }
        
        .footer-bottom {
            text-align: center;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 0.9rem;
            color: rgba(255, 255, 255, 0.7);
        }
        
        /* Animations */
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
        
        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }
        
        @keyframes confetti-fall {
            0% {
                opacity: 1;
                transform: translateY(-100px) rotate(0deg);
            }
            100% {
                opacity: 0;
                transform: translateY(500px) rotate(360deg);
            }
        }
        
        .text-center {
            text-align: center;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .menu-toggle {
                display: block;
            }
            
            .nav-menu {
                display: none;
            }
            
            .page-hero-title {
                font-size: 2rem;
            }
        }
        
        @media (max-width: 768px) {
            .nav-wrapper {
                padding: 15px 0;
            }
            
            .page-hero {
                min-height: 55vh;
            }
            
            .page-hero-title {
                font-size: 1.8rem;
            }
            
            .page-hero-subtitle {
                font-size: 1.rem;
            }
            
            .page-hero-shape {
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 150px;
            background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1440 320"><path fill="white" fill-opacity="1" d="M0,160L48,149.3C96,139,192,117,288,122.7C384,128,480,160,576,170.7C672,181,768,171,864,154.7C960,139,1056,117,1152,117.3C1248,117,1344,139,1392,149.3L1440,160L1440,320L1392,320C1344,320,1248,320,1152,320C1056,320,960,320,864,320C768,320,672,320,576,320C480,320,384,320,288,320C192,320,96,320,48,320L0,320Z"></path></svg>');
            background-size: cover;
            z-index: 1;
        }
            
            .success-card {
                padding: 25px;
            }
            
            .footer-content {
                flex-direction: column;
                gap: 30px;
            }
            
            .buttons-container {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
            }
        }
        
        @media (max-width: 576px) {
            .logo-text {
                font-size: 18px;
            }
            
            .page-hero-title {
                font-size: 1.5rem;
            }
            
            .success-icon {
                width: 80px;
                height: 80px;
                font-size: 38px;
                margin-bottom: 20px;
            }
            
            .success-title {
                font-size: 1.8rem;
            }
        }
    </style>
    <link rel="stylesheet" href="application-theme.css">
</head>
<body>
    <!-- Header -->
    <header class="header" id="header">
        <div class="container">
            <div class="nav-wrapper">
                <a href="index.html" class="logo">
                    <div class="logo-icon"><img src="/Images/bfi-new-logo.svg"></div>
                    <div class="logo-text">Bold Footprint Initiatives</div>
                </a>
                
                <div class="menu-toggle" id="menuToggle">
                    <i class="fas fa-bars"></i>
                </div>
                
                <ul class="nav-menu">
                    <li><a href="index.html" class="nav-link">Home</a></li>
                    <li><a href="about.html" class="nav-link">About Us</a></li>
                    <li><a href="programs.html" class="nav-link">Our Programs</a></li>
                    <li><a href="talent.html" class="nav-link">BFI Talent of the Year</a></li>
                    <li><a href="/scholar-portal/" class="nav-link">Scholar Portal</a></li>
                    <li><a href="/admin/admin-login.php" class="nav-link">Admin Portal</a></li>
                </ul>
            </div>
        </div>
    </header>
    
    <!-- Mobile Menu -->
    <div class="mobile-menu-panel" id="mobileMenuPanel">
        <ul class="mobile-menu">
            <li><a href="index.html" class="mobile-link">Home</a></li>
            <li><a href="about.html" class="mobile-link">About Us</a></li>
            <li><a href="programs.html" class="mobile-link">Our Programs</a></li>
            <li><a href="talent.html" class="mobile-link">BFI Talent of the Year</a></li>
            <li><a href="/scholar-portal/" class="mobile-link">Scholar Portal</a></li>
            <li><a href="/admin/admin-login.php" class="mobile-link">Admin Portal</a></li>
        </ul>
        <div class="mobile-buttons">
            <a href="apply.html" class="btn btn-primary">Apply for Scholarship</a>
        </div>
    </div>
    
    <div class="menu-overlay" id="menuOverlay"></div>

    <!-- Hero Section -->
    <section class="page-hero">
        <div class="container">
            <div class="page-hero-content">
                <h1 class="page-hero-title">Application Submitted!</h1>
                <p class="page-hero-subtitle">Your scholarship application has been successfully received by our team</p>
            </div>
        </div>
        <div class="page-hero-shape"></div>
    </section>

    <!-- Success Content -->
    <section class="success-section">
        <div class="container">
            <div class="success-card">
                <div class="text-center">
                    <div class="success-icon">
                        <i class="fas fa-check"></i>
                    </div>
                    <h2 class="success-title">Success!</h2>
                </div>

                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-success">
                        <?php 
                        $message = $_SESSION['message'];
                        unset($_SESSION['message']);
                        
                        // Extract application ID if present in the message
                        preg_match('/BFI(?:-[A-Z]+-)?\d+/', $message, $matches);
                        $applicationId = $matches[0] ?? null;
                        
                        // Display message without application ID
                        echo str_replace($applicationId, '', $message);
                        
                        // If application ID was found, display it separately
                        if ($applicationId): ?>
                        </div>
                        <div class="application-id">
                            <i class="fas fa-id-badge"></i>
                            <span>Application ID: <?php echo $applicationId; ?></span>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-success">
                        Thank you for applying to the BFI Scholarship Program. Your application has been submitted successfully!
                    </div>
                <?php endif; ?>

                <div class="info-section">
                    <h3><i class="fas fa-info-circle"></i> What's Next?</h3>
                    <ul>
                        <li>We have sent a confirmation email with your application details. Please check your inbox (and spam folder).</li>
                        <li>Our team will review your application within 14 business days.</li>
                        <li>You can check your application status anytime using your Application ID and password.</li>
                        <li>Should we need any additional information, we will contact you via email.</li>
                    </ul>
                </div>

                <div class="buttons-container">
                    <a href="check-status.php" class="btn btn-success">
                        <i class="fas fa-search"></i> Check Application Status
                    </a>
                    <a href="index.html" class="btn btn-primary">
                        <i class="fas fa-home"></i> Return to Home
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="footer-content">
                <div class="footer-logo">
                    <div class="footer-brand">
                        <div class="footer-brand-text">Bold Footprint Initiatives</div>
                    </div>
                    <p class="footer-slogan">Empowering dreams through education</p>
                    <div class="footer-socials">
                        <a href="https://web.facebook.com/profile.php?id=61574771032448" class="social-link"><i class="fab fa-facebook-f"></i></a>
                        <a href="https://twitter.com/bfinitiatives" class="social-link"><i class="fab fa-x-twitter"></i></a>
                        <a href="https://www.instagram.com/bfinitiatives" class="social-link" target="_blank" rel="noopener" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="social-link"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                
                <div class="footer-links">
                    <h4 class="footer-heading">Connect With Us</h4>
                    <ul class="footer-nav">
                        <li><a href="https://web.facebook.com/profile.php?id=61574771032448"><i class="fab fa-facebook-f"></i> Facebook</a></li>
                        <li><a href="https://twitter.com/bfinitiatives"><i class="fab fa-x-twitter"></i> X</a></li>
                        <li><a href="#"><i class="fab fa-instagram"></i> Instagram</a></li>
                        <li><a href="#"><i class="fab fa-linkedin-in"></i> LinkedIn</a></li>
                    </ul>
                </div>
                
                <div class="footer-links">
                    <h4 class="footer-heading">Quick Links</h4>
                    <ul class="footer-nav">
                        <li><a href="index.html">Home</a></li>
                        <li><a href="about.html">About Us</a></li>
                        <li><a href="programs.html">Our Programs</a></li>
                        <li><a href="talent.html">Talent of the Year</a></li>
                        <li><a href="mentor.html">Become a Mentor</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="footer-bottom">
                <p>&copy; 2024 Bold Footprint Initiatives. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <script>
        // DOM Elements
        const header = document.getElementById('header');
        const menuToggle = document.getElementById('menuToggle');
        const mobileMenuPanel = document.getElementById('mobileMenuPanel');
        const menuOverlay = document.getElementById('menuOverlay');
        
        // Event Listeners
        menuToggle.addEventListener('click', toggleMenu);
        menuOverlay.addEventListener('click', closeMenu);
        
        // Functions
        function toggleMenu() {
            mobileMenuPanel.classList.toggle('active');
            menuOverlay.classList.toggle('active');
            document.body.style.overflow = mobileMenuPanel.classList.contains('active') ? 'hidden' : 'auto';
            
            // Toggle icon
            if (mobileMenuPanel.classList.contains('active')) {
                menuToggle.innerHTML = '<i class="fas fa-times"></i>';
            } else {
                menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
            }
        }
        
        function closeMenu() {
            mobileMenuPanel.classList.remove('active');
            menuOverlay.classList.remove('active');
            document.body.style.overflow = 'auto';
            menuToggle.innerHTML = '<i class="fas fa-bars"></i>';
        }
        
        // Create confetti effect
        document.addEventListener('DOMContentLoaded', function() {
            const colors = ['#2c5282', '#10b981', '#f59e0b', '#ef4444', '#3b82f6'];
            const confettiCount = 100;
            const container = document.querySelector('.success-card');
            
            for (let i = 0; i < confettiCount; i++) {
                const confetti = document.createElement('div');
                confetti.classList.add('confetti');
                confetti.style.backgroundColor = colors[Math.floor(Math.random() * colors.length)];
                confetti.style.left = Math.random() * 100 + '%';
                confetti.style.width = Math.random() * 8 + 5 + 'px';
                confetti.style.height = Math.random() * 16 + 8 + 'px';
                confetti.style.animationDelay = Math.random() * 3 + 's';
                confetti.classList.add('animate-confetti');
                container.appendChild(confetti);
            }
        });
    </script>
</body>
</html>

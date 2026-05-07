<?php
session_start();
require_once 'includes/config.php';
require_once 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Initialize default values
$first_name = '';
$last_name = '';
$scholarship_type = '';
$amount = '';
$start_date = '';
$end_date = '';
$disbursement_schedule = '';
$academic_requirements = '';
$profile_picture = null;

// Get scholarship details
try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Get user and scholarship details
    $stmt = $conn->prepare("
        SELECT 
            u.first_name, 
            u.last_name,
            u.profile_picture,
            s.scholarship_type,
            s.amount,
            s.start_date,
            s.end_date,
            s.disbursement_schedule,
            s.academic_requirements
        FROM users u 
        LEFT JOIN scholarships s ON u.id = s.user_id
        WHERE u.id = :user_id
    ");
    
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($data) {
        $first_name = $data['first_name'] ?? '';
        $last_name = $data['last_name'] ?? '';
        $profile_picture = $data['profile_picture'] ?? null;
        $scholarship_type = $data['scholarship_type'] ?? 'BFI Standard Scholarship';
        $amount = $data['amount'] ?? '$5,000';
        $start_date = !empty($data['start_date']) ? date('F d, Y', strtotime($data['start_date'])) : 'January 1, 2024';
        $end_date = !empty($data['end_date']) ? date('F d, Y', strtotime($data['end_date'])) : 'December 31, 2024';
        $disbursement_schedule = $data['disbursement_schedule'] ?? 'Semester-based';
        $academic_requirements = $data['academic_requirements'] ?? 'Maintain 3.0 GPA';
    }
    
} catch (Exception $e) {
    error_log("Scholarship details error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Scholarship Details - BFI Scholar Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Include the same base styles as dashboard.php */
        /* ... Copy base styles from dashboard.php ... */
        
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
            font-family: 'Poppins', system-ui, -apple-system, sans-serif;
            background-image: url('/Images/apply-scholar.jpeg');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            background-repeat: no-repeat;
            min-height: 100vh;
            padding: 40px 20px;
            position: relative;
            color: var(--text-color);
            line-height: 1.6;
            margin: 0;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(245, 247, 250, 0.95) 0%, rgba(227, 238, 255, 0.92) 100%);
            z-index: -1;
        } 
        
        /* Mobile-First Base Styles */
        :root {
            --sidebar-width: 250px;
            --header-height: 70px;
            --footer-height: 60px;
        }

        /* Mobile Navigation Toggle Button */
        .mobile-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
            background: var(--primary-color);
            color: white;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
            border: none;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }

        /* Responsive Breakpoints */
        @media (max-width: 1024px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .resources-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            /* Show mobile toggle */
            .mobile-toggle {
                display: block;
            }

            /* Sidebar modifications */
            .sidebar {
                transform: translateX(-100%);
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                width: 80%;
                max-width: 300px;
                z-index: 1000;
                transition: transform 0.3s ease-in-out;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            /* Header modifications */
            .header {
                left: 0;
                padding: 0 15px;
            }

            .header-left {
                display: none;
            }

            .header-right {
                width: 100%;
                justify-content: flex-end;
            }

            .user-profile span {
                display: none;
            }

            /* Main content modifications */
            .main-content {
                margin-left: 0;
                padding: 15px;
            }

            /* Stats grid modifications */
            .stats-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            /* Quick actions modifications */
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
            }

            /* Resources grid modifications */
            .resources-grid {
                grid-template-columns: 1fr;
                gap: 15px;
            }

            /* Footer modifications */
            .footer {
                left: 0;
                padding: 0 15px;
                flex-direction: column;
                height: auto;
                padding: 10px 0;
            }

            .footer-links {
                flex-wrap: wrap;
                justify-content: center;
                gap: 10px;
            }

            .footer-copyright {
                font-size: 0.8rem;
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            /* Further refinements for smaller screens */
            .header {
                height: 60px;
            }

            .welcome-message {
                font-size: 1.2rem;
            }

            .stat-card {
                padding: 15px;
            }

            .stat-card .value {
                font-size: 1.5rem;
            }

            .quick-actions {
                grid-template-columns: 1fr;
            }

            .mobile-toggle {
                top: 10px;
                left: 10px;
            }

            .notification-badge {
                width: 16px;
                height: 16px;
                font-size: 10px;
            }

            .user-avatar {
                width: 35px;
                height: 35px;
            }
        }
        
        /* User Avatar Styles */
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            background: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
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
            font-size: 1.2em;
            text-transform: uppercase;
        }

        .fallback-avatar {
            background-color: var(--primary-color);
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .user-name {
            margin-left: 10px;
            font-weight: 500;
        }

        /* Hover effect for profile picture */
        .user-avatar:hover .profile-image {
            transform: scale(1.1);
            transition: transform 0.3s ease;
        }

        /* Profile picture dropdown (optional) */
        .user-profile {
            position: relative;
            cursor: pointer;
        }

        .profile-dropdown {
            position: absolute;
            top: 100%;
            right: 0;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 10px 0;
            min-width: 200px;
            display: none;
            z-index: 1000;
        }

        .profile-dropdown.active {
            display: block;
        }

        .profile-dropdown-item {
            padding: 10px 20px;
            color: var(--text-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 10px;
            transition: background-color 0.3s;
        }

        .profile-dropdown-item:hover {
            background-color: rgba(67, 97, 238, 0.05);
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
        }

        .sidebar-overlay.active {
            display: block;
        }
        
        /* Header Styles */
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

        /* Sidebar Styles */
        .sidebar {
            width: var(--sidebar-width);
            background: rgba(255, 255, 255, 0.95);
            padding: 20px;
            box-shadow: 2px 0 5px rgba(0,0,0,0.1);
            position: fixed;
            left: 0;
            top: 0;
            margin: 0;
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

        .main-content {
            flex: 1;
            margin-left: var(--sidebar-width);
            margin-top: var(--header-height);
            padding: 20px;
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
        
        /* Additional styles for scholarship details */
        .scholarship-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            padding: 30px;
            margin-bottom: 25px;
        }
        
        .scholarship-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .scholarship-title {
            font-size: 1.8rem;
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: bold;
        }
        
        .scholarship-badge {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            color: white;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .details-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .detail-item {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
        }
        
        .detail-label {
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 5px;
        }
        
        .detail-value {
            font-size: 1.2rem;
            font-weight: 500;
            color: var(--text-color);
        }
        
        .agreement-container {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-top: 30px;
        }
        
        .btn-download {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 10px 25px;
            border-radius: 25px;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
        }
        
        .btn-download:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 4px 10px rgba(0,0,0,0.1);
        }
        
        .requirements-list {
            margin-top: 20px;
        }
        
        .requirement-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            padding: 12px 0;
            border-bottom: 1px solid #eee;
        }
        
        .requirement-item:last-child {
            border-bottom: none;
        }
        
        .requirement-icon {
            width: 30px;
            height: 30px;
            background: rgba(67, 97, 238, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
        }
        
        .contact-info {
            background: white;
            border-radius: 10px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-top: 25px;
        }
        
        .contact-title {
            font-size: 1.2rem;
            margin-bottom: 15px;
            color: var(--primary-color);
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 15px;
        }
        
        .contact-icon {
            width: 40px;
            height: 40px;
            background: rgba(67, 97, 238, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--primary-color);
        }
        
        /* Footer */
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
        
    </style>
</head>
<body>
    <!-- Sidebar Overlay -->
    <div class="sidebar-overlay"></div>
    
    <div class="container">
        <!-- Mobile Menu Toggle -->
        <div class="mobile-toggle">
            <i class="fas fa-bars"></i>
        </div>

        <!-- Header -->
        <header class="header">
            <div class="header-right">
                <div class="notification-bell">
                    <i class="fas fa-bell"></i>
                    <span class="notification-badge">3</span>
                </div>
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php if ($profile_picture): ?>
                            <img src="<?php echo htmlspecialchars('/uploads/profile_pictures/' . $profile_picture); ?>" 
                                 alt="Profile Picture"
                                 class="profile-image"
                                 onerror="this.onerror=null; this.src='/images/default-avatar.png'; this.classList.add('fallback-avatar');">
                        <?php else: ?>
                            <div class="avatar-initials">
                                <?php echo strtoupper(substr($first_name, 0, 1)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <span class="user-name"><?php echo htmlspecialchars($first_name); ?></span>
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
                        My Profile
                    </a>
                </li>
                <li class="nav-item">
                    <a href="scholarship-details.php" class="nav-link">
                        <i class="fas fa-graduation-cap"></i>
                        Scholarship Details
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

        <!-- Main Content -->
        <div class="main-content">
            <div class="dashboard-header">
                <h1 class="welcome-message">Scholarship Details</h1>
                <p>Review your scholarship information and requirements</p>
            </div>
            
            <!-- Scholarship Details Card -->
            <div class="scholarship-card">
                <div class="scholarship-header">
                    <h2 class="scholarship-title"><?php echo htmlspecialchars($scholarship_type); ?></h2>
                    <span class="scholarship-badge">Active</span>
                </div>
                
                <div class="details-grid">
                    <div class="detail-item">
                        <div class="detail-label">Scholarship Amount</div>
                        <div class="detail-value"><?php echo htmlspecialchars($amount); ?></div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Start Date</div>
                        <div class="detail-value"><?php echo htmlspecialchars($start_date); ?></div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">End Date</div>
                        <div class="detail-value"><?php echo htmlspecialchars($end_date); ?></div>
                    </div>
                    
                    <div class="detail-item">
                        <div class="detail-label">Disbursement Schedule</div>
                        <div class="detail-value"><?php echo htmlspecialchars($disbursement_schedule); ?></div>
                    </div>
                </div>
                
                <h3>Scholarship Requirements</h3>
                <div class="requirements-list">
                    <div class="requirement-item">
                        <div class="requirement-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div>
                            <strong>Academic Performance</strong>
                            <p>Maintain a minimum GPA of 3.0 throughout the scholarship period.</p>
                        </div>
                    </div>
                    
                    <div class="requirement-item">
                        <div class="requirement-icon">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                        <div>
                            <strong>Course Load</strong>
                            <p>Maintain full-time enrollment status with a minimum of 12 credit hours per semester.</p>
                        </div>
                    </div>
                    
                    <div class="requirement-item">
                        <div class="requirement-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div>
                            <strong>Program Participation</strong>
                            <p>Attend at least 75% of scheduled BFI events and workshops.</p>
                        </div>
                    </div>
                    
                    <div class="requirement-item">
                        <div class="requirement-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div>
                            <strong>Progress Reports</strong>
                            <p>Submit required progress reports at the end of each semester.</p>
                        </div>
                    </div>
                </div>
                
                <div class="agreement-container">
                    <h3>Scholarship Agreement</h3>
                    <p>You can download a copy of your scholarship agreement below. This document contains detailed terms and conditions of your scholarship award.</p>
                    <a href="view-agreement.php" class="btn btn-download mt-3">
                        <i class="fas fa-file-pdf"></i> View Agreement
                    </a>
                </div>
            </div>
            
            <!-- Contact Information -->
            <div class="contact-info">
                <h3 class="contact-title">Scholarship Support</h3>
                <p>If you have any questions about your scholarship, please contact your scholarship coordinator:</p>
                
                <div class="contact-item">
                    <div class="contact-icon">
                        <i class="fas fa-user"></i>
                    </div>
                    <div>
                        <strong>Scholarship Coordinator</strong>
                        <p>Olanrewaju Akande</p>
                    </div>
                </div>
                
                <div class="contact-item">
                    <div class="contact-icon">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div>
                        <strong>Email</strong>
                        <p>olanrewaju.akande@bfinitiatives.com</p>
                    </div>
                </div>
                
                <div class="contact-item">
                    <div class="contact-icon">
                        <i class="fas fa-phone"></i>
                    </div>
                    <div>
                        <strong>Phone</strong>
                        <p>(234) 816 501 1291</p>
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
                <a href="/"><i class="fas fa-home me-2"></i>Home</a>
                <a href="/about.html"><i class="fas fa-info-circle me-2"></i>About Us</a>
                <a href="/programs.html"><i class="fas fa-graduation-cap me-2"></i>Programs</a>
            </div>
        </footer>
    </div>

    <script>
        // Include the same JavaScript as dashboard.php
        document.addEventListener('DOMContentLoaded', function() {
            const mobileToggle = document.querySelector('.mobile-toggle');
            const sidebar = document.querySelector('.sidebar');
            const overlay = document.querySelector('.sidebar-overlay');
            
            // Toggle sidebar
            function toggleSidebar() {
                sidebar.classList.toggle('active');
                overlay.classList.toggle('active');
                document.body.style.overflow = sidebar.classList.contains('active') ? 'hidden' : '';
            }

            // Event listeners
            mobileToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                toggleSidebar();
            });

            overlay.addEventListener('click', toggleSidebar);

            document.addEventListener('click', function(e) {
                if (!sidebar.contains(e.target) && 
                    !mobileToggle.contains(e.target) && 
                    sidebar.classList.contains('active')) {
                    toggleSidebar();
                }
            });
            
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

            sidebar.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        });
    </script>
</body>
</html>
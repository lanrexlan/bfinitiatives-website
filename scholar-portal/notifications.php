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
$user_data = [];
$notifications = [];

try {
    $db = new Database();
    $conn = $db->getConnection();
    
    // Fetch user data
    $stmt = $conn->prepare("SELECT first_name, last_name FROM users WHERE id = :user_id");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Mark notifications as read if requested
    if (isset($_POST['mark_read']) && !empty($_POST['notification_id'])) {
        $update_stmt = $conn->prepare("
            UPDATE notifications 
            SET read_at = CURRENT_TIMESTAMP 
            WHERE id = :notification_id AND user_id = :user_id
        ");
        $update_stmt->execute([
            ':notification_id' => $_POST['notification_id'],
            ':user_id' => $_SESSION['user_id']
        ]);
    }
    
    // Fetch notifications
    $stmt = $conn->prepare("
        SELECT 
            id,
            type,
            message,
            created_at,
            read_at,
            action_url,
            priority
        FROM notifications 
        WHERE user_id = :user_id 
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $stmt->execute([':user_id' => $_SESSION['user_id']]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Scholar Portal</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/dashboard.css">
    <style>
        :root {
            --sidebar-width: 200px;
            --header-height: 80px;
            --footer-height: 60px;
            --primary-color: #4a90e2;
            --secondary-color: #357abd;
            --accent-color: #6fcf97;
            --text-color: #2d3436;
            --bg-color: #f8f9fa;
            --card-bg: rgba(255, 255, 255, 0.98);
        }
        

        
        body {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)),
                        url('/Images/apply-scholar.jpeg') center/cover fixed;
            color: var(--text-color);
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
        }
        
        /* Improved Sidebar */
        .sidebar {
            width: var(--sidebar-width);
            background: var(--card-bg);
            padding: 20px 15px !important; /* Reduced right padding */
            box-shadow: 4px 0 15px rgba(0,0,0,0.05);
            transform: translateX(0);
            z-index: 1000;
            border-right: 1px solid rgba(0,0,0,0.05);
        }

        .nav-link {
            padding: 14px 25px;
            margin: 6px 15px;
            border-radius: 12px;
        }

        .nav-link:hover {
            background: linear-gradient(135deg, var(--primary-color), var(--accent-color));
            box-shadow: 0 3px 12px rgba(74, 144, 226, 0.2);
        }

        /* Main Content Adjustments */
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 30px;
            background: var(--bg-color);
            min-height: calc(100vh - var(--header-height));
        }

        .dashboard-header {
            background: var(--card-bg);
            border-radius: 15px;
            padding: 25px 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            margin-bottom: 30px;
            text-align: center;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 15px;
        }

        /* Notification List Improvements */
        .notification-list {
            max-width: 800px;
            margin: 0 auto;
            padding-bottom: 80px; /* Prevent footer overlap */
        }

        .notification-item {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 20px 25px;
            margin-bottom: 15px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.04);
            transition: all 0.2s ease;
            border-left: 4px solid transparent;
        }

        .notification-item.unread {
            border-left-color: var(--primary-color);
            background: linear-gradient(to right, rgba(74, 144, 226, 0.03), transparent);
        }

        .notification-icon {
            width: 45px;
            height: 45px;
            border-radius: 12px;
            font-size: 1.1rem;
        }

        .notification-content {
            padding-right: 30px;
        }

        .notification-message {
            color: var(--text-color);
            line-height: 1.6;
            font-size: 0.95rem;
        }

        /* Filter Buttons */
        .notification-filter {
            margin-bottom: 30px;
            gap: 15px;
            justify-content: center;
    margin: 30px 0;
        }

        .filter-btn {
            padding: 10px 25px;
            border-radius: 25px;
            background: #f1f3f5;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .filter-btn.active {
            background: var(--primary-color);
            color: white;
            border-color: var(--secondary-color);
            transform: translateY(-2px);
        }
        
        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                width: 280px;
            }

            .main-content {
                margin-left: 0;
                padding: 20px;
            }

            .footer {
                left: 0;
                justify-content: center !important;
    flex-direction: column;
    gap: 15px;
    text-align: center;
            }

            .notification-item {
                padding: 15px;
            }
        }

        /* New Additions */
        .notification-priority {
            position: absolute;
            top: 15px;
            right: 15px;
            font-size: 0.8rem;
            padding: 4px 10px;
            border-radius: 15px;
            background: rgba(255, 235, 59, 0.15);
            color: #f39c12;
        }

        .mark-read-btn {
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .notification-item:hover .mark-read-btn {
            opacity: 1;
        }

        .type-badge {
            font-size: 0.8rem;
            margin-top: 8px;
            display: inline-block;
            padding: 4px 12px;
            border-radius: 15px;
            background: rgba(74, 144, 226, 0.1);
            color: var(--primary-color);
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
    
    
        .notification-list {
            max-width: 800px;
            margin: 0 auto;
        }

        .notification-item {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
            position: relative;
            display: flex;
            align-items: start;
            gap: 15px;
        }

        .notification-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }

        .notification-item.unread {
            background: #fff;
            border-left: 4px solid var(--primary-color);
        }

        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .icon-info {
            background: #cce5ff;
            color: #004085;
        }

        .icon-warning {
            background: #fff3cd;
            color: #856404;
        }

        .icon-success {
            background: #d4edda;
            color: #155724;
        }

        .icon-error {
            background: #f8d7da;
            color: #721c24;
        }

        .notification-content {
            flex: 1;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 5px;
        }

        .notification-time {
            font-size: 0.85rem;
            color: #666;
        }

        .notification-message {
            color: #444;
            margin-bottom: 10px;
        }

        .notification-actions {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .action-btn {
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            border: none;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-secondary {
            background: #f8f9fa;
            color: #444;
        }

        .notification-filter {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-btn {
            padding: 8px 15px;
            border: none;
            border-radius: 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .filter-btn.active {
            background: var(--primary-color);
            color: white;
        }

        .no-notifications {
            text-align: center;
            padding: 40px;
            background: var(--card-bg);
            border-radius: 10px;
            margin-top: 20px;
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
            text-align: left;
    padding-left: 20px;
        }

        .sidebar-header h2 {
            color: var(--primary-color);
            font-size: 1.5rem;
        }

        .nav-menu {
            list-style: none;
            padding-left: 0;
            margin: 0;
        }

        .nav-item {
            margin-bottom: 10px;
            margin-left: 0;
    width: 100%;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            color: var(--text-color);
            text-decoration: none;
            border-radius: 8px;
            transition: 0.3s;
            justify-content: flex-start !important;
    padding: 12px 20px !important;
    margin: 6px 0 !important;
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
            margin-right: 15px;
    width: 20px;
    text-align: left; /* Align icons to left */
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
    </style>
</head>
<body>
     <!-- Modified Notification Item Structure -->
    <?php foreach ($notifications as $notification): ?>
        <div class="notification-item <?php echo is_null($notification['read_at']) ? 'unread' : ''; ?>">
            <div class="notification-icon icon-<?php echo htmlspecialchars($notification['type']); ?>">
                <i class="fas fa-<?php echo getNotificationIcon($notification['type']); ?>"></i>
            </div>
            <div class="notification-content">
                <div class="notification-header">
                    <span class="notification-time">
                        <?php echo getTimeAgo($notification['created_at']); ?>
                    </span>
                    <?php if($notification['priority'] === 'high'): ?>
                        <span class="notification-priority">❗ High Priority</span>
                    <?php endif; ?>
                </div>
                <div class="notification-message">
                    <?php echo htmlspecialchars($notification['message']); ?>
                </div>
                <span class="type-badge"><?php echo ucfirst($notification['type']); ?></span>
                <div class="notification-actions">
                    <?php if ($notification['action_url']): ?>
                        <a href="<?php echo htmlspecialchars($notification['action_url']); ?>" 
                           class="action-btn btn-primary">
                            View Details →
                        </a>
                    <?php endif; ?>
                    <?php if (is_null($notification['read_at'])): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="notification_id" 
                                   value="<?php echo $notification['id']; ?>">
                            <button type="submit" name="mark_read" 
                                    class="action-btn btn-secondary mark-read-btn">
                                Mark as Read
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

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


    <div class="main-content">
        <header class="dashboard-header">
            <h1>Notifications</h1>
            <div class="user-info">
                <span>Welcome, <?php echo htmlspecialchars($user_data['first_name'] ?? 'User'); ?></span>
            </div>
        </header>

        <div class="content-wrapper">
            <div class="notification-filter">
                <button class="filter-btn active" data-filter="all">All</button>
                <button class="filter-btn" data-filter="unread">Unread</button>
                <button class="filter-btn" data-filter="important">Important</button>
            </div>

            <?php if (empty($notifications)): ?>
                <div class="no-notifications">
                    <i class="fas fa-bell fa-3x" style="color: var(--primary-color); margin-bottom: 15px;"></i>
                    <h2>No Notifications</h2>
                    <p>You're all caught up! Check back later for updates.</p>
                </div>
            <?php else: ?>
                <div class="notification-list">
                    <?php foreach ($notifications as $notification): ?>
                        <div class="notification-item <?php echo is_null($notification['read_at']) ? 'unread' : ''; ?>"
                             data-priority="<?php echo htmlspecialchars($notification['priority']); ?>">
                            <div class="notification-icon icon-<?php echo htmlspecialchars($notification['type']); ?>">
                                <i class="fas fa-<?php echo getNotificationIcon($notification['type']); ?>"></i>
                            </div>
                            
                            <div class="notification-content">
                                <div class="notification-header">
                                    <span class="notification-time">
                                        <?php echo getTimeAgo($notification['created_at']); ?>
                                    </span>
                                </div>
                                
                                <div class="notification-message">
                                    <?php echo htmlspecialchars($notification['message']); ?>
                                </div>
                                
                                <div class="notification-actions">
                                    <?php if ($notification['action_url']): ?>
                                        <a href="<?php echo htmlspecialchars($notification['action_url']); ?>" 
                                           class="action-btn btn-primary">
                                            View Details
                                        </a>
                                    <?php endif; ?>
                                    
                                    <?php if (is_null($notification['read_at'])): ?>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="notification_id" 
                                                   value="<?php echo $notification['id']; ?>">
                                            <button type="submit" name="mark_read" 
                                                    class="action-btn btn-secondary">
                                                Mark as Read
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
           <!-- Footer -->
        <footer class="footer">
            <div class="footer-copyright">
                © 2024 Bright Future Initiatives. All rights reserved.
            </div>
            <div class="footer-links">
                <a href="/"><i class="fas fa-home me-2"></i>Home</a>
                        <a href="/about-us"><i class="fas fa-info-circle me-2"></i>About Us</a>
                        <a href="/programs"><i class="fas fa-graduation-cap me-2"></i>Programs</a>
            </div>
        </footer>
    </div>
    
    <script>
        // Mobile menu toggle
        document.querySelector('.mobile-toggle').addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('active');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.querySelector('.sidebar');
            const toggle = document.querySelector('.mobile-toggle');
            
            if (!sidebar.contains(event.target) && !toggle.contains(event.target) && window.innerWidth <= 768) {
                sidebar.classList.remove('active');
            }
        });
    </script>
    
    <script>
        // Helper function to get notification icon
        <?php
        function getNotificationIcon($type) {
            $icons = [
                'info' => 'info-circle',
                'warning' => 'exclamation-triangle',
                'success' => 'check-circle',
                'error' => 'times-circle'
            ];
            return $icons[$type] ?? 'bell';
        }

        // Helper function to format time ago
        function getTimeAgo($timestamp) {
            $time = strtotime($timestamp);
            $now = time();
            $diff = $now - $time;
            
            if ($diff < 60) {
                return 'Just now';
            } elseif ($diff < 3600) {
                return floor($diff / 60) . ' minutes ago';
            } elseif ($diff < 86400) {
                return floor($diff / 3600) . ' hours ago';
            } elseif ($diff < 604800) {
                return floor($diff / 86400) . ' days ago';
            } else {
                return date('M j, Y', $time);
            }
        }
        ?>

        // Notification filtering
        document.querySelectorAll('.filter-btn').forEach(button => {
            button.addEventListener('click', function() {
                // Update active button
                document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
                this.classList.add('active');

                // Filter notifications
                const filter = this.dataset.filter;
                document.querySelectorAll('.notification-item').forEach(item => {
                    if (filter === 'all' || 
                        (filter === 'unread' && item.classList.contains('unread')) ||
                        (filter === 'important' && item.dataset.priority === 'high')) {
                        item.style.display = 'flex';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        });
    </script>
</body>
</html>